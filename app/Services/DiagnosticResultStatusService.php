<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\TestReportConfirmation;
use Illuminate\Support\Facades\DB;

/**
 * Shared by TestReportDashboardController and TestReportDeliveryReportController
 * -- in-house result-entry progress (Pending/Partial/Complete/N/A, same
 * logic for both screens) plus the newer outsourced-report 3-state
 * workflow (Pending -> Received -> Delivered), which has no result-entry
 * step at all since the test itself is performed by an outside lab.
 */
class DiagnosticResultStatusService
{
    /**
     * A package's own billed line (e.g. "LIPID PROFILE", is_package=1) is a
     * pricing label, not a measurable test -- it's never claimable on the
     * report screen. is_outsourced=1 lines are excluded here too (that's
     * outsourcedLinesQuery()'s job instead) -- they're never reported
     * in-house.
     */
    public function qualifyingLinesQuery(Invoice $invoice)
    {
        return DB::table('invoice_details as d')
            ->join('invoice_item_details as iid', function ($join) {
                $join->on('iid.item_code', '=', 'd.item_code')
                    ->on('iid.item_code_sub', '=', 'd.item_code_sub');
            })
            ->where('d.invoice_no', $invoice->invoice_no)
            ->where(function ($query) {
                $query->where('iid.is_package', 0)
                    ->orWhereExists(function ($finding) {
                        $finding->selectRaw('1')
                            ->from('pathology_report_finding_items as pfi')
                            ->whereColumn('pfi.invoice_detail_id', 'd.id')
                            ->where('d.item_code', 'PAT001');
                    });
            })
            ->where('iid.is_outsourced', 0)
            ->where('iid.is_report_not_required', 0);
    }

    public function outsourcedLinesQuery(Invoice $invoice)
    {
        return DB::table('invoice_details as d')
            ->join('invoice_item_details as iid', function ($join) {
                $join->on('iid.item_code', '=', 'd.item_code')
                    ->on('iid.item_code_sub', '=', 'd.item_code_sub');
            })
            ->where('d.invoice_no', $invoice->invoice_no)
            ->where('iid.is_package', 0)
            ->where('iid.is_outsourced', 1)
            ->where('iid.is_report_not_required', 0);
    }

    /**
     * Pathology's report entry moved from a structured analyte grid
     * (test_result_entries, one legacy per-invoice TestReportConfirmation)
     * to per-line-bundle narrative reports (pathology_report_findings /
     * pathology_report_finding_items, one confirmation per report -- see
     * PathologyReportController). An invoice confirmed under the OLD system
     * before this rollout still reads as fully Complete/confirmed here (no
     * data migration) since its TestReportConfirmation row still exists;
     * everything else is computed from the new tables.
     *
     * Covers every narrative report module, not just Pathology -- USG
     * (usg_report_findings), Cardiology (cardiology_report_findings) and
     * everything else (non_pathology_report_findings) each keep exactly one
     * row per billed line with their own confirmed_at, unlike Pathology's
     * many-lines-per-finding bundling.
     *
     * @return array{0: string, 1: int, 2: int, 3: int} [result_status, total_tests, results_entered, confirmed_count] -- result_status is one of N/A, Pending, Partial, Confirmation Pending, Complete
     */
    public function resultStatusFor(Invoice $invoice): array
    {
        $lines = $this->qualifyingLinesQuery($invoice)->get(['d.id as invoice_detail_id', 'd.item_code']);

        $totalTests = $lines->count();

        if ($totalTests === 0) {
            return ['N/A', 0, 0, 0];
        }

        if (TestReportConfirmation::where('invoice_no', $invoice->invoice_no)->exists()) {
            return ['Complete', $totalTests, $totalTests, $totalTests];
        }

        $pathologyIds = $lines->where('item_code', 'PAT001')->pluck('invoice_detail_id');

        $resultsEntered = 0;
        $confirmedCount = 0;

        if ($pathologyIds->isNotEmpty()) {

            $resultsEntered += DB::table('pathology_report_finding_items')
                ->whereIn('invoice_detail_id', $pathologyIds)
                ->count();

            $confirmedCount += DB::table('pathology_report_finding_items as pfi')
                ->join('pathology_report_findings as pf', 'pf.id', '=', 'pfi.pathology_report_finding_id')
                ->whereIn('pfi.invoice_detail_id', $pathologyIds)
                ->whereNotNull('pf.confirmed_at')
                ->count();
        }

        // USG/Cardiology/everything-else each keep exactly one finding row
        // per billed line (unlike Pathology's bundling) -- "entered" is
        // just "a row exists for this line", "confirmed" adds confirmed_at.
        foreach ([
            'usg_report_findings' => $lines->where('item_code', 'USG001')->pluck('invoice_detail_id'),
            'cardiology_report_findings' => $lines->where('item_code', 'CRD001')->pluck('invoice_detail_id'),
            'non_pathology_report_findings' => $lines->whereNotIn('item_code', ['PAT001', 'USG001', 'CRD001'])->pluck('invoice_detail_id'),
        ] as $table => $ids) {

            if ($ids->isEmpty()) {
                continue;
            }

            $resultsEntered += DB::table($table)->whereIn('invoice_detail_id', $ids)->count();
            $confirmedCount += DB::table($table)->whereIn('invoice_detail_id', $ids)->whereNotNull('confirmed_at')->count();
        }

        // Fully entered isn't the same as Complete -- a report whose text is
        // all written but not yet signed off reads as "Confirmation Pending"
        // instead, so staff don't mistake data-entry progress for a report
        // that's actually finished and ready to print/deliver.
        $resultStatus = $resultsEntered <= 0
            ? 'Pending'
            : ($resultsEntered < $totalTests
                ? 'Partial'
                : ($confirmedCount >= $totalTests ? 'Complete' : 'Confirmation Pending'));

        return [$resultStatus, $totalTests, $resultsEntered, $confirmedCount];
    }

