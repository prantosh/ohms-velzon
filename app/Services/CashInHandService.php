<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Maintains users.cash_in_hand as a running balance, updated incrementally
 * at the moment each cash movement is recorded on daily_transactions --
 * never recomputed by summing history, so reading it (e.g. on the Home
 * dashboard) is a single column read, not an aggregation query.
 *
 * Call apply() right after every daily_transactions insert that could be a
 * cash movement, passing the exact same created_by/transaction_type/
 * payment_mode/amount used for that row. Non-cash payment modes and
 * transaction types this doesn't understand are silently ignored, so it's
 * always safe to call unconditionally rather than guarding every call site.
 *
 * Only one place in the app reverses an already-recorded row after the fact
 * (DoctorSettlementController::cancel() flips a PAYMENT row to CANCELLED) --
 * call reverse() there with that row's own stored values to undo exactly
 * what apply() did for it originally.
 */
class CashInHandService
{
    // RECEIVED adds to the collector's cash in hand; REFUND and PAYMENT
    // (a doctor settlement paid out in cash) subtract from it. Any other
    // transaction_type is a no-op.
    private const SIGN_BY_TYPE = [
        'RECEIVED' => 1,
        'REFUND' => -1,
        'PAYMENT' => -1,
    ];

    public function apply(?int $userId, string $transactionType, ?string $paymentMode, float $amount): void
    {
        $this->adjust($userId, $transactionType, $paymentMode, $amount, 1);
    }

    public function reverse(?int $userId, string $transactionType, ?string $paymentMode, float $amount): void
    {
        $this->adjust($userId, $transactionType, $paymentMode, $amount, -1);
    }

    private function adjust(?int $userId, string $transactionType, ?string $paymentMode, float $amount, int $direction): void
    {
        if (!$userId || $paymentMode !== 'Cash' || $amount == 0.0) {
            return;
        }

        $sign = self::SIGN_BY_TYPE[$transactionType] ?? 0;

        if ($sign === 0) {
            return;
        }

        DB::table('users')->where('id', $userId)->increment('cash_in_hand', $direction * $sign * $amount);
    }
}
