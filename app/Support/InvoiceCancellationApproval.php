<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\InvoiceCancellationPermission;
use Carbon\Carbon;

class InvoiceCancellationApproval
{
    public const NOT_TODAY_MESSAGE = 'This invoice was not created today. A Supervisor or Admin must first grant cancellation permission for it via the Cancellation Permission dashboard.';

    public static function isToday(Invoice $invoice): bool
    {
        return $invoice->invoice_date
            ? Carbon::parse($invoice->invoice_date)->isToday()
            : false;
    }

    /**
     * Resolves who approved this cancellation.
     * Returns null when the invoice was created today (no approval needed).
     * Returns the approving user's id when a Supervisor/Admin has pre-granted permission.
     * Returns false when the invoice is not from today and no permission has been granted.
     */
    public static function resolveApprover(Invoice $invoice): int|false|null
    {
        if (self::isToday($invoice)) {
            return null;
        }

        $permission = InvoiceCancellationPermission::where('invoice_id', $invoice->id)->first();

        return $permission ? $permission->granted_by : false;
    }
}
