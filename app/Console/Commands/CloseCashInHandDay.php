<?php

namespace App\Console\Commands;

use App\Services\CashInHandService;
use Illuminate\Console\Command;

class CloseCashInHandDay extends Command
{
    protected $signature = 'cash-in-hand:close-day';

    protected $description = 'End-of-day cash close: positive balances are treated as deposited, negative balances are recorded as pending deficits for an Admin to adjust';

    public function handle(CashInHandService $service): int
    {
        [$deposited, $deficits] = $service->closeDay();

        $this->info("Cash day closed: {$deposited} user(s) deposited, {$deficits} user(s) with a pending deficit.");

        return self::SUCCESS;
    }
}
