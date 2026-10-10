<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\TestReportDelivery;
use App\Models\XrayReportUpload;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DiagnosticResultStatusService;
use App\Services\InvoiceReportStatusRecorder;
use App\Services\PatientIdentityGuard;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Delivered tab: report over test_report_deliveries -- the append-only log
 * of every "mark as delivered"/"mark as received" action. One invoice can
 * appear more than once if it was delivered, unmarked, then re-delivered,
 * or because in-house and outsourced each log their own stages separately.
 *
 * Undelivered tab: every diagnostic invoice still owing EITHER an in-house
 * or an outsourced report delivery (or both) -- this is where delivery
 * ACTIONS now live, not on the Test Report Dashboard.
 */
class TestReportDeliveryReportController extends Controller
{
    private const MODULE_CODE = 'DIAGNOSTIC_TEST_REPORT';
    private const STAFF_ROLES = ['Admin', 'Supervisor', 'Employee'];

    private DiagnosticResultStatusService $statusService;
    private PatientIdentityGuard $identityGuard;

    public function __construct(DiagnosticResultStatusService $statusService, PatientIdentityGuard $identityGuard)
    {
        $this->statusService = $statusService;
        $this->identityGuard = $identityGuard;
    }

    public function index()
    {
        $users = User::whereIn('role', self::STAFF_ROLES)->orderBy('name')->get(['id', 'name', 'role']);

        return view('apps-test-report-delivery-report', compact('users'));
    }

    /*
    |--------------------------------------------------------------------------
    | DELIVERED TAB -- the append-only log
    |--------------------------------------------------------------------------
    */

    public function list(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);

