<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Applies WATI webhook message-status events (sent / delivered / read /
 * failed) to the matching whatsapp_message_logs row.
 *
 * Matching: WATI echoes back the `localMessageId` it returned in the
 * original send response (stored in the generated column
 * wati_local_message_id). The first matched event also records WhatsApp's
 * own message id (wamid) into `message_id`, used as a fallback key for any
 * later event that only carries that id.
 *
 * Events can arrive out of order or be repeated, so status only ever moves
 * forward: SENT -> DELIVERED -> READ. FAILED is accepted only while the
 * message has not been confirmed delivered.
 */
class WhatsappDeliveryStatusService
{
    public const SENT = 'SENT';
    public const DELIVERED = 'DELIVERED';
    public const READ = 'READ';
    public const FAILED = 'FAILED';

    private const RANK = [
        self::SENT => 1,
        self::DELIVERED => 2,
        self::READ => 3,
    ];

    // Incoming patient messages and contact events -- not about a message we sent.
    private const IGNORED_EVENTS = [
        'message',
        'newcontactmessagereceived',
    ];

    /**
     * @return array{stored: bool, matched_log_id: ?int, delivery_status: ?string}
     */
    public function handle(array $payload): array
    {
        $eventType = (string) ($payload['eventType'] ?? $payload['event_type'] ?? '');

        if (in_array(strtolower($eventType), self::IGNORED_EVENTS, true)) {
            return ['stored' => false, 'matched_log_id' => null, 'delivery_status' => null];
        }

        $status = $this->resolveStatus($eventType, (string) ($payload['statusString'] ?? $payload['status'] ?? ''));

        if ($status === null) {
            return ['stored' => false, 'matched_log_id' => null, 'delivery_status' => null];
        }

        $localMessageId = $this->firstString($payload, ['localMessageId', 'local_message_id']);
        $whatsappMessageId = $this->firstString($payload, ['whatsappMessageId', 'whatsapp_message_id', 'wamid']);
        $eventAt = $this->resolveTimestamp($payload['timestamp'] ?? $payload['created'] ?? null);
        $failureReason = $status === self::FAILED ? $this->resolveFailureReason($payload) : null;

        $matchedLogId = $this->applyToLog($localMessageId, $whatsappMessageId, $status, $eventAt, $failureReason);

        DB::table('wati_webhook_events')->insert([
            'event_type' => substr($eventType, 0, 60) ?: null,
            'delivery_status' => $status,
            'local_message_id' => $localMessageId ? substr($localMessageId, 0, 64) : null,
            'whatsapp_message_id' => $whatsappMessageId ? substr($whatsappMessageId, 0, 191) : null,
            'whatsapp_message_log_id' => $matchedLogId,
            'payload' => json_encode($payload),
            'created_at' => now(),
        ]);

        return ['stored' => true, 'matched_log_id' => $matchedLogId, 'delivery_status' => $status];
    }

    /**
     * The tables are MyISAM (no transactions / row locks), so two events for
     * the same message arriving together (e.g. DELIVERED and READ) are made
     * safe with compare-and-set: the UPDATE only applies if the delivery
     * fields are still what we read, otherwise re-read and recompute.
     */
    private function applyToLog(?string $localMessageId, ?string $whatsappMessageId, string $status, Carbon $eventAt, ?string $failureReason): ?int
    {
        $columns = ['id', 'message_id', 'delivery_status', 'delivered_at', 'read_at'];

        for ($attempt = 0; $attempt < 5; $attempt++) {

            $log = null;

            if ($localMessageId) {
                $log = DB::table('whatsapp_message_logs')->where('wati_local_message_id', $localMessageId)->first($columns);
            }

            if (!$log && $whatsappMessageId) {
                $log = DB::table('whatsapp_message_logs')->where('message_id', $whatsappMessageId)->first($columns);
            }

            if (!$log) {
                return null;
            }

            $updates = $this->buildUpdates($log, $status, $eventAt, $failureReason);

            if ($whatsappMessageId && empty($log->message_id)) {
                $updates['message_id'] = substr($whatsappMessageId, 0, 100);
            }

            if (!$updates) {
                return (int) $log->id;
            }

            $updates['delivery_updated_at'] = now();
            $updates['updated_at'] = now();

            $query = DB::table('whatsapp_message_logs')->where('id', $log->id);
            foreach (['message_id', 'delivery_status', 'delivered_at', 'read_at'] as $col) {
                $log->{$col} === null
                    ? $query->whereNull($col)
                    : $query->where($col, $log->{$col});
            }

            if ($query->update($updates) > 0) {
                return (int) $log->id;
            }
            // Row changed underneath us -- loop and recompute from fresh values.
        }

        return isset($log) ? (int) $log->id : null;
    }

