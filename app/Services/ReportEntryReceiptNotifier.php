<?php

namespace App\Services;

use App\Mail\ReportEntryReceiptMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the logged-in employee a copy of the report content they just saved
 * or confirmed, so they hold their own record of the entry (non-repudiation).
 * The snapshot is taken at call time; the mail itself is sent after the HTTP
 * response so a slow SMTP server never holds up the save, and any failure is
 * logged rather than allowed to fail the save/confirm.
 */
class ReportEntryReceiptNotifier
{
    // Every receipt is also copied here (the office's record of who entered what).
    private const LABELS = [
        'pathology' => 'Pathology',
        'usg' => 'USG',
        'cardiology' => 'Cardiology (Echo)',
        'non_pathology' => 'Non-Pathology',
    ];

    // Bookkeeping columns -- not report content.
    private const SKIP_FIELDS = [
        'invoice_no', 'invoice_detail_id', 'item_code', 'item_code_sub', 'item_description',
        'pathology_report_template_id', 'confirmed_by', 'confirmed_at', 'corrected_from_sent_at',
        'created_by', 'updated_by', 'created_at', 'updated_at', 'id',
    ];

    /**
     * @param string $kind   pathology|usg|cardiology|non_pathology
     * @param string $action SAVE|CONFIRM
     */
    public function send(string $kind, Model $finding, string $action): void
    {
        try {

            $user = Auth::user();

            if (!$user) {
                return;
            }

            if (empty($user->email)) {
                Log::warning("Report entry receipt not sent: user #{$user->id} has no email (report {$kind} #{$finding->getKey()}, {$action}).");
                return;
            }

            $fields = [];

            foreach ($finding->only($finding->getFillable()) as $key => $value) {

                if (in_array($key, self::SKIP_FIELDS, true) || $value === null || $value === '' || $value === []) {
                    continue;
                }

                if ($value instanceof \DateTimeInterface) {
                    $value = $value->format('d-m-Y');
                }

                $fields[$key] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
            }

            if ($kind === 'pathology') {

                $items = DB::table('pathology_report_finding_items as pfi')
                    ->join('invoice_details as d', 'd.id', '=', 'pfi.invoice_detail_id')
                    ->where('pfi.pathology_report_finding_id', $finding->getKey())
                    ->orderBy('d.id')
                    ->pluck('d.item_description')
                    ->implode(', ');

            } else {

                $items = (string) ($finding->item_description ?? '');
            }

            $patient = DB::table('invoices')->where('invoice_no', $finding->invoice_no)->value('patient_name');

            $at = now();

            $payload = [
                'kind' => $kind,
                'kind_label' => self::LABELS[$kind] ?? $kind,
                'action' => $action,
                'report_id' => $finding->getKey(),
                'invoice_no' => $finding->invoice_no,
                'patient' => $patient,
                'items' => $items,
                'employee' => $user->name,
                'at' => $at->format('d-m-Y H:i:s'),
                'fields' => $fields,
            ];

            // Fingerprint of exactly what was recorded -- re-computable from
            // the audit log's stored data if the content is ever disputed.
            $payload['hash'] = hash('sha256', json_encode([
                $kind, $finding->getKey(), $action, $user->id, $at->toIso8601String(), $fields,
            ], JSON_UNESCAPED_UNICODE));

            $to = $user->email;

            app()->terminating(function () use ($to, $payload) {

                try {
                    // Operator only -- no CC.
                    Mail::to($to)->send(new ReportEntryReceiptMail($payload));
                } catch (\Throwable $e) {
                    Log::error('Failed to send report entry receipt email: ' . $e->getMessage());
                }
            });

        } catch (\Throwable $e) {

            Log::error('Failed to prepare report entry receipt email: ' . $e->getMessage());
        }
    }
}
