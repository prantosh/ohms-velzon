<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * When may an Admin delete a saved/confirmed report on the per-line report
 * screens (USG, Cardiology, Non-Pathology)? Pathology has its own variant in
 * PathologyReportController::destroyFinding() because its combined WhatsApp
 * only goes out once every report is confirmed; these modules send each
 * report on its own as soon as it is confirmed, so the rules are:
 *
 *   - Admin only.
 *   - Not once every report of this screen's items on the invoice is
 *     confirmed (the same "everything is final" rule as Pathology).
 *   - A confirmed report that has already been WhatsApp'd to the patient is
 *     never deleted (the patient holds it), regardless of the above.
 *   - A saved (unconfirmed) report can always go while the first rule holds.
 */
class ReportDeletionGuard
{
    /** @var array<string, bool> per invoice+kind cache for one request */
    private static array $moduleLocked = [];

    public static function isAdmin(): bool
    {
        return optional(Auth::user())->role === 'Admin';
    }

    /**
     * @param string  $kind        InvoiceReportItemsService::KIND_* of the screen
     * @param ?string $confirmedAt the report's confirmed_at (null while only saved)
     * @return ?string reason it can NOT be deleted, or null when it can
     */
    public static function denyReason(Invoice $invoice, string $kind, string $messageType, $confirmedAt): ?string
    {
        if (!self::isAdmin()) {
            return 'Only an Admin can delete a report.';
        }

        $key = $invoice->id . '|' . $kind;

        if (!isset(self::$moduleLocked[$key])) {

            $items = collect(app(InvoiceReportItemsService::class)->forInvoice($invoice))->where('kind', $kind);

            self::$moduleLocked[$key] = $items->isNotEmpty() && $items->every(fn ($i) => $i['confirmed']);
        }

        if (self::$moduleLocked[$key]) {
            return 'Every report on this invoice for these tests is already confirmed, so it is locked. A report can only be deleted until then.';
        }

        if ($confirmedAt) {

            $sent = DB::table('whatsapp_message_logs')
                ->where('invoice_no', $invoice->invoice_no)
                ->where('message_type', $messageType)
                ->where('status', 'SENT')
                ->where('created_at', '>=', \Carbon\Carbon::parse($confirmedAt)->subSeconds(5))
                ->exists();

            if ($sent) {
                return 'This confirmed report has already been sent to the patient on WhatsApp, so it cannot be deleted.';
            }
        }

        return null;
    }

    /** Forget the per-request cache (used after a delete changes the answer). */
    public static function reset(): void
    {
        self::$moduleLocked = [];
    }
}
