<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\TestGroupMaster;
use App\Models\TestReportConfirmation;
use App\Services\AuditService;
use App\Services\TestReportRowBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DiagnosticTestReportController extends Controller
{
    private const MODULE_CODE = 'DIAGNOSTIC_TEST_REPORT';

    public function index()
    {
        return view('apps-diagnostic-test-report');
    }

    public function search(Request $request, TestReportRowBuilder $rowBuilder)
    {
        $validator = Validator::make($request->all(), [
            'invoice_no' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $invoice = Invoice::where('invoice_no', $request->invoice_no)->first();

        if (!$invoice) {
            return response()->json([
                'status' => false,
                'message' => 'Invoice not found.'
            ], 404);
        }

        $rows = $rowBuilder->buildRows($invoice);

        if (empty($rows)) {
            return response()->json([
                'status' => false,
                'message' => 'No diagnostic tests found for this invoice.'
            ], 404);
        }

        $isConfirmed = TestReportConfirmation::where('invoice_no', $invoice->invoice_no)->exists();

        $groups = [];

        foreach ($rows as $row) {

            $key = $row['test_group_code'] ?? 'OTHER';

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'test_group_code' => $row['test_group_code'],
                    'test_group_name' => $row['test_group_name'] ?: 'Other Tests',
                    'test_count' => 0,
                    'result_count' => 0
                ];
            }

            // Count at the leaf level: an analyte-bearing test contributes
            // one countable unit per analyte. The main test's own result
            // slot is optional when analytes exist, so it only counts as
            // a leaf once it's actually been used (filled result/remarks)
            // — otherwise every panel test would look permanently
            // incomplete for a field nobody intends to fill.
            $mainSlotIsOptionalAndUnused = $row['has_analytes']
                && empty($row['result_value'])
                && empty($row['remarks']);

            $leaves = $row['has_analytes'] ? $row['analytes'] : [];

            if (!$mainSlotIsOptionalAndUnused) {
                $leaves[] = $row;
            }

            foreach ($leaves as $leaf) {

                $groups[$key]['test_count']++;

                if (!is_null($leaf['result_value']) && $leaf['result_value'] !== '') {
                    $groups[$key]['result_count']++;
                }
            }
        }

        return response()->json([
            'status' => true,
            'invoice' => [
                'invoice_no' => $invoice->invoice_no,
                'invoice_date' => optional($invoice->invoice_date)->format('d-m-Y') ?? $invoice->invoice_date,
                'patient_name' => $invoice->patient_name,
                'patient_id' => $invoice->patient_id
            ],
            'confirmed' => $isConfirmed,
            'groups' => array_values($groups)
        ]);
    }

    public function printGroup(Request $request, AuditService $auditService, TestReportRowBuilder $rowBuilder)
    {
        $validator = Validator::make($request->all(), [
            'invoice_no' => 'required|string',
            'test_group_code' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            abort(422, $validator->errors()->first());
        }

        $invoice = Invoice::where('invoice_no', $request->invoice_no)->firstOrFail();

        if (!TestReportConfirmation::where('invoice_no', $invoice->invoice_no)->exists()) {
            abort(403, 'Test report must be confirmed before printing.');
        }

        $allRows = $rowBuilder->buildRows($invoice);

        $testGroupCode = $request->test_group_code;

        $tests = array_values(array_filter($allRows, function ($row) use ($testGroupCode) {
            return $testGroupCode
                ? ($row['test_group_code'] == $testGroupCode)
                : empty($row['test_group_code']);
        }));

        if (empty($tests)) {
            abort(404, 'No tests found for the selected group.');
        }

        $groupName = $tests[0]['test_group_name'] ?: 'Other Tests';

        $additionalContent = null;

        if ($testGroupCode) {
            $additionalContent = optional(TestGroupMaster::find($testGroupCode))->additional_content;
        }

        $pdf = Pdf::loadView('apps-diagnostic-test-report-pdf', [
            'invoice' => $invoice,
            'tests' => $tests,
            'groupName' => $groupName,
            'additionalContent' => $additionalContent
        ]);

        $auditService->logAction(
            self::MODULE_CODE,
            $invoice,
            'PRINT',
            'Diagnostic test report printed for group: ' . $groupName
        );

        $safeFileName = str_replace(
            ['/', '\\', ' '],
            '-',
            $invoice->invoice_no . '-' . $groupName
        ) . '-report.pdf';

        return $pdf->stream($safeFileName);
    }
}
