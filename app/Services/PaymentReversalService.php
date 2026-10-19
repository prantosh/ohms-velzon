<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reverses the single most recent RECEIVED daily_transactions row for an
 * invoice -- the "a payment turned out to have failed after the fact" case
 * (UPI/card bounce, discovered later; or a cash payment that has to be
 * undone for any other reason). Pure find+validate+cancel+reverse mechanics,
 * invoice-type-agnostic -- it never touches the invoices table itself, since
 * how paid_amount/due_amount/status/phase fields recompute genuinely
 * differs per invoice type (Diagnostic's multi-phase due payments vs every
 * other type's single payment) and that recompute already lives in each
 * controller. Each caller is expected to run this inside its own
 * DB::transaction() with the Invoice row already locked -- this does not
 * open its own transaction, same convention CashInHandService follows.
 *
 * "Most recent" rather than an explicit row id: for every invoice type
 * except Diagnostic there is only ever one RECEIVED row per invoice, so
 * "latest" and "the only one" are the same thing. For Diagnostic, only the
 * most recent phase may ever be reversed (never reaching back into history)
 * -- and that is always the highest-id ACTIVE RECEIVED row for the invoice,
 * with no need to match on remarks text. Calling this again after a
 * successful reversal naturally finds and reverses the next-most-recent
 * phase, so unwinding phase 2 then later phase 1 falls out for free.
 */
class PaymentReversalService
{
    public function reverseLatestReceived(Invoice $invoice, float $expectedAmount): object
    {
        $row = DB::table('daily_transactions')
            ->where('invoice_reference', $invoice->invoice_no)
            ->where('transaction_type', 'RECEIVED')
            ->where('status', 'ACTIVE')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if (!$row) {
            throw ValidationException::withMessages([
                'payment' => ['No active payment found to reverse on this invoice.'],
            ]);
        }

        if (abs((float) $row->received_amount - $expectedAmount) > 0.01) {
            throw ValidationException::withMessages([
                'payment' => [
                    'The most recent payment on record (₹' . number_format((float) $row->received_amount, 2)
                    . ') does not match the amount expected for this reversal (₹' . number_format($expectedAmount, 2)
                    . ') -- stopping rather than risk reversing the wrong transaction.',
                ],
            ]);
        }

        DB::table('daily_transactions')
            ->where('id', $row->id)
            ->update([
                'status' => 'CANCELLED',
                'remarks' => trim(($row->remarks ? $row->remarks . ' | ' : '') . 'Marked failed by ' . (Auth::user()->name ?? 'system')),
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);

        app(CashInHandService::class)->reverse(
            $row->created_by,
            'RECEIVED',
            $row->payment_mode,
            (float) $row->received_amount
        );

        return $row;
    }
}
