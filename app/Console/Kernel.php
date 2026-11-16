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

        // See CashInHandService::closeDay() -- at 23:00 every positive
        // cash_in_hand is assumed handed over to the accountant; a negative
        // one is recorded as a pending deficit that an Admin clears with a
        // Cash Adjustment (never netted against the next day's cash).
        // ensureFreshForToday() does the same lazily, so a missed run loses
        // nothing. cash-in-hand:reset remains for manual use.
        $schedule->command('cash-in-hand:close-day')->dailyAt('23:00');

        // Saves each user's reconciliation summary of the month that just ended (see
        // UserReconciliationService). The dashboard also fills any missing month when it
        // is opened, so a missed run leaves no gap.
        $schedule->command('reconciliation:snapshot')->monthlyOn(1, '00:30');

        // See XrayReportUploadController::upload()/processDueWhatsapp() --
        // unlike every other report type (sent immediately), X-Ray's
        // WhatsApp send is deliberately delayed 2 hours from the latest
        // upload/replace. This picks up whatever's due every 5 minutes.
        $schedule->command('xray:send-due-whatsapp')->everyFiveMinutes();

        // Safety net for the due-payment report hold -- sends held reports
        // whose invoice is now fully paid but whose release never ran in
        // the payment request itself (see ReportWhatsappDueGate::releaseAllPaid()).
        $schedule->command('reports:release-held-whatsapp')->everyFiveMinutes()->withoutOverlapping();
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
