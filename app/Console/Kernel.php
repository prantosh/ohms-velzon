<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();

        // See InvoicesBackupService -- requires the cPanel cron entry
        // `php <app-root>/artisan schedule:run` firing every minute for this
        // to actually run on production (no other scheduled task exists yet,
        // so that cron entry likely still needs to be added there).
        // Prune runs only onSuccess() so a backup failure can never lead to
        // deleting originals that didn't actually get backed up that week --
        // pruneOriginals() also independently re-checks this per file.
        $schedule->command('invoices:backup')->weekly()->onSuccess(function () {
            $this->call('invoices:prune');
        });
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
