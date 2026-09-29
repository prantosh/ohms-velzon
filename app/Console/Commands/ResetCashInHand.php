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
        $affected = DB::table('users')->where('cash_in_hand', '!=', 0)->update(['cash_in_hand' => 0]);

        $this->info("Reset cash_in_hand to 0 for {$affected} user(s).");

        return self::SUCCESS;
    }
}
