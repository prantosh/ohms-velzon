<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\InvoiceReportStatusRecorder;
use Illuminate\Console\Command;

class RefreshInvoiceReportStatus extends Command
{
    protected $signature = 'invoices:refresh-report-status {--all : Recompute every invoice, not just those without a stored status} {--limit= : Stop after this many invoices}';

    protected $description = 'Compute and store invoices.report_status for diagnostic invoices (initial backfill / full rebuild)';

    public function handle(InvoiceReportStatusRecorder $recorder): int
    {
        $query = Invoice::where('invoice_type', 'DIAGNOSTIC');

        if (!$this->option('all')) {
            $query->whereNull('report_status');
        }

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $done = 0;

        foreach ($query->orderBy('id')->lazyById(200) as $invoice) {

            $recorder->refreshInvoice($invoice);

            $done++;

            if ($limit && $done >= $limit) {
                break;
            }
        }

        $this->info("Report status stored for {$done} invoice(s).");

        return self::SUCCESS;
    }
}
