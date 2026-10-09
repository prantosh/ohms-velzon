<?php

namespace App\Console\Commands;

use App\Services\InvoicesBackupService;
use Illuminate\Console\Command;

class ArchiveInvoices extends Command
{
    protected $signature = 'invoices:archive {--force : Archive as if today were a run day}';

    protected $description = 'Zip the oldest 3 days of public/invoices (every 3rd day) into storage/app/backups, then remove them from the folder';

    public function handle(InvoicesBackupService $service): int
    {
        $result = $service->archiveAndPrune((bool) $this->option('force'));

        if (!$result['status']) {
            $this->error($result['message']);
            return self::FAILURE;
        }

        if (isset($result['skipped'])) {
            $this->line('Skipped: ' . $result['skipped']);
            return self::SUCCESS;
        }

        $this->info(
            "Archived and removed {$result['file_count']} file(s) into {$result['file_name']} "
            . '(' . round($result['size'] / 1024 / 1024, 2) . ' MB)'
        );

        return self::SUCCESS;
    }
}
