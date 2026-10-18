<?php

namespace App\Console\Commands;

use App\Http\Controllers\XrayReportUploadController;
use Illuminate\Console\Command;

/**
 * Fires every X-Ray report upload whose 2-hour delay (set in
 * XrayReportUploadController::upload(), reset on every replace) has now
 * elapsed. See Kernel::schedule() for how often this actually runs --
 * needs the production cPanel cron entry for `php artisan schedule:run`
 * every minute to fire at all.
 */
class SendDueXrayWhatsapp extends Command
{
    protected $signature = 'xray:send-due-whatsapp';

    protected $description = 'Send X-Ray report WhatsApp messages whose 2-hour delay has elapsed';

    public function handle(XrayReportUploadController $controller): int
    {
        $controller->processDueWhatsapp(app(\App\Services\WatiService::class), app(\App\Services\AuditService::class));

        return self::SUCCESS;
    }
}