    /**
     * Outsourced tests have no result-entry step in this system at all (the
     * test itself is performed by an outside lab) -- status is purely a
     * function of the invoice's own outsourced_report_received_at/
     * delivered_at, not of anything claimable/confirmable per line.
     *
     * @return array{0: string, 1: int} [status, total_outsourced_lines] -- status is one of N/A, Pending, Received, Delivered
     */
    public function outsourcedStatusFor(Invoice $invoice): array
    {
        $total = $this->outsourcedLinesQuery($invoice)->count();

        if ($total === 0) {
            return ['N/A', 0];
        }

        if ($invoice->outsourced_report_delivered_at) {
            return ['Delivered', $total];
        }

        if ($invoice->outsourced_report_received_at) {
            return ['Received', $total];
        }

        return ['Pending', $total];
    }

    /**
     * Which standalone page actually owns this invoice's in-house report(s)
     * -- Pathology and generic Non-Pathology items live inside the shared
     * Test Result Entry modal, but USG (usg-report.index) and Cardiology
     * (cardiology-report.index) are each their OWN separate page with their
     * own confirm/print routes, not reachable through Test Result Entry at
     * all. Used to send staff to the right place instead of the legacy
     * single-PDF route, which only understands the old whole-invoice
     * TestReportConfirmation and 403s ("must be confirmed") on anything
     * confirmed through these newer per-line modules. An invoice mixing
     * USG/Cardiology/other items on one invoice (rare, but real) has no
     * single right destination -- falls back to the Test Result Entry
     * modal, same as plain Non-Pathology.
     */
    public function printRouteFor(Invoice $invoice): string
    {
        if ($invoice->invoice_category === 'PATHOLOGY') {
            return 'pathology';
        }

        $itemCodes = $this->qualifyingLinesQuery($invoice)->pluck('d.item_code')->unique();

        // No qualifying line at all -- e.g. an invoice confirmed entirely
        // under the old pre-narrative system, with nothing in any of the
        // new per-line tables to route to. Only the legacy whole-invoice
        // PDF route has anything to show for it.
        if ($itemCodes->isEmpty()) {
            return 'legacy';
        }

        if ($itemCodes->count() === 1 && $itemCodes->first() === 'USG001') {
            return 'usg';
        }

        if ($itemCodes->count() === 1 && $itemCodes->first() === 'CRD001') {
            return 'cardiology';
        }

        return 'non_pathology';
    }
}
