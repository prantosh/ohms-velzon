<?php

namespace App\Console\Commands;

use App\Services\CashInHandService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetCashInHand extends Command
{
    protected $signature = 'cash-in-hand:reset';

    protected $description = 'Roll every user\'s leftover cash_in_hand from an earlier day over to today (deficits are recorded as pending, not lost)';

    public function handle(CashInHandService $service): int
    {
        // Manual sweep for any user whose balance still belongs to an
        // earlier day. Uses the same roll-over as the lazy path, so a
        // negative balance is recorded in cash_day_closings as a pending
        // deficit before it restarts at 0.
        $today = now()->toDateString();

        $userIds = DB::table('users')
            ->where('cash_in_hand', '!=', 0)
            ->where(function ($q) use ($today) {
                $q->whereNull('cash_in_hand_date')->orWhere('cash_in_hand_date', '!=', $today);
            })
            ->pluck('id');

        foreach ($userIds as $userId) {
            $service->ensureFreshForToday((int) $userId);
        }

        $this->info("Rolled over cash_in_hand for {$userIds->count()} user(s).");

        return self::SUCCESS;
    }
}
