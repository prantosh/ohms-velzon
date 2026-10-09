<?php

namespace App\Services;

use App\Http\Controllers\CardiologyReportController;
use App\Http\Controllers\NonPathologyReportController;
use App\Http\Controllers\PathologyReportController;
use App\Http\Controllers\TestResultEntryController;
use App\Http\Controllers\UsgReportController;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A diagnostic report is not WhatsApp'd to the patient while any payment is
 * still due on its invoice. The automatic send that would have fired on
 * confirm is parked as a HELD_DUE row in whatsapp_message_logs instead, and
 * released (sent for real) the moment the invoice's due amount reaches 0 --
 * see DiagnosticInvoiceController::update(), which calls releaseAll().
 *
 * For the per-finding report types (Cardiology/USG/Non-Pathology) the held
 * row's `response` carries {"finding_id": N} so release knows which
 * finding to send; Pathology (combined) and Test Report are invoice-level
 * and need no id.
 */
class ReportWhatsappDueGate
{
    public const HELD = 'HELD_DUE';

    private const RELEASED = 'RELEASED';

    public static function dueAmount(string $invoiceNo): float
    {
        return (float) DB::table('invoices')->where('invoice_no', $invoiceNo)->value('due_amount');
    }

    /**
     * Message shown when staff try a manual "Send WhatsApp" on an invoice
     * with a payment due; null when sending is allowed. Admin may still
     * send manually as a deliberate override.
     */
    public static function manualBlockMessage(string $invoiceNo): ?string
    {
        $due = self::dueAmount($invoiceNo);

        if ($due <= 0 || optional(Auth::user())->role === 'Admin') {
            return null;
        }

        return 'Payment of ' . number_format($due, 2) . ' is still due on this invoice. The report will be sent on WhatsApp automatically once the payment is cleared.';
    }

    public static function hold(string $messageType, Invoice $invoice, ?int $findingId = null): void
    {
        $payload = $findingId ? json_encode(['finding_id' => $findingId]) : null;

        $alreadyHeld = DB::table('whatsapp_message_logs')
            ->where('invoice_no', $invoice->invoice_no)
            ->where('message_type', $messageType)
            ->where('status', self::HELD)
            ->where('response', $payload)
            ->exists();

        if ($alreadyHeld) {
            return;
        }

        DB::table('whatsapp_message_logs')->insert([
            'invoice_no' => $invoice->invoice_no,
            'mobile_no' => $invoice->patient_mobile_no,
            'patient_name' => $invoice->patient_name,
            'message_type' => $messageType,
            'status' => self::HELD,
            'response' => $payload,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Held rows for this invoice/type, flipped to RELEASED so a second
     * release call can't send them twice. Returns each row's finding id
     * (null for invoice-level types).
     *
     * @return array<int, ?int>
     */
    public static function takeHeld(string $invoiceNo, string $messageType): array
    {
        $rows = DB::table('whatsapp_message_logs')
            ->where('invoice_no', $invoiceNo)
            ->where('message_type', $messageType)
            ->where('status', self::HELD)
            ->get();

        $ids = [];

        foreach ($rows as $row) {

            DB::table('whatsapp_message_logs')
                ->where('id', $row->id)
                ->where('status', self::HELD)
                ->update(['status' => self::RELEASED, 'updated_at' => now()]);

            $ids[] = json_decode((string) $row->response, true)['finding_id'] ?? null;
        }

        return $ids;
    }

    /**
     * Called once a payment has been recorded. No-op while anything is
     * still due. A failure here must never undo the payment, so it only
     * logs.
     */
    public static function releaseAll(string $invoiceNo): void
    {
        if (self::dueAmount($invoiceNo) > 0) {
            return;
        }

        foreach ([
            PathologyReportController::class,
            CardiologyReportController::class,
            UsgReportController::class,
            NonPathologyReportController::class,
            TestResultEntryController::class,
        ] as $controller) {

            try {

                app($controller)->releaseHeldWhatsapp($invoiceNo);

            } catch (\Throwable $e) {

                Log::error('Held report WhatsApp release failed (' . $controller . ') for ' . $invoiceNo . ': ' . $e->getMessage());
            }
        }
    }
}
