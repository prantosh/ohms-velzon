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
        // to actually run on production.
        // Checked daily; the command itself only acts on the 3-day grid
        // anchored at 06/10/2026 (archives the oldest 3 of the 6 days held)
        // -- so a missed day or month boundary can't skew the rhythm like a cron
        // day-of-month step would. Originals are deleted only after the
        // archive is verified. invoices:backup / invoices:prune stay
        // available for manual use.
        $schedule->command('invoices:archive')->dailyAt('02:00');

        // See CashInHandService -- cash_in_hand is a running balance that
        // only moves via actual transactions during the day; this is the
        // one place it gets reset, back to 0 for every user at the start of
        // each new day.
        $schedule->command('cash-in-hand:reset')->dailyAt('00:01');

        // Saves each user's reconciliation summary of the month that just ended (see
        // UserReconciliationService). The dashboard also fills any missing month when it
        // is opened, so a missed run leaves no gap.
        $schedule->command('reconciliation:snapshot')->monthlyOn(1, '00:30');

        // See XrayReportUploadController::upload()/processDueWhatsapp() --
        // unlike every other report type (sent immediately), X-Ray's
        // WhatsApp send is deliberately delayed 2 hours from the latest
        // upload/replace. This picks up whatever's due every 5 minutes.
        $schedule->command('xray:send-due-whatsapp')->everyFiveMinutes();
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
