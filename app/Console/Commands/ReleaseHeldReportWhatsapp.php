<?php

namespace App\Console\Commands;

use App\Services\ReportWhatsappDueGate;
use Illuminate\Console\Command;

/**
 * Safety net for the due-payment WhatsApp hold: sends every held report
 * whose invoice is now fully paid but was never released -- e.g. the
 * payment request died after committing (timeout / fatal in a later step)
 * before ReportWhatsappDueGate::releaseAll() ran. The normal release still
 * happens instantly inside the payment request; this only catches misses.
 * See Kernel::schedule() for the interval.
 */
class ReleaseHeldReportWhatsapp extends Command
{
    protected $signature = 'reports:release-held-whatsapp';

    protected $description = 'Send held report WhatsApp messages for invoices that are now fully paid';

    public function handle(): int
    {
        $invoiceNos = ReportWhatsappDueGate::releaseAllPaid();

        $this->info(count($invoiceNos) . ' invoice(s) released' . ($invoiceNos ? ': ' . implode(', ', $invoiceNos) : '.'));

        return self::SUCCESS;
    }
}
