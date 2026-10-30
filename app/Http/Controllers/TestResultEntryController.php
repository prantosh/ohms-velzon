<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItemDetail;
use App\Models\InvoiceItemMaster;
use App\Models\InstrumentMaster;
use App\Models\KitMaster;
use App\Models\NoteMaster;
use App\Models\MicroscopyMaster;
use App\Models\ImpressionMaster;
use App\Models\FindingMaster;
use App\Models\TestAnalyte;
use App\Models\TestExtraFieldType;
use App\Models\TestReportConfirmation;
use App\Models\TestResultEntry;
use App\Models\TestResultExtraValue;
use App\Models\WhatsappAutoSendSetting;
use App\Services\AuditService;
use App\Services\ReportWhatsappDueGate;
use App\Services\DiagnosticResultStatusService;
use App\Services\InvoiceReportStatusRecorder;
use App\Support\PdfPageNumbers;
use App\Services\PdfPasswordProtectionService;
use App\Services\TestReportRowBuilder;
use App\Services\WatiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TestResultEntryController extends Controller
{
    private const MODULE_CODE = 'TEST_RESULT_ENTRY';

    public function index()
    {
        $extraFieldTypes = TestExtraFieldType::where('status', 'ACTIVE')
            ->orderBy('sort_order')
            ->get(['id', 'field_name', 'input_type', 'source_master']);

        $masterOptions = [
            'instrument' => InstrumentMaster::where('status', 'ACTIVE')->orderBy('name')->pluck('name'),
            'kit' => KitMaster::where('status', 'ACTIVE')->orderBy('name')->pluck('name'),
            'note' => NoteMaster::where('status', 'ACTIVE')->orderBy('name')->pluck('name'),
            'microscopy' => MicroscopyMaster::where('status', 'ACTIVE')->orderBy('name')->pluck('name'),
            'impression' => ImpressionMaster::where('status', 'ACTIVE')->orderBy('name')->pluck('name'),
            'finding' => FindingMaster::where('status', 'ACTIVE')->orderBy('name')->pluck('name'),
        ];

        $extraFieldTypes = $extraFieldTypes->map(function ($fieldType) use ($masterOptions) {

            if ($fieldType->source_master && isset($masterOptions[$fieldType->source_master])) {
                $fieldType->options = $masterOptions[$fieldType->source_master];
            }

            return $fieldType;
        });

        return view('apps-test-result-entry', compact('extraFieldTypes'));
    }

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD LIST
    |--------------------------------------------------------------------------
    */

    private const RANGE_DAYS = [
        '1' => 1,
        '3' => 3,
        '7' => 7,
        '30' => 30,
    ];

    public function list(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);

        $query = Invoice::where('invoice_type', 'DIAGNOSTIC')
            ->where('invoice_category', 'PATHOLOGY')
            ->where(function ($q) {
                $q->whereNull('cancelled')->orWhere('cancelled', '!=', 'Y');
            });

        // An invoice needs at least one PAT001 (or whichever item_codes are
        // test_parameter_required) line that isn't outsourced, otherwise
        // there is nothing to report on here. Non-Pathology (X-Ray, Dental,
        // Vaccination, ECG, etc.) has its own separate dashboard now -- see
        // NonPathologyReportController.
        $pathologyQualifyingItemCodes = InvoiceItemMaster::where('test_parameter_required', 'YES')
            ->pluck('item_code');

        $query->whereExists(function ($sub) use ($pathologyQualifyingItemCodes) {
            $sub->selectRaw('1')
                ->from('invoice_details')
                ->join('invoice_item_details', function ($join) {
                    $join->on('invoice_item_details.item_code', '=', 'invoice_details.item_code')
                        ->on('invoice_item_details.item_code_sub', '=', 'invoice_details.item_code_sub');
                })
                ->whereColumn('invoice_details.invoice_no', 'invoices.invoice_no')
                ->whereIn('invoice_details.item_code', $pathologyQualifyingItemCodes)
                ->where('invoice_item_details.is_outsourced', 0)
                ->where('invoice_item_details.is_report_not_required', 0);
        });

        $range = $request->get('range', '3');

        if ($range !== 'all' && isset(self::RANGE_DAYS[$range])) {
            $query->whereDate('invoice_date', '>=', now()->subDays(self::RANGE_DAYS[$range] - 1)->toDateString());
        }

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhere('patient_name', 'like', "%{$search}%")
                    ->orWhere('patient_mobile_no', 'like', "%{$search}%")
                    // Full or part of any billed test's description.
                    ->orWhereExists(function ($sub) use ($search) {
                        $sub->selectRaw('1')
                            ->from('invoice_details as sd')
                            ->whereColumn('sd.invoice_no', 'invoices.invoice_no')
                            ->where('sd.item_description', 'like', "%{$search}%");
                    });
            });
        }

        $query->orderByDesc('invoice_date')->orderByDesc('id');

        // "Confirmed" is the stored invoices.report_status = 'Complete'
        // (every qualifying report confirmed, or the legacy whole-invoice
        // confirmation), kept current by InvoiceReportStatusRecorder -- so
        // the filter and pagination run in SQL, not over every row.
        $confirmedFilter = $request->get('confirmed');

        if (in_array($confirmedFilter, ['yes', 'no'], true)) {

            // Invoices never recorded yet have no stored status; fill those
            // in first so they land on the right side of the filter.
            $recorder = app(InvoiceReportStatusRecorder::class);

            (clone $query)
                ->where(function ($q) {
                    $q->whereNull('report_status')->orWhereNull('report_outsourced_tests');
                })
                ->get()
                ->each(fn ($invoice) => $recorder->refreshInvoice($invoice));

            $confirmedFilter === 'yes'
                ? $query->where('report_status', 'Complete')
                : $query->where('report_status', '!=', 'Complete');
        }

        $invoices = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $this->toRows($invoices->getCollection()),
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'total' => $invoices->total(),
            ]
        ]);
    }

    /**
     * Dashboard rows for one page of invoices. Result status/counts come
     * from the stored invoices.report_* columns (same source as the Test
     * Report Dashboard, so both always agree); the description and category
     * text -- every billed line regardless of category -- plus the legacy
     * Created Offline flag are fetched for the whole page in 3 queries
     * instead of several per invoice.
     */
    private function toRows($invoices)
    {
        if ($invoices->isEmpty()) {
            return collect();
        }

        $recorder = app(InvoiceReportStatusRecorder::class);
        $invoiceNos = $invoices->pluck('invoice_no');

        // A package is billed as ONE line; its hidden component lines
        // (package_parent_item_code_sub set) and blank placeholder lines
        // are not real billed tests.
        $linesByInvoice = DB::table('invoice_details')
            ->whereIn('invoice_no', $invoiceNos)
            ->whereNotNull('item_code')
            ->where('item_code', '!=', '')
            ->whereNull('package_parent_item_code_sub')
            ->orderBy('id')
            ->get(['invoice_no', 'item_code', 'item_description'])
            ->groupBy('invoice_no');

        $categoryNames = InvoiceItemMaster::whereIn(
            'item_code',
            $linesByInvoice->flatten(1)->pluck('item_code')->unique()
        )->pluck('item_name', 'item_code');

        $offlineByInvoice = TestReportConfirmation::whereIn('invoice_no', $invoiceNos)
            ->pluck('created_offline', 'invoice_no');

        return $invoices->map(function ($invoice) use ($recorder, $linesByInvoice, $categoryNames, $offlineByInvoice) {

            [$resultStatus, , $resultsEntered] = $recorder->statusFor($invoice);

            $lines = $linesByInvoice->get($invoice->invoice_no, collect());

            $confirmed = $resultStatus === 'Complete';

            // Fully entered but not yet signed off reads as Complete in
            // this list, with the Confirmed flag still false.
            if ($resultStatus === 'Confirmation Pending') {
                $resultStatus = 'Complete';
            }

            return [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'invoice_date' => $invoice->invoice_date
                    ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y')
                    : null,
                'patient_name' => $invoice->patient_name,
                'patient_age' => $invoice->patient_age,
                'patient_gender' => $invoice->patient_gender,
                'patient_mobile_no' => $invoice->patient_mobile_no,
                'referred_doctor' => $invoice->referred_doctor,
                'test_category' => $lines->pluck('item_code')->unique()
                    ->map(fn ($code) => $categoryNames->get($code, $code))
                    ->implode(', '),
                'test_description' => $lines->pluck('item_description')->implode(', '),
                'total_tests' => $lines->count(),
                'results_entered' => $resultsEntered,
                'result_status' => $resultStatus,
                'confirmed' => $confirmed,
                'created_offline' => (bool) $offlineByInvoice->get($invoice->invoice_no),
            ];
        })->values();
    }

    /*
    |--------------------------------------------------------------------------
    | SEARCH INVOICE
    |--------------------------------------------------------------------------
    */

    public function search(Request $request, TestReportRowBuilder $rowBuilder)
    {
        $validator = Validator::make(
            $request->all(),
            ['invoice_no' => 'required']
        );

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }

        $invoice = Invoice::where(
            'invoice_no',
            trim($request->invoice_no)
        )->first();

        if (!$invoice) {

            return response()->json([
                'status' => false,
                'message' => 'Invoice not found.'
            ]);
        }

        $rows = $rowBuilder->buildRows($invoice);

        $confirmation = TestReportConfirmation::where(
            'invoice_no',
            $invoice->invoice_no
        )->first();

        return response()->json([
            'status' => true,
            'invoice' => [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'invoice_category' => $invoice->invoice_category,
                'invoice_date' => optional($invoice->invoice_date)
                    ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y')
                    : '',
                'patient_name' => $invoice->patient_name,
                'patient_age' => $invoice->patient_age,
                'patient_gender' => $invoice->patient_gender,
                'patient_mobile_no' => $invoice->patient_mobile_no,
                'referred_doctor' => $invoice->referred_doctor,
                'status' => $invoice->status,
                'confirmed' => (bool) $confirmation,
                'confirmed_at' => optional($confirmation?->confirmed_at)->format('d-m-Y H:i'),
            ],
            'data' => $rows
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATED OFFLINE
    |--------------------------------------------------------------------------
    | The report was written outside the system (system/internet trouble).
    | Stores the same invoice-level confirmation the old grid used, flagged
    | created_offline, so every screen reads the invoice as Complete and
    | Confirmed. There is nothing to print or WhatsApp from here.
    */

    public function markCreatedOffline(Request $request, AuditService $auditService, DiagnosticResultStatusService $statusService, InvoiceReportStatusRecorder $statusRecorder)
    {
        // Shared by the Pathology, USG and Cardiology dashboards.
        $invoice = Invoice::where('invoice_no', trim((string) $request->invoice_no))
            ->where('invoice_type', 'DIAGNOSTIC')
            ->first();

        if (!$invoice || $invoice->cancelled === 'Y') {

            return response()->json([
                'status' => false,
                'message' => 'Invoice not found.'
            ]);
        }

        // USG / Cardiology are marked per test, so the other tests on the
        // invoice (e.g. an X-Ray) keep their own status.
        $scope = $request->input('scope');

        if (in_array($scope, ['usg', 'cardiology'], true)) {

            return $this->markLinesCreatedOffline($invoice, $scope, $auditService, $statusService, $statusRecorder);
        }

        // Pathology: whole invoice (Pathology and Non-Pathology can't share an invoice).
        if ($invoice->invoice_category !== 'PATHOLOGY') {

            return response()->json([
                'status' => false,
                'message' => 'Invoice not found.'
            ]);
        }

        if ($statusService->qualifyingLinesQuery($invoice)->count() === 0) {

            return response()->json([
                'status' => false,
                'message' => 'This invoice has no in-house tests to report.'
            ]);
        }

        if (TestReportConfirmation::where('invoice_no', $invoice->invoice_no)->exists()) {

            return response()->json([
                'status' => false,
                'message' => 'This report is already confirmed.'
            ]);
        }

        TestReportConfirmation::create([
            'invoice_no' => $invoice->invoice_no,
            'confirmed_by' => Auth::id(),
            'confirmed_at' => now(),
            'created_offline' => true,
        ]);

        $auditService->logAction(
            self::MODULE_CODE,
            $invoice,
            'CONFIRM',
            'Test report marked as Created Offline (completed and confirmed without in-system entry)'
        );

        // Dashboards read the stored status; refresh it now.
        $statusRecorder->refreshInvoice($invoice);

        return response()->json([
            'status' => true,
            'message' => 'Report marked as Created Offline.'
        ]);
    }

    /**
     * Created Offline for the USG or Cardiology tests of one invoice: each
     * such test not yet confirmed in the system gets a report_created_offline
     * row. Everything else on the invoice is left as it is, and the reply
     * names the tests still pending.
     */
    private function markLinesCreatedOffline(
        Invoice $invoice,
        string $scope,
        AuditService $auditService,
        DiagnosticResultStatusService $statusService,
        InvoiceReportStatusRecorder $statusRecorder
    ) {
        $lines = $statusService->qualifyingLinesQuery($invoice);

        $scope === 'usg'
            ? $lines->where('d.item_code', 'USG001')
            : $lines->whereIn('d.item_code_sub', \App\Support\CardiologyReportFields::ITEM_CODE_SUBS);

        $findingsTable = $scope === 'usg' ? 'usg_report_findings' : 'cardiology_report_findings';

        $lines = $lines->get(['d.id as invoice_detail_id', 'd.item_description']);

        if ($lines->isEmpty()) {

            return response()->json([
                'status' => false,
                'message' => 'This invoice has no ' . ($scope === 'usg' ? 'USG' : 'Cardiology') . ' tests to report.'
            ]);
        }

        $ids = $lines->pluck('invoice_detail_id');

        $done = DB::table($findingsTable)
            ->whereIn('invoice_detail_id', $ids)
            ->whereNotNull('confirmed_at')
            ->pluck('invoice_detail_id')
            ->merge(DB::table('report_created_offline')->whereIn('invoice_detail_id', $ids)->pluck('invoice_detail_id'));

        $open = $lines->whereNotIn('invoice_detail_id', $done->all());

        if ($open->isEmpty()) {

            return response()->json([
                'status' => false,
                'message' => 'These tests are already confirmed.'
            ]);
        }

        foreach ($open as $line) {

            DB::table('report_created_offline')->insert([
                'invoice_no' => $invoice->invoice_no,
                'invoice_detail_id' => $line->invoice_detail_id,
                'report_type' => strtoupper($scope),
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $auditService->logAction(
            self::MODULE_CODE,
            $invoice,
            'CONFIRM',
            strtoupper($scope) . ' report marked as Created Offline: ' . $open->pluck('item_description')->implode(', ')
        );

        $statusRecorder->refreshInvoice($invoice);

        // Other in-house tests on this invoice that are still not confirmed.
        $stillPending = collect(app(\App\Services\InvoiceReportItemsService::class)->forInvoice($invoice))
            ->where('confirmed', false)
            ->pluck('item_description')
            ->values();

        $message = strtoupper($scope) . ' report marked as Created Offline.';

        if ($stillPending->isNotEmpty()) {
            $message .= ' Still pending on this invoice: ' . $stillPending->implode(', ') . '.';
        }

        return response()->json([
            'status' => true,
            'message' => $message,
            'still_pending' => $stillPending,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CONFIRM REPORT
    |--------------------------------------------------------------------------
    */

    public function confirm(Request $request, AuditService $auditService, TestReportRowBuilder $rowBuilder, WatiService $wati)
    {
        $validator = Validator::make(
            $request->all(),
            ['invoice_no' => 'required']
        );

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }

        $invoice = Invoice::where(
            'invoice_no',
            trim($request->invoice_no)
        )->first();

        if (!$invoice) {

            return response()->json([
                'status' => false,
                'message' => 'Invoice not found.'
            ]);
        }

        // Without this, a report can be confirmed (and therefore printed)
        // with zero results ever saved -- exactly what happened to
        // LAB/0726/001/0001, which was confirmed on 2026-07-06 with no
        // test_result_entries rows at all.
        $rows = $rowBuilder->buildRows($invoice);

        $hasAnyResult = collect($rows)->contains(function ($row) {

            if (!empty($row['result_value']) || !empty($row['remarks'])) {
                return true;
            }

            return collect($row['analytes'])->contains(
                fn ($analyte) => !empty($analyte['result_value']) || !empty($analyte['remarks'])
            );
        });

        if (!$hasAnyResult) {

            return response()->json([
                'status' => false,
                'message' => 'Cannot confirm this report -- no test results have been entered yet.'
            ]);
        }

        $confirmation = TestReportConfirmation::updateOrCreate(
            ['invoice_no' => $invoice->invoice_no],
            [
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ]
        );

        $auditService->logAction(
            self::MODULE_CODE,
            $invoice,
            'CONFIRM',
            'Test report confirmed and locked'
        );

        $whatsappStatus = $this->autoSendReportWhatsapp($invoice, $rowBuilder, $wati, $auditService);

        return response()->json([
            'status' => true,
            'message' => 'Test report confirmed.',
            'data' => [
                'confirmed' => true,
                'confirmed_at' => $confirmation->confirmed_at->format('d-m-Y H:i'),
                'whatsapp_status' => $whatsappStatus,
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE RESULT (atomic test, or one analyte within a panel test)
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, AuditService $auditService)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'invoice_detail_id' => 'required|integer',
                'analyte_id' => 'nullable|integer',
                'result_value' => 'nullable|max:191',
                'remarks' => 'nullable|max:1000',
            ]
        );

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }

        $detail = DB::table('invoice_details')
            ->where('id', $request->invoice_detail_id)
            ->first();

        if (!$detail) {

            return response()->json([
                'status' => false,
                'message' => 'Invoice line item not found.'
            ]);
        }

        if (TestReportConfirmation::where('invoice_no', $detail->invoice_no)->exists()) {

            return response()->json([
                'status' => false,
                'message' => 'This test report is already confirmed and locked for editing.'
            ]);
        }

        if (\App\Support\DiagnosticReportGuard::invoiceIsCancelled($detail->invoice_no)) {

            return response()->json([
                'status' => false,
                'message' => 'This invoice has been cancelled -- no report can be created or edited for it.'
            ]);
        }

        $analyteId = (int) ($request->analyte_id ?? 0);

        if ($analyteId > 0) {

            $analyte = TestAnalyte::find($analyteId);

            if (!$analyte) {

                return response()->json([
                    'status' => false,
                    'message' => 'Analyte not found.'
                ]);
            }

            $description = $analyte->analyte_name;
            $uom = $analyte->uom;
            $rangeMale = $analyte->range_male;
            $rangeFemale = $analyte->range_female;
            $rangeCommon = $analyte->range_common;
            $method = $analyte->method;

        } else {

            $itemDetail = InvoiceItemDetail::where('item_code', $detail->item_code)
                ->where('item_code_sub', $detail->item_code_sub)
                ->first();

            $description = $detail->item_description;
            $uom = $itemDetail->uom ?? null;
            $rangeMale = $itemDetail->range_male ?? null;
            $rangeFemale = $itemDetail->range_female ?? null;
            $rangeCommon = $itemDetail->range_common ?? null;
            $method = $itemDetail->method ?? null;
        }

        $existing = TestResultEntry::where('invoice_detail_id', $detail->id)
            ->where('analyte_id', $analyteId)
            ->first();

        $oldData = $existing ? $existing->toArray() : [];

        $entry = TestResultEntry::updateOrCreate(
            ['invoice_detail_id' => $detail->id, 'analyte_id' => $analyteId],
            [
                'invoice_no' => $detail->invoice_no,
                'item_code' => $detail->item_code,
                'item_code_sub' => $detail->item_code_sub,
                'item_description' => $description,
                'uom' => $uom,
                'range_male' => $rangeMale,
                'range_female' => $rangeFemale,
                'range_common' => $rangeCommon,
                'method' => $method,
                'result_value' => $request->result_value,
                'remarks' => $request->remarks,
                'updated_by' => Auth::id(),
                'created_by' => $existing->created_by ?? Auth::id(),
            ]
        );

        if ($entry->wasRecentlyCreated) {

            $auditService->logCreate(
                self::MODULE_CODE,
                $entry,
                $entry->only($entry->getFillable()),
                'Test result entered'
            );

        } else {

            $auditService->logUpdate(
                self::MODULE_CODE,
                $entry,
                $oldData,
                $entry->only($entry->getFillable()),
                'Test result updated'
            );
        }

        return response()->json([
            'status' => true,
            'message' => 'Result saved successfully.',
            'data' => [
                'result_id' => $entry->id,
                'result_value' => $entry->result_value,
                'remarks' => $entry->remarks,
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE RESULT
    |--------------------------------------------------------------------------
    */

    public function destroy($id, AuditService $auditService)
    {
        $entry = TestResultEntry::findOrFail($id);

        if (TestReportConfirmation::where('invoice_no', $entry->invoice_no)->exists()) {

            return response()->json([
                'status' => false,
                'message' => 'This test report is already confirmed and locked for editing.'
            ]);
        }

        $oldData = $entry->only($entry->getFillable());

        $entry->delete();

        $auditService->logDelete(
            self::MODULE_CODE,
            $entry,
            $oldData,
            'Test result cleared'
        );

        return response()->json([
            'status' => true,
            'message' => 'Result cleared successfully.'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE EXTRA PARAMETER VALUE (once per billed test line)
    |--------------------------------------------------------------------------
    */

    public function saveExtraValue(Request $request, AuditService $auditService)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'invoice_detail_id' => 'required|integer',
                'test_extra_field_type_id' => 'required|integer|exists:test_extra_field_types,id',
                'value' => 'nullable|string',
            ]
        );

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }

        $detail = DB::table('invoice_details')
            ->where('id', $request->invoice_detail_id)
            ->first();

        if (!$detail) {

            return response()->json([
                'status' => false,
                'message' => 'Invoice line item not found.'
            ]);
        }

        if (TestReportConfirmation::where('invoice_no', $detail->invoice_no)->exists()) {

            return response()->json([
                'status' => false,
                'message' => 'This test report is already confirmed and locked for editing.'
            ]);
        }

        $existing = TestResultExtraValue::where('invoice_detail_id', $detail->id)
            ->where('test_extra_field_type_id', $request->test_extra_field_type_id)
            ->first();

        $oldData = $existing ? $existing->toArray() : [];

        $entry = TestResultExtraValue::updateOrCreate(
            [
                'invoice_detail_id' => $detail->id,
                'test_extra_field_type_id' => $request->test_extra_field_type_id,
            ],
            [
                'value' => $request->value,
                'updated_by' => Auth::id(),
                'created_by' => $existing->created_by ?? Auth::id(),
            ]
        );

        if ($entry->wasRecentlyCreated) {

            $auditService->logCreate(
                self::MODULE_CODE,
                $entry,
                $entry->only($entry->getFillable()),
                'Extra parameter value entered'
            );

        } else {

            $auditService->logUpdate(
                self::MODULE_CODE,
                $entry,
                $oldData,
                $entry->only($entry->getFillable()),
                'Extra parameter value updated'
            );
        }

        return response()->json([
            'status' => true,
            'message' => 'Parameter saved successfully.',
            'data' => [
                'id' => $entry->id,
                'value' => $entry->value,
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE EXTRA PARAMETER VALUE
    |--------------------------------------------------------------------------
    */

    public function destroyExtraValue($id, AuditService $auditService)
    {
        $entry = TestResultExtraValue::findOrFail($id);

        $detail = DB::table('invoice_details')->where('id', $entry->invoice_detail_id)->first();

        if ($detail && TestReportConfirmation::where('invoice_no', $detail->invoice_no)->exists()) {

            return response()->json([
                'status' => false,
                'message' => 'This test report is already confirmed and locked for editing.'
            ]);
        }

        $oldData = $entry->only($entry->getFillable());

        $entry->delete();

        $auditService->logDelete(
            self::MODULE_CODE,
            $entry,
            $oldData,
            'Extra parameter value removed'
        );

        return response()->json([
            'status' => true,
            'message' => 'Parameter removed successfully.'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PRINT REPORT (PDF)
    |--------------------------------------------------------------------------
    */

    public function printReport($id, AuditService $auditService, TestReportRowBuilder $rowBuilder)
    {
        $invoice = Invoice::findOrFail($id);

        $confirmation = TestReportConfirmation::where('invoice_no', $invoice->invoice_no)->first();

        if (!$confirmation) {

            abort(403, 'Test report must be confirmed before printing.');
        }

        if ($confirmation->created_offline) {

            abort(403, 'This report was created offline -- there is nothing to print from the system.');
        }

        $pdf = $this->buildInvoicePdf($invoice, $rowBuilder);

        $auditService->logAction(
            self::MODULE_CODE,
            $invoice,
            'PRINT',
            'Test report printed'
        );

        \App\Services\ReportPrintDueLogger::record(
            $invoice,
            DB::table('invoice_details')
                ->where('invoice_no', $invoice->invoice_no)
                ->whereNotNull('item_code')
                ->where('item_code', '!=', '')
                ->whereNull('package_parent_item_code_sub')
                ->pluck('item_description'),
            'LEGACY'
        );

        $safeFileName = str_replace(
            ['/', '\\'],
            '-',
            $invoice->invoice_no
        ) . '-report.pdf';

        return $pdf->stream($safeFileName);
    }

    /**
     * The staff-print PDF of an invoice confirmed under the legacy
     * whole-invoice system, page-numbered -- shared with the Test Report
     * Dashboard's "print selected items".
     */
    public function buildInvoicePdf(Invoice $invoice, TestReportRowBuilder $rowBuilder)
    {
        $tests = $rowBuilder->buildRows($invoice);

        $pdf = Pdf::loadView(
            'apps-test-result-entry-pdf',
            compact('invoice', 'tests')
        );

        PdfPageNumbers::add($pdf);

        return $pdf;
    }

    /*
    |--------------------------------------------------------------------------
    | SEND REPORT VIA WHATSAPP
    |--------------------------------------------------------------------------
    */

    public function sendWhatsapp($id, AuditService $auditService, TestReportRowBuilder $rowBuilder, WatiService $wati)
    {
        $invoice = Invoice::findOrFail($id);

        $confirmation = TestReportConfirmation::where('invoice_no', $invoice->invoice_no)->first();

        if (!$confirmation) {

            return response()->json([
                'status' => false,
                'message' => 'Test report must be confirmed before sending.'
            ]);
        }

        if ($confirmation->created_offline) {

            return response()->json([
                'status' => false,
                'message' => 'This report was created offline -- there is nothing to send from the system.'
            ]);
        }

        if ($blocked = ReportWhatsappDueGate::manualBlockMessage($invoice->invoice_no)) {

            return response()->json(['status' => false, 'message' => $blocked]);
        }

        if (!WhatsappAutoSendSetting::isEnabled('TEST_REPORT')) {

            WhatsappAutoSendSetting::logSkipped('TEST_REPORT', $invoice->invoice_no, $invoice->patient_mobile_no, $invoice->patient_name);

            return response()->json([
                'status' => false,
                'message' => 'WhatsApp sending for Test Report is currently disabled by Admin. Enable it in WhatsApp Auto-Send settings to send.'
            ]);
        }

        $sent = $this->sendReportWhatsapp($invoice, $rowBuilder, $wati, $auditService);

        return response()->json([
            'status' => $sent,
            'message' => $sent
                ? 'Report sent via WhatsApp successfully.'
                : 'Unable to send report via WhatsApp.'
        ], $sent ? 200 : 500);
    }

    /**
     * Shared by the manual "Send WhatsApp" button (always runs, ungated)
     * and the automatic send fired from confirm() (gated by
     * WhatsappAutoSendSetting -- checked by the caller, not in here, so
     * this method itself always actually attempts the send).
     */
    private function sendReportWhatsapp(
        Invoice $invoice,
        TestReportRowBuilder $rowBuilder,
        WatiService $wati,
        AuditService $auditService
    ): bool {
        try {

            $tests = $rowBuilder->buildRows($invoice);

            $pdf = Pdf::loadView(
                'apps-test-result-entry-pdf',
                compact('invoice', 'tests')
            );

            PdfPageNumbers::add($pdf);

            $fileName = str_replace('/', '_', $invoice->invoice_no)
                . '-report.pdf';

            $pdfPath = public_path('invoices/' . $fileName);

            $password = PdfPasswordProtectionService::passwordForMobile($invoice->patient_mobile_no);

            file_put_contents(
                $pdfPath,
                $password
                    ? PdfPasswordProtectionService::protect($pdf->output(), $password)
                    : $pdf->output()
            );

            $pdfUrl = asset('invoices/' . $fileName);

            $watiResponse = $wati->sendTemplateMessage(
                '91' . preg_replace('/\D/', '', $invoice->patient_mobile_no),
                config('services.wati.test_report_template_name'),
                config('services.wati.test_report_broadcast_name'),
                [
                    ['name' => '1', 'value' => $pdfUrl],
                    ['name' => '2', 'value' => $invoice->patient_name],
                    ['name' => '3', 'value' => PdfPasswordProtectionService::invoiceNoWithHint($invoice->invoice_no, (bool) $password)],
                ]
            );

            $sent = is_array($watiResponse) && ($watiResponse['result'] ?? false) === true;

            DB::table('whatsapp_message_logs')->insert([

                'invoice_no' => $invoice->invoice_no,
                'mobile_no' => $invoice->patient_mobile_no,
                'patient_name' => $invoice->patient_name,
                'message_type' => 'TEST_REPORT',
                'status' => $sent ? 'SENT' : 'FAILED',
                'response' => json_encode($watiResponse),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($sent) {

                $auditService->logAction(
                    self::MODULE_CODE,
                    $invoice,
                    'WHATSAPP',
                    'Test report sent via WhatsApp' . ($password ? ' (password-protected)' : ' (unprotected: no valid mobile number)')
                );
            }

            return $sent;

        } catch (\Exception $e) {

            \Log::error('Test Report WhatsApp Send Failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Gated entry point for the automatic trigger fired from confirm().
     * The manual sendWhatsapp() route above calls sendReportWhatsapp()
     * directly, ungated, so staff always retain a working resend button.
     */
    private function autoSendReportWhatsapp(
        Invoice $invoice,
        TestReportRowBuilder $rowBuilder,
        WatiService $wati,
        AuditService $auditService
    ): string {
        if (!WhatsappAutoSendSetting::isEnabled('TEST_REPORT')) {
            WhatsappAutoSendSetting::logSkipped('TEST_REPORT', $invoice->invoice_no, $invoice->patient_mobile_no, $invoice->patient_name);
            return 'skipped';
        }

        if ((float) $invoice->due_amount > 0) {
            ReportWhatsappDueGate::hold('TEST_REPORT', $invoice);
            return 'held_due';
        }

        return $this->sendReportWhatsapp($invoice, $rowBuilder, $wati, $auditService)
            ? 'sent'
            : 'failed';
    }

    /**
     * Sends the test report whose automatic WhatsApp was held for a
     * payment due, once the invoice is fully paid (see ReportWhatsappDueGate).
     * Still subject to the auto-send toggle -- a due clearing while the
     * category is switched off logs SKIPPED instead of sending, same as
     * any other automatic trigger.
     */
    public function releaseHeldWhatsapp(string $invoiceNo): void
    {
        if (!ReportWhatsappDueGate::takeHeld($invoiceNo, 'TEST_REPORT')) {
            return;
        }

        $invoice = Invoice::where('invoice_no', $invoiceNo)->first();

        if (!$invoice || !TestReportConfirmation::where('invoice_no', $invoiceNo)->exists()) {
            return;
        }

        if (!WhatsappAutoSendSetting::isEnabled('TEST_REPORT')) {
            WhatsappAutoSendSetting::logSkipped('TEST_REPORT', $invoice->invoice_no, $invoice->patient_mobile_no, $invoice->patient_name);
            return;
        }

        $this->sendReportWhatsapp($invoice, app(TestReportRowBuilder::class), app(WatiService::class), app(AuditService::class));
    }
}