        $rows = $this->filteredQuery($request)
            ->orderByDesc('delivered_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $rows->getCollection()->transform(function ($row) {
            return $this->toRow($row);
        });

        return response()->json([
            'status' => true,
            'data' => $rows->items(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function print(Request $request)
    {
        $rows = $this->filteredQuery($request)
            ->orderByDesc('delivered_at')
            ->orderByDesc('id')
            ->get()
            ->map(function ($row) {
                return $this->toRow($row);
            });

        $isAllUsers = !$request->filled('user_id') || $request->user_id === 'ALL';

        $userLabel = $isAllUsers
            ? 'All Users'
            : optional(User::find($request->user_id))->name;

        $pdf = Pdf::loadView('apps-test-report-delivery-report-pdf', [
            'rows' => $rows,
            'userLabel' => $userLabel,
            'search' => $request->search,
            'fromDateFmt' => $request->from_date ? Carbon::parse($request->from_date)->format('d-m-Y') : null,
            'toDateFmt' => $request->to_date ? Carbon::parse($request->to_date)->format('d-m-Y') : null,
            'totalCount' => $rows->count(),
            'printedBy' => optional(auth()->user())->name,
        ]);

        return $pdf->stream('Test-Report-Delivery-Log-' . now()->format('d-m-Y') . '.pdf');
    }

    private function filteredQuery(Request $request)
    {
        $query = TestReportDelivery::query()->with(['deliveredByUser', 'invoice']);

        if ($request->filled('user_id') && $request->user_id !== 'ALL') {
            $query->where('delivered_by', $request->user_id);
        }

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhere('patient_name', 'like', "%{$search}%")
                    ->orWhere('patient_mobile_no', 'like', "%{$search}%");
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('delivered_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('delivered_at', '<=', $request->to_date);
        }

        return $query;
    }

    private function toRow(TestReportDelivery $row): array
    {
        $paymentStatus = $row->due_amount <= 0
            ? 'Paid'
            : ($row->paid_amount <= 0 ? 'Due' : 'Partial');

        $typeLabel = !$row->is_outsourced
            ? 'In-House'
            : ($row->stage === 'received' ? 'Outsourced (Received)' : 'Outsourced (Delivered)');

        return [
            'id' => $row->id,
            'invoice_no' => $row->invoice_no,
            'patient_name' => $row->patient_name,
            'patient_mobile_no' => $row->patient_mobile_no,
            'invoice_date_fmt' => optional($row->invoice)->invoice_date
                ? Carbon::parse($row->invoice->invoice_date)->format('d-m-Y')
                : '-',
            'total_amount' => (float) $row->total_amount,
            'paid_amount' => (float) $row->paid_amount,
            'due_amount' => (float) $row->due_amount,
            'payment_status' => $paymentStatus,
            'type_label' => $typeLabel,
            'delivered_by_name' => optional($row->deliveredByUser)->name ?? '-',
            'delivered_at_fmt' => $row->delivered_at ? $row->delivered_at->format('d-m-Y h:i A') : '-',
            'xray_report_url' => $row->invoice ? XrayReportUpload::viewUrlFor($row->invoice) : null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | UNDELIVERED TAB -- invoices still owing an in-house and/or outsourced
    | delivery. This is where the Deliver/Receive actions now live.
    |--------------------------------------------------------------------------
    */

    public function undelivered(Request $request)
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

        \App\Support\ReportDeliveryScope::owesDelivery($query);

        // Oldest invoice first, same reasoning as the old Not Delivered tab
        // -- clears the backlog in order.
        $invoices = $query->orderBy('invoice_date')->orderBy('id')->paginate($perPage);

        $rows = $invoices->getCollection()->map(function ($invoice) {
            return $this->toUndeliveredRow($invoice);
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
        ]);
    }

    private function toUndeliveredRow(Invoice $invoice): array
    {
        [$inHouseStatus, $inHouseTotal, $inHouseEntered] = app(InvoiceReportStatusRecorder::class)->statusFor($invoice);
        [$outsourcedStatus, $outsourcedTotal] = $this->statusService->outsourcedStatusFor($invoice);

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
            'payment_status' => $paymentStatus,
            'due_amount' => (float) $invoice->due_amount,

            'in_house_result_status' => $inHouseStatus,
            'in_house_total_tests' => $inHouseTotal,
            'in_house_results_entered' => $inHouseEntered,
            'in_house_delivered' => (bool) $invoice->report_delivered_at,

            'outsourced_status' => $outsourcedStatus,
            'outsourced_total_tests' => $outsourcedTotal,

            'xray_report_url' => XrayReportUpload::viewUrlFor($invoice),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | OUTSOURCED ACTIONS -- Received (from the outside lab) and Delivered
    | (to the patient) are tracked as two independent timestamps/users,
    | since the same person doesn't always do both.
    |--------------------------------------------------------------------------
    */

    public function markOutsourcedReceived($id, AuditService $auditService)
    {
        $invoice = Invoice::where('invoice_type', 'DIAGNOSTIC')->findOrFail($id);

        [, $outsourcedTotal] = $this->statusService->outsourcedStatusFor($invoice);

        if ($outsourcedTotal === 0) {
            return response()->json(['status' => false, 'message' => 'This invoice has no outsourced tests.'], 422);
        }

        if ($invoice->outsourced_report_received_at) {
            return response()->json(['status' => false, 'message' => 'Already marked as received.'], 422);
        }

        $invoice->update([
            'outsourced_report_received_at' => now(),
            'outsourced_report_received_by' => Auth::id(),
        ]);

        TestReportDelivery::create([
            'invoice_id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'patient_name' => $invoice->patient_name,
            'patient_mobile_no' => $invoice->patient_mobile_no,
            'total_amount' => $invoice->total_amount,
            'paid_amount' => $invoice->paid_amount,
            'due_amount' => $invoice->due_amount,
            'delivered_by' => Auth::id(),
            'delivered_at' => now(),
            'is_outsourced' => true,
            'stage' => 'received',
        ]);

        $auditService->logAction(self::MODULE_CODE, $invoice, 'CUSTOM', 'Outsourced report marked as received from lab');

        return response()->json(['status' => true, 'message' => 'Marked as received from lab.']);
    }

    public function markOutsourcedDelivered($id, AuditService $auditService)
    {
        $invoice = Invoice::where('invoice_type', 'DIAGNOSTIC')->findOrFail($id);

        [, $outsourcedTotal] = $this->statusService->outsourcedStatusFor($invoice);

        if ($outsourcedTotal === 0) {
            return response()->json(['status' => false, 'message' => 'This invoice has no outsourced tests.'], 422);
        }

        if (!$invoice->outsourced_report_received_at) {
            return response()->json(['status' => false, 'message' => 'Cannot deliver -- not yet marked as received from the lab.'], 422);
        }

        if ($invoice->outsourced_report_delivered_at) {
            return response()->json(['status' => false, 'message' => 'Already marked as delivered.'], 422);
        }

        $invoice->update([
            'outsourced_report_delivered_at' => now(),
            'outsourced_report_delivered_by' => Auth::id(),
        ]);

        TestReportDelivery::create([
            'invoice_id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'patient_name' => $invoice->patient_name,
            'patient_mobile_no' => $invoice->patient_mobile_no,
            'total_amount' => $invoice->total_amount,
            'paid_amount' => $invoice->paid_amount,
            'due_amount' => $invoice->due_amount,
            'delivered_by' => Auth::id(),
            'delivered_at' => now(),
            'is_outsourced' => true,
            'stage' => 'delivered',
        ]);

        $auditService->logAction(self::MODULE_CODE, $invoice, 'CUSTOM', 'Outsourced report marked as delivered to patient');

        return response()->json(['status' => true, 'message' => 'Marked as delivered to patient.']);
    }
}
