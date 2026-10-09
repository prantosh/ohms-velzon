<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\InvoiceCancellationPermission;
use App\Models\User;
use Carbon\Carbon;

/**
 * Single shared rule for every "Cancel"/"Delete" button on any invoice type
 * in the app: an invoice created today can be cancelled by anyone; an older
 * one needs a Supervisor/Admin to have granted a cancellation request for
 * it first (see InvoiceCancellationController::requestPermission()), and
 * even then only the user who originally requested it may actually cancel.
 *
 * Every cancellation entry point (Invoice Cancellation page, and the
 * per-invoice-type Delete/Cancel buttons on Diagnostic, Doctor Visit,
 * Ambulance, Equipment, and Income) must call check() rather than
 * re-implementing this -- this class used to only expose resolveApprover(),
 * which several of those call sites used in a way that let a merely
 * PENDING (not yet granted) request through, and none of them checked that
 * the canceller was actually the requester. That drift is exactly what
 * check() exists to prevent from happening again.
 */
class InvoiceCancellationApproval
{
    public static function isToday(Invoice $invoice): bool
    {
        return $invoice->invoice_date
            ? Carbon::parse($invoice->invoice_date)->isToday()
            : false;
    }

    /**
     * @return array{allowed: bool, approver_id: ?int, message: ?string}
     */
    public static function check(Invoice $invoice, int $currentUserId): array
    {
        // Checked before even the same-day shortcut below -- a report
        // already represents real diagnostic work, so it blocks
        // cancellation regardless of how recently the invoice was raised.
        // Admin alone may override this (the same-day/permission rules
        // below still apply to them like anyone else).
        if (
            DiagnosticReportGuard::anyReportExists($invoice->invoice_no)
            && optional(User::find($currentUserId))->role !== 'Admin'
        ) {

            return [
                'allowed' => false,
                'approver_id' => null,
                'message' => 'This invoice already has a diagnostic report started or completed against it and can no longer be cancelled.',
            ];
        }

        if (self::isToday($invoice)) {
            return ['allowed' => true, 'approver_id' => null, 'message' => null];
        }

        $permission = InvoiceCancellationPermission::with('requestedByUser')
            ->where('invoice_id', $invoice->id)
            ->first();

        if (!$permission) {
            return [
                'allowed' => false,
                'approver_id' => null,
                'message' => 'This invoice was not created today. Submit a cancellation request with your reason first, from the Invoice Cancellation page.',
            ];
        }

        if ($permission->status !== InvoiceCancellationPermission::STATUS_GRANTED) {

            $isOwnRequest = (int) $permission->requested_by === $currentUserId;

            return [
                'allowed' => false,
                'approver_id' => null,
                'message' => $isOwnRequest
                    ? 'Your cancellation request for this invoice is still pending Supervisor/Admin approval.'
                    : 'A cancellation request for this invoice by '
                        . optional($permission->requestedByUser)->name
                        . ' is still pending Supervisor/Admin approval.',
            ];
        }

        if ((int) $permission->requested_by !== $currentUserId) {

            return [
                'allowed' => false,
                'approver_id' => null,
                'message' => 'Cancellation permission for this invoice was granted to '
                    . optional($permission->requestedByUser)->name
                    . '. Only they can cancel it.',
            ];
        }

        return ['allowed' => true, 'approver_id' => $permission->granted_by, 'message' => null];
    }
}
