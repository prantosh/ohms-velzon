<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Enforces two linked business rules shared by every diagnostic report type
 * (Pathology, Cardiology, Non-Pathology, USG, and the generic Test Report/
 * Test Result Entry module) and every invoice-cancellation entry point:
 *
 *   1. No report can be created (or re-saved) for an invoice that's already
 *      cancelled -- see invoiceIsCancelled(), called from each report
 *      controller's store()/save().
 *   2. An invoice can't be cancelled once any report already exists for it
 *      -- see anyReportExists(), called once from
 *      InvoiceCancellationApproval::check() so it automatically covers all
 *      6 cancellation entry points (Invoice Cancellation page, and the
 *      per-invoice-type Delete/Cancel buttons) without each needing its own
 *      copy of this check.
 *
 * "Exists" deliberately means any row at all, confirmed or not -- a report
 * that's only been started (not yet confirmed) still represents real work
 * that cancelling the invoice would orphan.
 */
class DiagnosticReportGuard
{
    private const REPORT_TABLES = [
        'pathology_report_findings',
        'cardiology_report_findings',
        'non_pathology_report_findings',
        'usg_report_findings',
        'test_result_entries',
    ];

    public static function invoiceIsCancelled(string $invoiceNo): bool
    {
        return DB::table('invoices')
            ->where('invoice_no', $invoiceNo)
            ->where('cancelled', 'Y')
            ->exists();
    }

    public static function anyReportExists(string $invoiceNo): bool
    {
        foreach (self::REPORT_TABLES as $table) {

            if (DB::table($table)->where('invoice_no', $invoiceNo)->exists()) {
                return true;
            }
        }

        return false;
    }
}
