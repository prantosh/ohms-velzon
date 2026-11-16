<?php

namespace App\Services;

use Carbon\Carbon;
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
 * REFUND is the one exception to the payment_mode gate: a cancelled
 * invoice's refund is always physically handed back in cash by whoever
 * cancels it, regardless of how the original payment was collected -- the
 * payment_mode passed in for a REFUND reflects the ORIGINAL payment's mode,
 * not how the refund itself was disbursed, so it is not a reliable signal
 * for whether cash actually moved here. ItemWiseReportController and
 * MonthlyReconciliationReportController already encode this same rule
 * (their refund buckets are unconditional); CashLedgerService,
 * UserReconciliationService, and DailyTransactionReportController had the
 * same payment_mode-gated bug this fixes and were corrected to match.
 *
 * Only one place in the app reverses an already-recorded row after the fact
 * (DoctorSettlementController::cancel() flips a PAYMENT row to CANCELLED) --
 * call reverse() there with that row's own stored values to undo exactly
 * what apply() did for it originally.
 *
 * END OF DAY. Every day starts from 0. At 23:00 (cash-in-hand:close-day) --
 * or lazily the first time anyone touches/views the balance on a later day
 * (ensureFreshForToday(), so correctness does not depend on cron) -- the
 * day's closing balance is written to cash_day_closings:
 *   - positive  => assumed handed to the accountant (deposited);
 *   - negative  => a DEFICIT (cash paid out exceeded cash collected). It is
 *                  NOT netted against the next day's collections -- that
 *                  would let the user keep tomorrow's cash without anyone
 *                  verifying the payout. It stays as a pending deficit until
 *                  an Admin clears it with a CashAdjustment (see
 *                  CashAdjustmentController), dated to the deficit day.
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
        if (!$userId || $amount == 0.0) {
            return;
        }

        // See the class doc comment -- REFUND always affects cash-in-hand
        // regardless of payment_mode; RECEIVED/PAYMENT only when it's
        // actually Cash.
        if ($transactionType !== 'REFUND' && $paymentMode !== 'Cash') {
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
     * Rolls a balance left over from an earlier day over to today: the old
     * day's closing is recorded in cash_day_closings (a deficit stays there
     * as pending) and the running balance restarts at 0. A single
     * conditional UPDATE (a concurrent second call matches zero rows once
     * the date is today), so no locking is needed.
     */
    public function ensureFreshForToday(int $userId): void
    {
        $today = now()->toDateString();

        $stale = DB::table('users')
            ->where('id', $userId)
            ->where(function ($query) use ($today) {
                $query->whereNull('cash_in_hand_date')
                    ->orWhere('cash_in_hand_date', '!=', $today);
            })
            ->first(['cash_in_hand', 'cash_in_hand_date']);

        if (!$stale) {
            return;
        }

        $affected = DB::table('users')
            ->where('id', $userId)
            ->where(function ($query) use ($today) {
                $query->whereNull('cash_in_hand_date')
                    ->orWhere('cash_in_hand_date', '!=', $today);
            })
            ->update([
                'cash_in_hand' => 0,
                'cash_in_hand_date' => $today,
            ]);

        if ($affected && $stale->cash_in_hand_date && (float) $stale->cash_in_hand != 0.0) {
            $this->addToClosing($userId, $stale->cash_in_hand_date, (float) $stale->cash_in_hand);
        }
    }

    /**
     * End-of-day close (scheduled 23:00). Records every user's closing for
     * today and restarts their running balance at 0 (a positive balance is
     * assumed deposited; a negative one is a pending deficit in
     * cash_day_closings). Anything recorded after the close, before
     * midnight, is added to the same day's closing when the balance rolls
     * over. Returns [deposited users, deficit users].
     */
    public function closeDay(): array
    {
        $today = now()->toDateString();
        $deposited = 0;
        $deficits = 0;

        $userIds = DB::table('users')->where('cash_in_hand', '!=', 0)->pluck('id');

        foreach ($userIds as $userId) {

            // Brings a balance left over from an earlier, never-touched day
            // over to today first (recording that earlier day's closing).
            $this->ensureFreshForToday((int) $userId);

            $balance = (float) DB::table('users')->where('id', $userId)->value('cash_in_hand');

            if ($balance == 0.0) {
                continue;
            }

            $this->addToClosing((int) $userId, $today, $balance);

            DB::table('users')->where('id', $userId)->update(['cash_in_hand' => 0]);

            $balance > 0 ? $deposited++ : $deficits++;
        }

        return [$deposited, $deficits];
    }

    /**
     * Adds $delta to a day's recorded closing balance (creating the row if
     * needed) and re-derives deposited / carried_forward from it.
     */
    private function addToClosing(int $userId, string $date, float $delta): void
    {
        $date = substr($date, 0, 10);

        $existing = (float) (DB::table('cash_day_closings')
            ->where('user_id', $userId)
            ->where('closing_date', $date)
            ->value('closing_balance') ?? 0);

        $closing = round($existing + $delta, 2);

        DB::table('cash_day_closings')->updateOrInsert(
            ['user_id' => $userId, 'closing_date' => $date],
            [
                'closing_balance' => $closing,
                'deposited_amount' => $closing > 0 ? $closing : 0,
                'carried_forward' => $closing < 0 ? $closing : 0,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * The day's cash position as it stands now: what was recorded at close
     * plus, for today, whatever the live running balance holds.
     */
    public function closingFor(int $userId, string $date): float
    {
        $date = substr($date, 0, 10);

        $recorded = (float) (DB::table('cash_day_closings')
            ->where('user_id', $userId)
            ->where('closing_date', $date)
            ->value('closing_balance') ?? 0);

        if ($date === now()->toDateString()) {
            $this->ensureFreshForToday($userId);
            $recorded += (float) DB::table('users')->where('id', $userId)->value('cash_in_hand');
        } else {
            // A past day whose balance has not been rolled over yet.
            $user = DB::table('users')->where('id', $userId)->first(['cash_in_hand', 'cash_in_hand_date']);
            if ($user && $user->cash_in_hand_date && substr($user->cash_in_hand_date, 0, 10) === $date) {
                $recorded += (float) $user->cash_in_hand;
            }
        }

        return round($recorded, 2);
    }

    /**
     * Unresolved deficit (positive number) for one user on one day.
     */
    public function deficitFor(int $userId, string $date): float
    {
        return max(0.0, -$this->closingFor($userId, $date));
    }

    /**
     * Applies an approved adjustment (+$amount) to the deficit of
     * $date. Today's adjustment goes into the live running balance; an
     * earlier day's goes into that day's recorded closing. $direction -1
     * reverses it (cancellation).
     */
    public function applyAdjustment(int $userId, string $date, float $amount, int $direction = 1): void
    {
        $date = substr($date, 0, 10);
        $delta = $direction * $amount;

        $this->ensureFreshForToday($userId);

        if ($date === now()->toDateString()) {
            DB::table('users')->where('id', $userId)->increment('cash_in_hand', $delta);
            return;
        }

        $this->addToClosing($userId, $date, $delta);
    }

    /**
     * Total unresolved deficit from days BEFORE $date (a negative number,
     * or 0) -- shown on the slip for information only; it never reduces
     * that day's deposit.
     */
    public function pendingDeficitBefore(int $userId, string $date): float
    {
        return round((float) DB::table('cash_day_closings')
            ->where('user_id', $userId)
            ->where('closing_date', '<', Carbon::parse($date)->toDateString())
            ->where('carried_forward', '<', 0)
            ->sum('carried_forward'), 2);
    }
}