    private function buildUpdates(object $log, string $status, Carbon $eventAt, ?string $failureReason): array
    {
        $current = $log->delivery_status;
        $currentRank = self::RANK[$current] ?? 0;
        $updates = [];

        switch ($status) {

            case self::SENT:
                if ($current === null) {
                    $updates['delivery_status'] = self::SENT;
                }
                break;

            case self::DELIVERED:
                if (!$log->delivered_at) {
                    $updates['delivered_at'] = $eventAt;
                }
                if ($currentRank < self::RANK[self::DELIVERED]) {
                    $updates['delivery_status'] = self::DELIVERED;
                }
                break;

            case self::READ:
                // A read receipt implies delivery even if that event was lost.
                if (!$log->delivered_at) {
                    $updates['delivered_at'] = $eventAt;
                }
                if (!$log->read_at) {
                    $updates['read_at'] = $eventAt;
                }
                if ($current !== self::READ) {
                    $updates['delivery_status'] = self::READ;
                }
                break;

            case self::FAILED:
                if ($currentRank < self::RANK[self::DELIVERED]) {
                    $updates['delivery_status'] = self::FAILED;
                    $updates['delivery_failed_at'] = $eventAt;
                    $updates['delivery_failure_reason'] = $failureReason;
                }
                break;
        }

        return $updates;
    }

    /**
     * WATI names status events like templateMessageSent, sentMessageDELIVERED,
     * sentMessageREAD, sentMessageREPLIED, templateMessageFailed, and also
     * sends a statusString ("Sent", "Delivered", "Read", "Failed"). Checked
     * most-specific first since "sentMessageDELIVERED" also contains "SENT".
     */
    public function resolveStatus(string $eventType, string $statusString): ?string
    {
        $haystack = strtoupper($eventType . ' ' . $statusString);

        if (str_contains($haystack, 'FAIL')) {
            return self::FAILED;
        }
        if (str_contains($haystack, 'READ') || str_contains($haystack, 'REPLIED')) {
            return self::READ;
        }
        if (str_contains($haystack, 'DELIVERED')) {
            return self::DELIVERED;
        }
        if (str_contains($haystack, 'SENT')) {
            return self::SENT;
        }

        return null;
    }

    private function resolveTimestamp($value): Carbon
    {
        $tz = config('app.timezone');

        try {
            if (is_numeric($value)) {
                $seconds = (int) $value;
                // Milliseconds epoch
                if ($seconds > 100000000000) {
                    $seconds = intdiv($seconds, 1000);
                }
                return Carbon::createFromTimestamp($seconds)->setTimezone($tz);
            }

            if (is_string($value) && $value !== '') {
                return Carbon::parse($value)->setTimezone($tz);
            }
        } catch (\Throwable $e) {
            // fall through
        }

        return now();
    }

    private function resolveFailureReason(array $payload): ?string
    {
        $parts = array_filter([
            $this->firstString($payload, ['failedCode', 'failed_code', 'errorCode']),
            $this->firstString($payload, ['failedDetail', 'failed_detail', 'failedReason', 'errorMessage', 'error']),
        ]);

        if (!$parts) {
            return 'WhatsApp reported the message as failed';
        }

        return substr(implode(' - ', $parts), 0, 500);
    }

    private function firstString(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }
}
