<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Cancelling an invoice means its doctor's share is never going to be paid.
 * Its doctor_payables rows used to stay PENDING, so every screen that lists or
 * adds up doctor payables had to remember to check the invoice's cancelled flag
 * (and some, like the Reporting Group Summary, did not). Marking the unpaid
 * rows CANCELLED at cancellation time makes them disappear from all of them.
 *
 * A payable that was ALREADY paid out (PAID) is left alone -- that money did
 * leave, and the settlement records still refer to it.
 */
class DoctorPayableCancellation
{
    /** @return int number of payable rows marked CANCELLED */
    public static function cancelUnpaidForInvoice($invoiceId): int
    {
        if (empty($invoiceId)) {
            return 0;
        }

        return DB::table('doctor_payables')
            ->where('invoice_id', $invoiceId)
            ->whereIn('payment_status', ['PENDING', 'APPROVED'])
            ->update([
                'payment_status' => 'CANCELLED',
                'updated_at' => now(),
            ]);
    }
}
