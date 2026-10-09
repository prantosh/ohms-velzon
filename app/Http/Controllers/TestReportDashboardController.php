<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\TestReportConfirmation;
use App\Models\TestReportDelivery;
use App\Services\AuditService;
use App\Services\DiagnosticResultStatusService;
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
 *
 * Delivery tracking/actions live on the Test Report Delivery Log
 * dashboard instead (TestReportDeliveryReportController) -- this screen
 * only shows report-preparation progress (result status, confirmed,
 * print/WhatsApp). toggleDelivered() below is kept here (it owns
 * invoices.report_delivered_at) and is called from the Delivery Log's
 * Undelivered tab, not from this dashboard's own UI.
 */
class TestReportDashboardController extends Controller
{
    private const MODULE_CODE = 'DIAGNOSTIC_TEST_REPORT';

    private PatientIdentityGuard $identityGuard;
    private DiagnosticResultStatusService $statusService;

    public function __construct(PatientIdentityGuard $identityGuard, DiagnosticResultStatusService $statusService)
    {
        $this->identityGuard = $identityGuard;
        $this->statusService = $statusService;
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
            })
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('invoice_details as d')
                    ->join('invoice_item_details as iid', function ($join) {
                        $join->on('iid.item_code', '=', 'd.item_code')
                            ->on('iid.item_code_sub', '=', 'd.item_code_sub');
                    })
                    ->whereColumn('d.invoice_no', 'invoices.invoice_no')
                    ->where(function ($reportable) {
                        $reportable->where('iid.is_package', 0)
                            ->orWhereExists(function ($finding) {
                                $finding->selectRaw('1')
                                    ->from('pathology_report_finding_items as pfi')
                                    ->whereColumn('pfi.invoice_detail_id', 'd.id')
                                    ->where('d.item_code', 'PAT001');
                            });
                    })
                    ->where('iid.is_report_not_required', 0);
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

        $range = $request->get('range', '3');
        $rangeDays = ['3' => 3, '5' => 5, '7' => 7, '15' => 15, '30' => 30];

        if (isset($rangeDays[$range])) {
            $query->whereDate(
                'invoice_date',
                '>=',
                now()->subDays($rangeDays[$range] - 1)->toDateString()
            );
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

        if ($request->filled('result_status')) {
            // Result status is assembled from several report modules, so it
            // cannot be filtered reliably in SQL before the status service
            // has calculated each invoice's row.
            $allRows = $query->orderByDesc('invoice_date')->orderByDesc('id')
                ->get()
                ->map(fn ($invoice) => $this->toRow($invoice))
                ->filter(fn ($row) => $row['result_status'] === $request->result_status)
                ->values();

            $total = $allRows->count();
            $page = max(1, (int) $request->get('page', 1));
            $rows = $allRows->forPage($page, $perPage)->values();
            $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
        } else {
            $invoices = $query->orderByDesc('invoice_date')->orderByDesc('id')->paginate($perPage);

            $rows = $invoices->getCollection()->map(function ($invoice) {
                return $this->toRow($invoice);
            });

            $total = $invoices->total();
            $page = $invoices->currentPage();
            $lastPage = $invoices->lastPage();
        }

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
                'current_page' => $page,
                'last_page' => $lastPage,
                'total' => $total,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | REPORT DELIVERED TOGGLE (in-house) -- called from the Test Report
    | Delivery Log's Undelivered tab, not from this dashboard's own UI.
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

        [$resultStatus] = $this->statusService->resultStatusFor($invoice);

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
            'is_outsourced' => false,
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

    private function toRow(Invoice $invoice): array
    {
        [$resultStatus, $totalTests, $resultsEntered, $confirmedCount] = $this->statusService->resultStatusFor($invoice);

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
            'print_route' => $this->statusService->printRouteFor($invoice),
            'total_tests' => $totalTests,
            'results_entered' => $resultsEntered,
            'result_status' => $resultStatus,
            'confirmed' => $confirmed,
            'payment_status' => $paymentStatus,
            'total_amount' => (float) $invoice->total_amount,
            'paid_amount' => (float) $invoice->paid_amount,
            'due_amount' => (float) $invoice->due_amount,
            'whatsapp_status' => $invoice->whatsapp_status,
        ];
    }
}
