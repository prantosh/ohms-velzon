<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItemMaster;
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

        if ($request->filled('delivery_status')) {

            if ($request->delivery_status === 'Delivered') {
                $query->whereNotNull('report_delivered_at');
            } else {
                $query->whereNull('report_delivered_at');
            }
        }

        $invoices = $query->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        $qualifyingItemCodes = InvoiceItemMaster::where('test_parameter_required', 'YES')
            ->pluck('item_code')
            ->toArray();

        $rows = $invoices->getCollection()->map(function ($invoice) use ($qualifyingItemCodes) {
            return $this->toRow($invoice, $qualifyingItemCodes);
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
            ]
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

        $qualifyingItemCodes = InvoiceItemMaster::where('test_parameter_required', 'YES')
            ->pluck('item_code')
            ->toArray();

        [$resultStatus] = $this->resultStatusFor($invoice, $qualifyingItemCodes);

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
     * @return array{0: string, 1: int, 2: int} [result_status, total_tests, results_entered]
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
    private function qualifyingLinesQuery(Invoice $invoice, array $qualifyingItemCodes)
    {
        return DB::table('invoice_details as d')
            ->join('invoice_item_details as iid', function ($join) {
                $join->on('iid.item_code', '=', 'd.item_code')
                    ->on('iid.item_code_sub', '=', 'd.item_code_sub');
            })
            ->where('d.invoice_no', $invoice->invoice_no)
            ->whereIn('d.item_code', $qualifyingItemCodes)
            ->where('iid.is_package', 0)
            ->where('iid.is_outsourced', 0);
    }

    private function resultStatusFor(Invoice $invoice, array $qualifyingItemCodes): array
    {
        $totalTests = $this->qualifyingLinesQuery($invoice, $qualifyingItemCodes)->count();

        if ($totalTests === 0) {
            return ['N/A', 0, 0];
        }

        if (TestReportConfirmation::where('invoice_no', $invoice->invoice_no)->exists()) {
            return ['Complete', $totalTests, $totalTests];
        }

        $qualifyingLineIds = $this->qualifyingLinesQuery($invoice, $qualifyingItemCodes)->pluck('d.id');

        $resultsEntered = DB::table('pathology_report_finding_items')
            ->whereIn('invoice_detail_id', $qualifyingLineIds)
            ->count();

        $resultStatus = $resultsEntered <= 0
            ? 'Pending'
            : ($resultsEntered >= $totalTests ? 'Complete' : 'Partial');

        return [$resultStatus, $totalTests, $resultsEntered];
    }

    private function toRow(Invoice $invoice, array $qualifyingItemCodes): array
    {
        [$resultStatus, $totalTests, $resultsEntered] = $this->resultStatusFor($invoice, $qualifyingItemCodes);

        // Fully confirmed only when every qualifying line's claim is on a
        // CONFIRMED finding (or the invoice carries the legacy per-invoice
        // confirmation from before this rollout).
        $confirmed = TestReportConfirmation::where('invoice_no', $invoice->invoice_no)->exists();

        if (!$confirmed && $totalTests > 0 && $resultStatus === 'Complete') {

            $qualifyingLineIds = $this->qualifyingLinesQuery($invoice, $qualifyingItemCodes)->pluck('d.id');

            $confirmedCount = DB::table('pathology_report_finding_items as pfi')
                ->join('pathology_report_findings as pf', 'pf.id', '=', 'pfi.pathology_report_finding_id')
                ->whereIn('pfi.invoice_detail_id', $qualifyingLineIds)
                ->whereNotNull('pf.confirmed_at')
                ->count();

            $confirmed = $confirmedCount >= $totalTests;
        }

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
