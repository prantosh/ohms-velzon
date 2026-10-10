<?php

namespace App\Http\Controllers;

use App\Models\CardiologyReportFinding;
use App\Models\Invoice;
use App\Models\NonPathologyReportFinding;
use App\Models\PathologyReportFinding;
use App\Models\Patient;
use App\Models\TestReportConfirmation;
use App\Models\TestReportDelivery;
use App\Models\UsgReportFinding;
use App\Models\XrayReportUpload;
use App\Services\AuditService;
use App\Services\DiagnosticResultStatusService;
use App\Services\InvoiceReportItemsService;
use App\Services\InvoiceReportStatusRecorder;
use App\Services\PatientIdentityGuard;
use App\Services\TestReportRowBuilder;
use App\Support\PdfMerger;
use App\Support\ReportDeliveryScope;
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
    private InvoiceReportStatusRecorder $statusRecorder;

    public function __construct(
        PatientIdentityGuard $identityGuard,
        DiagnosticResultStatusService $statusService,
        InvoiceReportStatusRecorder $statusRecorder
    ) {
        $this->statusRecorder = $statusRecorder;
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

        // Defaults to Not Delivered when the param is absent; 'all' turns
        // the delivery filter off. "Delivered" means owing no in-house or
        // outsourced delivery -- the exact inverse of the Delivery Log's
        // Undelivered tab (see ReportDeliveryScope).
        $deliveryStatus = $request->get('delivery_status', 'not_delivered');

        $query = Invoice::where('invoice_type', 'DIAGNOSTIC')
            ->where(function ($q) {
                $q->whereNull('cancelled')->orWhere('cancelled', '!=', 'Y');
            });

        // Only invoices with at least one reportable line. "Not Delivered"
        // already implies it (an invoice can only owe a delivery through such
        // a line), so repeating this correlated check there only made every
        // page load roughly three times slower.
        if ($deliveryStatus !== 'not_delivered') {

            $query->whereExists(function ($sub) {
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
        }

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

        if ($deliveryStatus === 'not_delivered') {
            ReportDeliveryScope::owesDelivery($query);
        } elseif ($deliveryStatus === 'delivered') {
            ReportDeliveryScope::owesDelivery($query, true);
        }

        if ($request->filled('result_status')) {
            // The status is stored on the invoice (see InvoiceReportStatusRecorder),
            // so this is a plain indexed filter. Any invoice in the filtered set
            // that has no stored status yet (new, invalidated, or never
            // backfilled) is computed and saved first so it can't be missed --
            // a no-op once everything is stored.
            $this->statusRecorder->fillMissing($query);

            $query->where('report_status', $request->result_status);
        }

        $invoices = $query->orderByDesc('invoice_date')->orderByDesc('id')->paginate($perPage);

        $rows = $invoices->getCollection()->map(function ($invoice) {
            return $this->toRow($invoice);
        });

        $total = $invoices->total();
        $page = $invoices->currentPage();
        $lastPage = $invoices->lastPage();

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
    | PRINT MODAL -- per-item report status, and printing only the items the
    | user ticked (confirmed ones only; enforced here, not just in the UI)
    |--------------------------------------------------------------------------
    */

    public function items($id, InvoiceReportItemsService $itemsService)
    {
        $invoice = Invoice::where('invoice_type', 'DIAGNOSTIC')->findOrFail($id);

        $items = $itemsService->forInvoice($invoice);

        // Same status the dashboard column shows, so the window explains it.
        [$resultStatus, $totalTests, $resultsEntered, $confirmedCount] = $this->statusRecorder->statusFor($invoice);

        return response()->json([
            'status' => true,
            'invoice' => [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'patient_name' => $invoice->patient_name,
            ],
            'summary' => [
                'result_status' => $resultStatus,
                'total' => $totalTests,
                'prepared' => $resultsEntered,
                'confirmed' => $confirmedCount,
            ],
            'items' => $items,
        ]);
    }

    public function printSelected(Request $request, InvoiceReportItemsService $itemsService, AuditService $auditService)
    {
        $request->validate([
            'invoice_id' => 'required|integer',
            'detail_ids' => 'required|array|min:1',
            'detail_ids.*' => 'integer',
        ]);

        $invoice = Invoice::where('invoice_type', 'DIAGNOSTIC')->findOrFail($request->invoice_id);

        $selected = collect($request->detail_ids)->map(fn ($v) => (int) $v)->all();

        // Billing order, and only what was actually ticked.
        $items = collect($itemsService->forInvoice($invoice))
            ->filter(fn ($item) => in_array($item['invoice_detail_id'], $selected, true))
            ->values();

        if ($items->count() !== count(array_unique($selected))) {
            abort(422, 'One or more selected items do not belong to this invoice.');
        }

        $notReady = $items->first(fn ($item) => !$item['can_print']);

        if ($notReady) {
            abort(422, 'The report for "' . $notReady['item_description'] . '" is not confirmed yet, so it cannot be printed.');
        }

        // One document per underlying report -- a Pathology report can cover
        // several billed items, so ticking two of them prints it once.
        $documents = [];

        // Several Pathology reports print as ONE sectioned document -- tests
        // of the same group run on continuously and the page only breaks
        // when the test group changes (the "print all" layout) -- instead of
        // one separate document, and so one new page, per report. A single
        // Pathology report keeps its normal standalone layout. It sits where
        // the first Pathology item was in billing order.
        $pathologyFindingIds = $items->where('kind', InvoiceReportItemsService::KIND_PATHOLOGY)
            ->pluck('finding_id')->unique()->values()->all();

        foreach ($items as $item) {

            if ($item['kind'] === InvoiceReportItemsService::KIND_PATHOLOGY && count($pathologyFindingIds) > 1) {

                $documents['pathology'] ??= app(PathologyReportController::class)
                    ->buildSectionedPdf($invoice, $pathologyFindingIds)->output();

                continue;
            }

            $key = $item['kind'] . ':' . ($item['finding_id'] ?? 'invoice');

            if (isset($documents[$key])) {
                continue;
            }

            $documents[$key] = $this->renderItemPdf($invoice, $item);
        }

        try {

            $pdf = PdfMerger::merge(array_values($documents));

        } catch (\Throwable $e) {

            \Log::warning('Selected reports merge failed for ' . $invoice->invoice_no . ': ' . $e->getMessage());

            abort(422, 'These reports could not be combined into one PDF (an uploaded PDF is in a format that cannot be merged). Print them one at a time instead.');
        }

        $auditService->logAction(
            self::MODULE_CODE,
            $invoice,
            'PRINT',
            'Selected reports printed: ' . $items->pluck('item_description')->implode(', ')
        );

        $fileName = str_replace(['/', '\\'], '-', $invoice->invoice_no) . '-selected-reports.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    private function renderItemPdf(Invoice $invoice, array $item): string
    {
        switch ($item['kind']) {

            case InvoiceReportItemsService::KIND_PATHOLOGY:
                return app(PathologyReportController::class)
                    ->buildFindingPdf(PathologyReportFinding::findOrFail($item['finding_id']))->output();

            case InvoiceReportItemsService::KIND_USG:
                return app(UsgReportController::class)
                    ->buildFindingPdf(UsgReportFinding::findOrFail($item['finding_id']))->output();

            case InvoiceReportItemsService::KIND_CARDIOLOGY:
                return app(CardiologyReportController::class)
                    ->buildFindingPdf(CardiologyReportFinding::findOrFail($item['finding_id']))->output();

            case InvoiceReportItemsService::KIND_NON_PATHOLOGY:
                return app(NonPathologyReportController::class)
                    ->buildFindingPdf(NonPathologyReportFinding::findOrFail($item['finding_id']))->output();

            case InvoiceReportItemsService::KIND_XRAY_UPLOAD:
                $upload = XrayReportUpload::where('invoice_no', $invoice->invoice_no)->firstOrFail();
                return file_get_contents($upload->path());

            default: // legacy whole-invoice report
                return app(TestResultEntryController::class)
                    ->buildInvoicePdf($invoice, app(TestReportRowBuilder::class))->output();
        }
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

        [$resultStatus] = $this->statusRecorder->statusFor($invoice);

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
        [$resultStatus, $totalTests, $resultsEntered, $confirmedCount] = $this->statusRecorder->statusFor($invoice);

        // Fully confirmed only when every qualifying line's own report is
        // confirmed. The legacy per-invoice confirmation (from before this
        // rollout) already reads as every line confirmed in the stored counts;
        // it only needs its own lookup for an invoice with no qualifying line.
        $confirmed = ($totalTests > 0 && $confirmedCount >= $totalTests)
            || ($totalTests === 0 && TestReportConfirmation::where('invoice_no', $invoice->invoice_no)->exists());

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
            'confirmed_count' => $confirmedCount,
            'payment_status' => $paymentStatus,
            'total_amount' => (float) $invoice->total_amount,
            'paid_amount' => (float) $invoice->paid_amount,
            'due_amount' => (float) $invoice->due_amount,
            'whatsapp_status' => $invoice->whatsapp_status,
            'xray_report_url' => XrayReportUpload::viewUrlFor($invoice),
        ];
    }
}
