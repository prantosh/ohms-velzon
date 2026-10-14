<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\ReportCorrectionNotice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * When may an Admin delete a saved/confirmed report on the per-line report
 * screens (USG, Cardiology, Non-Pathology) or Pathology? Admin only -- that
 * is the only rule now. A confirmed report may be deleted even after it has
 * already reached the patient via WhatsApp; lastSentAt() below is used by
 * each controller's destroyFinding() purely to decide whether (and with
 * what timestamp) to leave a report_correction_notices trail (see
 * recordCorrection()), not to block the delete.
 */
class ReportDeletionGuard
{
    public static function isAdmin(): bool
    {
        return optional(Auth::user())->role === 'Admin';
    }

    /**
     * @return ?string reason it can NOT be deleted, or null when it can
     */
    public static function denyReason(): ?string
    {
        if (!self::isAdmin()) {
            return 'Only an Admin can delete a report.';
        }

        return null;
    }

    /**
     * When this confirmed report was actually sent to the patient via
     * WhatsApp (the real send time, for the "previous report sent at X"
     * footnote), or null if it never was. Auto-send fires within moments of
     * confirm() in every module, so "created_at >= confirmedAt - 5s"
     * reliably matches the send that followed THIS confirmation rather
     * than some unrelated earlier one; the latest matching send is used.
     */
    public static function lastSentAt(Invoice $invoice, string $messageType, $confirmedAt): ?\Carbon\Carbon
    {
        if (!$confirmedAt) {
            return null;
        }

        $sentAt = DB::table('whatsapp_message_logs')
            ->where('invoice_no', $invoice->invoice_no)
            ->where('message_type', $messageType)
            ->where('status', 'SENT')
            ->where('created_at', '>=', \Carbon\Carbon::parse($confirmedAt)->subSeconds(5))
            ->orderByDesc('created_at')
            ->value('created_at');

        return $sentAt ? \Carbon\Carbon::parse($sentAt) : null;
    }

    /**
     * Leaves a trail, keyed by billed line, so the NEXT report written for
     * this same test can carry a "please ignore the previous report sent
     * at X" footnote (consumed once -- see each controller's store()).
     * One notice per line; deleting a second already-corrected report
     * before a replacement is ever written just replaces the stored time
     * rather than stacking two footnotes.
     */
    public static function recordCorrection(int $invoiceDetailId, $sentAt): void
    {
        ReportCorrectionNotice::updateOrCreate(
            ['invoice_detail_id' => $invoiceDetailId],
            ['previous_sent_at' => $sentAt]
        );
    }
}
