<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Keeps invoices.report_status (+ total/prepared/confirmed counts) equal to
 * DiagnosticResultStatusService::resultStatusFor(), which stays the single
 * place the status is actually worked out. Readers (Test Report Dashboard,
 * Delivery Log) call statusFor(), which returns the stored values and, for an
 * invoice with none yet (new, invalidated, or never backfilled), computes and
 * stores them on the spot -- so a missed refresh can only ever cost one
 * recompute, never show a wrong status for long.
 *
 * Written with plain DB updates on purpose: no Invoice model events, no
 * audit-log noise, no updated_at change for what is derived data.
 */
class InvoiceReportStatusRecorder
{
    private DiagnosticResultStatusService $statusService;

    public function __construct(DiagnosticResultStatusService $statusService)
    {
        $this->statusService = $statusService;
    }

    /**
     * @return array{0: string, 1: int, 2: int, 3: int} same shape as resultStatusFor()
     */
    public function statusFor(Invoice $invoice): array
    {
        if ($invoice->report_status !== null) {

            return [
                $invoice->report_status,
                (int) $invoice->report_total_tests,
                (int) $invoice->report_prepared_count,
                (int) $invoice->report_confirmed_count,
            ];
        }

        return $this->refreshInvoice($invoice);
    }

    /**
     * Recompute and store. Returns the fresh status tuple.
     *
     * @return array{0: string, 1: int, 2: int, 3: int}
     */
    public function refreshInvoice(Invoice $invoice): array
    {
        $status = $this->statusService->resultStatusFor($invoice);

        [$resultStatus, $total, $prepared, $confirmed] = $status;

        DB::table('invoices')->where('id', $invoice->id)->update([
            'report_status' => $resultStatus,
            'report_total_tests' => $total,
            'report_prepared_count' => $prepared,
            'report_confirmed_count' => $confirmed,
            'report_status_updated_at' => now(),
        ]);

        // Keep the in-memory model in step for the caller.
        $invoice->report_status = $resultStatus;
        $invoice->report_total_tests = $total;
        $invoice->report_prepared_count = $prepared;
        $invoice->report_confirmed_count = $confirmed;

        return $status;
    }

    /**
     * Refresh by invoice number -- the entry point for the model observers
     * and the invoice create/update paths. Never throws: a failure here must
     * not break saving a report; the status just stays NULL/old and is
     * recomputed on the next read.
     */
    public function refreshByInvoiceNo(?string $invoiceNo): void
    {
        if (!$invoiceNo) {
            return;
        }

        try {

            $invoice = Invoice::where('invoice_no', $invoiceNo)->where('invoice_type', 'DIAGNOSTIC')->first();

            if ($invoice) {
                $this->refreshInvoice($invoice);
            }

        } catch (\Throwable $e) {

            Log::warning('Report status refresh failed for ' . $invoiceNo . ': ' . $e->getMessage());

            $this->invalidateByInvoiceNo($invoiceNo);
        }
    }

    /**
     * Mark stored statuses as stale (NULL) so the next read recomputes
     * them. Cheap -- one UPDATE -- which is why item-master changes that
     * can affect thousands of invoices use this instead of recomputing.
     */
    public function invalidateByInvoiceNo(string ...$invoiceNos): void
    {
        try {

            DB::table('invoices')->whereIn('invoice_no', $invoiceNos)->update(['report_status' => null]);

        } catch (\Throwable $e) {

            Log::warning('Report status invalidation failed: ' . $e->getMessage());
        }
    }

    /**
     * Invalidate every invoice carrying a given billed item -- used when an
     * item's reporting flags (report not required / outsourced / package)
     * change, which changes which lines count toward the status.
     */
    public function invalidateForItem(string $itemCode, string $itemCodeSub): void
    {
        try {

            DB::table('invoices')
                ->whereIn('invoice_no', function ($sub) use ($itemCode, $itemCodeSub) {
                    $sub->select('invoice_no')
                        ->from('invoice_details')
                        ->where('item_code', $itemCode)
                        ->where('item_code_sub', $itemCodeSub);
                })
                ->update(['report_status' => null]);

        } catch (\Throwable $e) {

            Log::warning('Report status invalidation for item failed: ' . $e->getMessage());
        }
    }

    /**
     * Compute any still-NULL statuses within an invoices query (a no-op once
     * everything is stored). Needed before filtering by status in SQL.
     */
    public function fillMissing(Builder $invoicesQuery): int
    {
        $count = 0;

        // Paged by id, NOT by offset: this loop fills the very column the
        // query filters on, so offset paging would skip rows as they drop
        // out of the result set.
        foreach ((clone $invoicesQuery)->whereNull('report_status')->lazyById(200) as $invoice) {
            $this->refreshInvoice($invoice);
            $count++;
        }

        return $count;
    }
}
