<?php

namespace App\Console\Commands;

use App\Services\InvoicesBackupService;
use Illuminate\Console\Command;

class PruneInvoices extends Command
{
    protected $signature = 'invoices:prune {--days=}';

    protected $description = 'Delete invoice/report PDFs from public/invoices once they are old enough and already backed up';

    public function handle(InvoicesBackupService $service): int
    {
        $days = $this->option('days')
            ? (int) $this->option('days')
            : InvoicesBackupService::PRUNE_ORIGINALS_AFTER_DAYS;

        $result = $service->pruneOriginals($days);

        $this->info("Deleted {$result['deleted']} file(s) older than {$days} days.");

        if ($result['skipped'] > 0) {
            $this->warn("Skipped {$result['skipped']} file(s) older than {$days} days -- no successful backup covers them yet.");
        }

        return self::SUCCESS;
    }
}
