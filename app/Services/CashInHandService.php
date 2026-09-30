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
 *
 * The balance is meant to reset to 0 at the start of each day (see
 * cash-in-hand:reset, scheduled in Console\Kernel), but that depends on the
 * host's cron actually invoking `artisan schedule:run` every minute --
 * fragile on shared hosting with no way to verify it's configured
 * correctly. ensureFreshForToday() makes correctness NOT depend on that:
 * called from both the write path here and the Home dashboard's read path,
 * it resets a stale balance the moment anyone touches or views it, so a
 * misconfigured/missing cron can no longer cause a stuck non-zero balance
 * to linger indefinitely.
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

        $this->ensureFreshForToday($userId);

        DB::table('users')->where('id', $userId)->increment('cash_in_hand', $direction * $sign * $amount);
    }

    /**
     * Resets this user's balance to 0 if it's carrying over from a previous
     * day -- a single conditional UPDATE (atomic: a second concurrent call
     * simply matches zero rows once the first has already updated the
     * date), not a read-then-write, so no locking is needed.
     */
    public function ensureFreshForToday(int $userId): void
    {
        $today = now()->toDateString();

        DB::table('users')
            ->where('id', $userId)
            ->where(function ($query) use ($today) {
                $query->whereNull('cash_in_hand_date')
                    ->orWhere('cash_in_hand_date', '!=', $today);
            })
            ->update([
                'cash_in_hand' => 0,
                'cash_in_hand_date' => $today,
            ]);
    }
}
