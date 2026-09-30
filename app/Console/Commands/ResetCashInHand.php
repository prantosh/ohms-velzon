<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetCashInHand extends Command
{
    protected $signature = 'cash-in-hand:reset';

    protected $description = 'Zero out every user\'s cash_in_hand at the start of a new day';

    public function handle(): int
    {
        // Redundant with CashInHandService::ensureFreshForToday() (which
        // self-heals a stale balance the moment it's read or touched
        // regardless of whether this command ever actually runs -- see
        // that service's class doc), kept as a belt-and-suspenders sweep
        // for any user who never triggers a read/write that day.
        $affected = DB::table('users')
            ->where('cash_in_hand', '!=', 0)
            ->update([
                'cash_in_hand' => 0,
                'cash_in_hand_date' => now()->toDateString(),
            ]);

        $this->info("Reset cash_in_hand to 0 for {$affected} user(s).");

        return self::SUCCESS;
    }
}
