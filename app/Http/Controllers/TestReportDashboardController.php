<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\TestReportConfirmation;
use App\Models\TestReportDelivery;
use App\Services\AuditService;
use App\Services\PatientIdentityGuard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Listing/CRUD-style dashboard over diagnostic test report invoices.
 * Distinct from DiagnosticTestReportController, which is a narrower
 * single-invoice search+print-by-group utility -- this is the "all
 * reports, with status and quick actions" overview.
 */
class TestReportDashboardController extends Controller
{
    private const MODULE_CODE = 'DIAGNOSTIC_TEST_REPORT';

    private PatientIdentityGuard $identityGuard;

    public function __construct(PatientIdentityGuard $identityGuard)
    {
        $this->identityGuard = $identityGuard;
    }

    public function index()
    {
        return view('apps-test-report-dashboard');
    }

    public function list(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);

        $query = Invoice::where('invoice_type', 'DIAGNOSTIC')
            ->where(function ($q) {
                $q->whereNull('cancelled')->orWhere('cancelled', '!=', 'Y');
            });

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhere('patient_name', 'like', "%{$search}%")
                    ->orWhere('patient_mobile_no', 'like', "%{$search}%");
            });
        }

        if ($request->filled('invoice_category')) {
            $query->where('invoice_category', $request->invoice_category);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('invoice_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('invoice_date', '<=', $request->to_date);
        }

        if ($request->filled('payment_status')) {

            if ($request->payment_status === 'Paid') {
                $query->where('due_amount', '<=', 0);
            } elseif ($request->payment_status === 'Due') {
                $query->where('due_amount', '>', 0)->where('paid_amount', '<=', 0);
            } elseif ($request->payment_status === 'Partial') {
                $query->where('due_amount', '>', 0)->where('paid_amount', '>', 0);
            }
        }

        // Counts for the Delivered / Not Delivered tab badges, taken before
        // the delivery_status filter itself narrows the query so both tabs
        // always show accurate totals regardless of which tab is active.
        $counts = [
            'delivered' => (clone $query)->whereNotNull('report_delivered_at')->count(),
            'not_delivered' => (clone $query)->whereNull('report_delivered_at')->count(),
        ];

        $isNotDeliveredTab = $request->delivery_status === 'Pending';

        if ($request->filled('delivery_status')) {

            if ($request->delivery_status === 'Delivered') {
                $query->whereNotNull('report_delivered_at');
            } else {
                $query->whereNull('report_delivered_at');
            }
        }

        // Not Delivered tab surfaces the oldest pending reports first so
        // staff clear the backlog in order; Delivered keeps the normal
        // most-recent-first view.
        if ($isNotDeliveredTab) {
            $query->orderBy('invoice_date')->orderBy('id');
        } else {
            $query->orderByDesc('invoice_date')->orderByDesc('id');
        }

        $invoices = $query->paginate($perPage);

        $rows = $invoices->getCollection()->map(function ($invoice) {
            return $this->toRow($invoice);
        });

        $protectedUsers = $this->identityGuard->protectedUsersForMobiles($rows->pluck('patient_mobile_no'));

        $rows = $rows->map(function ($row) use ($protectedUsers) {
            $row['can_edit_patient_name'] = !$this->identityGuard->isProtected(
                $row['patient_mobile_no'],
                $row['patient_name'],
                $protectedUsers
            );
            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $rows,
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'total' => $invoices->total(),
            ],
            'counts' => $counts,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | REPORT DELIVERED TOGGLE
    |--------------------------------------------------------------------------
    */

    public function toggleDelivered($id, AuditService $auditService)
    {
        $invoice = Invoice::where('invoice_type', 'DIAGNOSTIC')->findOrFail($id);

        if ($invoice->report_delivered_at) {

            $invoice->update([
                'report_delivered_at' => null,
                'report_delivered_by' => null,
            ]);

            $auditService->logAction(
                self::MODULE_CODE,
                $invoice,
                'CUSTOM',
                'Report delivery unmarked'
            );

            return response()->json([
                'status' => true,
                'delivered' => false,
                'message' => 'Marked as not delivered.'
            ]);
        }

        [$resultStatus] = $this->resultStatusFor($invoice);

        if ($resultStatus === 'Pending') {

            return response()->json([
                'status' => false,
                'message' => 'Cannot mark as delivered -- no test results have been entered yet.'
            ], 422);
        }

        $deliveredAt = now();

        $invoice->update([
            'report_delivered_at' => $deliveredAt,
            'report_delivered_by' => Auth::id(),
        ]);

        // Append-only delivery log -- unmarking never removes a row here, so
        // an invoice unmarked and re-delivered later ends up with more than
        // one entry, which is exactly what the delivery report needs.
        TestReportDelivery::create([
            'invoice_id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'patient_name' => $invoice->patient_name,
            'patient_mobile_no' => $invoice->patient_mobile_no,
            'total_amount' => $invoice->total_amount,
            'paid_amount' => $invoice->paid_amount,
            'due_amount' => $invoice->due_amount,
            'delivered_by' => Auth::id(),
            'delivered_at' => $deliveredAt,
        ]);

        $auditService->logAction(
            self::MODULE_CODE,
            $invoice,
            'CUSTOM',
            'Report marked as delivered'
        );

        return response()->json([
            'status' => true,
            'delivered' => true,
            'message' => 'Marked as delivered.'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PATIENT NAME CORRECTION (typo fix from invoice creation)
    |--------------------------------------------------------------------------
    */

    public function updatePatientName(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'patient_name' => 'required|string|max:150',
        ]);

        $invoice = Invoice::where('invoice_type', 'DIAGNOSTIC')->findOrFail($id);

        $protectedUsers = $this->identityGuard->protectedUsersForMobiles([$invoice->patient_mobile_no]);

        if ($this->identityGuard->isProtected($invoice->patient_mobile_no, $invoice->patient_name, $protectedUsers)) {

            return response()->json([
                'status' => false,
                'message' => 'This patient matches a Member/Admin/Supervisor account (or one of their registered family members) and cannot be renamed here.',
            ], 422);
        }

        $newName = trim($request->patient_name);

        if ($newName === '') {
            return response()->json(['status' => false, 'message' => 'Patient name cannot be empty.'], 422);
        }

        $oldName = $invoice->patient_name;

        DB::beginTransaction();

        try {

            $invoice->update(['patient_name' => $newName]);

            // The permanent fix -- the patients master row, matched the same
            // way DiagnosticInvoiceController links an invoice back to it.
            if ($invoice->patient_id) {
                Patient::where('patient_id', $invoice->patient_id)->update(['patient_name' => $newName]);
            }

            DB::commit();

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }

        $auditService->logAction(
            self::MODULE_CODE,
            $invoice,
            'CUSTOM',
            "Patient name corrected from \"{$oldName}\" to \"{$newName}\""
        );

        return response()->json([
            'status' => true,
            'message' => 'Patient name updated.',
            'patient_name' => $newName,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

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
     * many-lines-per-finding bundling. Before this, an invoice made up
     * entirely of USG/Cardiology/Non-Pathology items always fell through to
     * "N/A (0/0)" here (qualifying lines were hardcoded to
     * test_parameter_required='YES', which is true only for Pathology's
     * PAT001) regardless of whether its report had actually been
     * written/confirmed -- which also meant toggleDelivered() never
     * actually blocked delivering an unreported USG/Cardiology/Non-Pathology
     * invoice.
     *
     * @return array{0: string, 1: int, 2: int, 3: int} [result_status, total_tests, results_entered, confirmed_count]
     */
    /**
     * A package's own billed line (e.g. "LIPID PROFILE", is_package=1) is a
     * pricing label, not a measurable test -- it's never claimable on the
     * report screen (PathologyReportController excludes it the same way),
     * so counting it as a test needing a result left every package invoice
     * permanently one short and stuck on "Partial" even once fully
     * confirmed. is_outsourced=1 lines are likewise never reported
     * in-house (PathologyReportController excludes those too).
     */
    private function qualifyingLinesQuery(Invoice $invoice)
    {
        return DB::table('invoice_details as d')
            ->join('invoice_item_details as iid', function ($join) {
                $join->on('iid.item_code', '=', 'd.item_code')
                    ->on('iid.item_code_sub', '=', 'd.item_code_sub');
            })
            ->where('d.invoice_no', $invoice->invoice_no)
            ->where('iid.is_package', 0)
            ->where('iid.is_outsourced', 0);
    }

    private function resultStatusFor(Invoice $invoice): array
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

        $resultStatus = $resultsEntered <= 0
            ? 'Pending'
            : ($resultsEntered >= $totalTests ? 'Complete' : 'Partial');

        return [$resultStatus, $totalTests, $resultsEntered, $confirmedCount];
    }

    /**
     * Which standalone page actually owns this invoice's report(s) --
     * Pathology and generic Non-Pathology items live inside the shared
     * Test Result Entry modal, but USG (usg-report.index) and Cardiology
     * (cardiology-report.index) are each their OWN separate page with their
     * own confirm/print routes, not reachable through Test Result Entry at
     * all. The dashboard's Print/WhatsApp buttons use this to send staff to
     * the right place instead of the legacy single-PDF route, which only
     * understands the old whole-invoice TestReportConfirmation and 403s
     * ("must be confirmed") on anything confirmed through these newer
     * per-line modules. An invoice mixing USG/Cardiology/other items on one
     * invoice (rare, but real) has no single right destination -- falls
     * back to the Test Result Entry modal, same as plain Non-Pathology.
     */
    private function printRouteFor(Invoice $invoice): string
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

    private function toRow(Invoice $invoice): array
    {
        [$resultStatus, $totalTests, $resultsEntered, $confirmedCount] = $this->resultStatusFor($invoice);

        // Fully confirmed only when every qualifying line's own report is
        // confirmed (or the invoice carries the legacy per-invoice
        // confirmation from before this rollout).
        $confirmed = TestReportConfirmation::where('invoice_no', $invoice->invoice_no)->exists()
            || ($totalTests > 0 && $confirmedCount >= $totalTests);

        $paymentStatus = $invoice->due_amount <= 0
            ? 'Paid'
            : ($invoice->paid_amount <= 0 ? 'Due' : 'Partial');

        return [
            'id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'invoice_date' => $invoice->invoice_date
                ? Carbon::parse($invoice->invoice_date)->format('d-m-Y')
                : null,
            'patient_name' => $invoice->patient_name,
            'patient_mobile_no' => $invoice->patient_mobile_no,
            'invoice_category' => $invoice->invoice_category,
            'print_route' => $this->printRouteFor($invoice),
            'total_tests' => $totalTests,
            'results_entered' => $resultsEntered,
            'result_status' => $resultStatus,
            'confirmed' => $confirmed,
            'payment_status' => $paymentStatus,
            'total_amount' => (float) $invoice->total_amount,
            'paid_amount' => (float) $invoice->paid_amount,
            'due_amount' => (float) $invoice->due_amount,
            'whatsapp_status' => $invoice->whatsapp_status,
            'report_delivered_at' => $invoice->report_delivered_at
                ? Carbon::parse($invoice->report_delivered_at)->format('d-m-Y h:i A')
                : null,
        ];
    }
}
