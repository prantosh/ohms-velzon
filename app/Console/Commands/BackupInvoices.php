<?php

namespace App\Console\Commands;

use App\Services\InvoicesBackupService;
use Illuminate\Console\Command;

class BackupInvoices extends Command
{
    protected $signature = 'invoices:backup';

    protected $description = 'Zip public/invoices into storage/app/backups (see Backups screen to download)';

    public function handle(InvoicesBackupService $service): int
    {
        $result = $service->run();

        if (!$result['status']) {
            $this->error($result['message']);
            return self::FAILURE;
        }

        $this->info(
            "Backed up {$result['file_count']} file(s) to {$result['file_name']} "
            . '(' . round($result['size'] / 1024 / 1024, 2) . ' MB)'
        );

        return self::SUCCESS;
    }
}
