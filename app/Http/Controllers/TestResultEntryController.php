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
use App\Models\TestAnalyte;
use App\Models\TestExtraFieldType;
use App\Models\TestReportConfirmation;
use App\Models\TestResultEntry;
use App\Models\TestResultExtraValue;
use App\Services\AuditService;
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

        $invoiceCategory = $request->get('invoice_category', 'PATHOLOGY');

        $query = Invoice::where('invoice_type', 'DIAGNOSTIC')
            ->where('invoice_category', $invoiceCategory)
            ->where(function ($q) {
                $q->whereNull('cancelled')->orWhere('cancelled', '!=', 'Y');
            });

        if ($invoiceCategory === 'NON_PATHOLOGY') {

            // USG shares the NON_PATHOLOGY category with every other
            // non-pathology test, but has its own separate reporting
            // dashboard (UsgReportController) -- an invoice whose only
            // Non-Pathology lines are USG has nothing to do on this screen
            // and is excluded. Mirrors NonPathologyReportController's own
            // qualifying-set definition. An invoice that mixes USG with
            // other tests (e.g. USG + X-Ray) still belongs here for its
            // non-USG lines; NonPathologyReportController::search() already
            // filters those cards down to the non-USG lines only.
            $nonUsgQualifyingItemCodes = InvoiceItemMaster::where('test_parameter_required', '!=', 'YES')
                ->whereNotIn('item_code', ['USG001', 'DOC001'])
                ->pluck('item_code');

            $query->whereExists(function ($sub) use ($nonUsgQualifyingItemCodes) {
                $sub->selectRaw('1')
                    ->from('invoice_details')
                    ->whereColumn('invoice_details.invoice_no', 'invoices.invoice_no')
                    ->whereIn('invoice_details.item_code', $nonUsgQualifyingItemCodes);
            });
        }

        $range = $request->get('range', '3');

        if ($range !== 'all' && isset(self::RANGE_DAYS[$range])) {
            $query->whereDate('invoice_date', '>=', now()->subDays(self::RANGE_DAYS[$range] - 1)->toDateString());
        }

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhere('patient_name', 'like', "%{$search}%")
                    ->orWhere('patient_mobile_no', 'like', "%{$search}%");
            });
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

    /**
     * The result-status algorithm (N/A/Pending/Partial/Complete) mirrors
     * TestReportDashboardController's definition exactly, so a given
     * invoice shows the same status on both dashboards. It stays scoped to
     * $qualifyingItemCodes (today: only Pathology's PAT001, the only
     * item_code with test_parameter_required=YES) since that's the only
     * category with a structured parameter-entry grid to track completion
     * against -- Non-Pathology invoices (X-Ray, USG, Dental, etc.) have no
     * such grid and correctly stay 'N/A'.
     *
     * The displayed test count/description, however, is NOT scoped to
     * $qualifyingItemCodes -- it lists every billed line on the invoice
     * regardless of category, since Non-Pathology invoices still have real
     * billed tests worth showing even though none of them feed the
     * qualifying-based completion status above.
     *
     * @return array{0: string, 1: int, 2: int, 3: string, 4: string} [result_status, total_tests, results_entered, test_description, test_category]
     */
    private function resultStatusFor(Invoice $invoice, array $qualifyingItemCodes): array
    {
        $lineDetails = DB::table('invoice_details')
            ->where('invoice_no', $invoice->invoice_no)
            // Excludes stray blank placeholder lines (no item_code at all)
            // that aren't real billed tests -- see e.g. invoice_details id
            // 1031 on LAB/0826/082/0004.
            ->whereNotNull('item_code')
            ->where('item_code', '!=', '')
            ->get(['item_code', 'item_description']);

        // Non-Pathology mixes several categories (X-Ray, Cardiology, USG,
        // etc.) on the same invoice/table, unlike Pathology's single
        // category -- resolved from invoice_item_masters.item_name, same
        // lookup used for the diagnostic invoice PDF's Test Category column.
        $testCategoryNames = InvoiceItemMaster::whereIn('item_code', $lineDetails->pluck('item_code')->unique())
            ->pluck('item_name', 'item_code');

        $testCategory = $lineDetails->pluck('item_code')->unique()
            ->map(fn ($code) => $testCategoryNames->get($code, $code))
            ->implode(', ');

        $qualifyingTotal = DB::table('invoice_details')
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

        $resultStatus = $qualifyingTotal === 0
            ? 'N/A'
            : ($resultsEntered <= 0
                ? 'Pending'
                : ($resultsEntered >= $qualifyingTotal ? 'Complete' : 'Partial'));

        return [
            $resultStatus,
            $lineDetails->count(),
            $resultsEntered,
            $lineDetails->pluck('item_description')->implode(', '),
            $testCategory,
        ];
    }

    private function toRow(Invoice $invoice, array $qualifyingItemCodes): array
    {
        [$resultStatus, $totalTests, $resultsEntered, $testDescription, $testCategory] =
            $this->resultStatusFor($invoice, $qualifyingItemCodes);

        $confirmed = TestReportConfirmation::where('invoice_no', $invoice->invoice_no)->exists();

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
            'test_category' => $testCategory,
            'test_description' => $testDescription,
            'total_tests' => $totalTests,
            'results_entered' => $resultsEntered,
            'result_status' => $resultStatus,
            'confirmed' => $confirmed,
        ];
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
    | CONFIRM REPORT
    |--------------------------------------------------------------------------
    */

    public function confirm(Request $request, AuditService $auditService, TestReportRowBuilder $rowBuilder)
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

        return response()->json([
            'status' => true,
            'message' => 'Test report confirmed.',
            'data' => [
                'confirmed' => true,
                'confirmed_at' => $confirmation->confirmed_at->format('d-m-Y H:i'),
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

        if (!TestReportConfirmation::where('invoice_no', $invoice->invoice_no)->exists()) {

            abort(403, 'Test report must be confirmed before printing.');
        }

        $tests = $rowBuilder->buildRows($invoice);

        $pdf = Pdf::loadView(
            'apps-test-result-entry-pdf',
            compact('invoice', 'tests')
        );

        $auditService->logAction(
            self::MODULE_CODE,
            $invoice,
            'PRINT',
            'Test report printed'
        );

        $safeFileName = str_replace(
            ['/', '\\'],
            '-',
            $invoice->invoice_no
        ) . '-report.pdf';

        return $pdf->stream($safeFileName);
    }

    /*
    |--------------------------------------------------------------------------
    | SEND REPORT VIA WHATSAPP
    |--------------------------------------------------------------------------
    */

    public function sendWhatsapp($id, AuditService $auditService, TestReportRowBuilder $rowBuilder, WatiService $wati)
    {
        try {

            $invoice = Invoice::findOrFail($id);

            if (!TestReportConfirmation::where('invoice_no', $invoice->invoice_no)->exists()) {

                return response()->json([
                    'status' => false,
                    'message' => 'Test report must be confirmed before sending.'
                ]);
            }

            $tests = $rowBuilder->buildRows($invoice);

            $pdf = Pdf::loadView(
                'apps-test-result-entry-pdf',
                compact('invoice', 'tests')
            );

            $fileName = str_replace('/', '_', $invoice->invoice_no)
                . '-report.pdf';

            $pdfPath = public_path('invoices/' . $fileName);

            $pdf->save($pdfPath);

            $pdfUrl = asset('invoices/' . $fileName);

            $watiResponse = $wati->sendTemplateMessage(
                '91' . preg_replace('/\D/', '', $invoice->patient_mobile_no),
                config('services.wati.test_report_template_name'),
                config('services.wati.test_report_broadcast_name'),
                [
                    ['name' => '1', 'value' => $pdfUrl],
                    ['name' => '2', 'value' => $invoice->patient_name],
                    ['name' => '3', 'value' => $invoice->invoice_no],
                ]
            );

            $sent = is_array($watiResponse) && ($watiResponse['result'] ?? false) === true;

            DB::table('whatsapp_message_logs')->insert([

                'invoice_no' => $invoice->invoice_no,
                'mobile_no' => $invoice->patient_mobile_no,
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
                    'Test report sent via WhatsApp'
                );
            }

            return response()->json([
                'status' => $sent,
                'message' => $sent
                    ? 'Report sent via WhatsApp successfully.'
                    : 'Unable to send report via WhatsApp.'
            ], $sent ? 200 : 500);

        } catch (\Exception $e) {

            \Log::error('Test Report WhatsApp Send Failed: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Unable to send report via WhatsApp.'
            ], 500);
        }
    }
}
