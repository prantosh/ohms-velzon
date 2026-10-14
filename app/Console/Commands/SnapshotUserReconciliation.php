<?php

namespace App\Console\Commands;

use App\Services\UserReconciliationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Saves the per-user monthly reconciliation of every completed month that is not
 * saved yet -- scheduled for the 1st of each month (so on 1st November the month
 * of October is saved). The dashboard also fills any missing month itself when it
 * is opened, so a missed run (e.g. no cron on the host) never leaves a gap.
 *
 *   php artisan reconciliation:snapshot                 saves any missing completed month
 *   php artisan reconciliation:snapshot --month=2026-10  rebuilds that month (completed months only)
 */
class SnapshotUserReconciliation extends Command
{
    protected $signature = 'reconciliation:snapshot {--month= : YYYY-MM to rebuild even if already saved}';

    protected $description = 'Save the per-user monthly reconciliation summary of completed months';

    public function handle(UserReconciliationService $service): int
    {
        if ($this->option('month')) {

            $start = Carbon::createFromFormat('Y-m-d', $this->option('month') . '-01')->startOfDay();

            if (!$start->lt(now()->startOfMonth())) {
                $this->error('Only completed months can be saved.');
                return self::FAILURE;
            }

            $count = $service->generateMonth($start);
            $this->info("Saved {$count} user row(s) for " . $start->format('F Y') . '.');

            return self::SUCCESS;
        }

        $months = $service->ensureSnapshots();
        $this->info("Saved {$months} missing month(s).");

        return self::SUCCESS;
    }
}
