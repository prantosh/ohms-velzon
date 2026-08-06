<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItemMaster;
use App\Models\TestReportConfirmation;
use App\Services\AuditService;
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

        $invoice->update([
            'report_delivered_at' => now(),
            'report_delivered_by' => Auth::id(),
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
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{0: string, 1: int, 2: int} [result_status, total_tests, results_entered]
     */
    private function resultStatusFor(Invoice $invoice, array $qualifyingItemCodes): array
    {
        $totalTests = DB::table('invoice_details')
            ->where('invoice_no', $invoice->invoice_no)
            ->whereIn('item_code', $qualifyingItemCodes)
            ->count();

        $resultsEntered = DB::table('test_result_entries')
            ->where('invoice_no', $invoice->invoice_no)
            ->where(function ($q) {
                $q->whereNotNull('result_value')->where('result_value', '!=', '');
            })
            ->distinct()
            ->count('invoice_detail_id');

        $resultStatus = $totalTests === 0
            ? 'N/A'
            : ($resultsEntered <= 0
                ? 'Pending'
                : ($resultsEntered >= $totalTests ? 'Complete' : 'Partial'));

        return [$resultStatus, $totalTests, $resultsEntered];
    }

    private function toRow(Invoice $invoice, array $qualifyingItemCodes): array
    {
        [$resultStatus, $totalTests, $resultsEntered] = $this->resultStatusFor($invoice, $qualifyingItemCodes);

        $confirmed = TestReportConfirmation::where('invoice_no', $invoice->invoice_no)->exists();

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
