<?php

namespace App\Http\Controllers;

use App\Models\ImpressionMaster;
use App\Models\InstrumentMaster;
use App\Models\InvoiceItemDetail;
use App\Models\InvoiceItemMaster;
use App\Models\KitMaster;
use App\Models\MicroscopyMaster;
use App\Models\NoteMaster;
use App\Models\TestExtraFieldType;
use App\Models\TestReportTemplate;
use App\Models\TestReportTemplateValue;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class TestReportTemplateController extends Controller
{
    private const MODULE_CODE = 'TEST_REPORT_TEMPLATE';

    public function index()
    {
        $qualifyingItemCodes = InvoiceItemMaster::where('test_parameter_required', 'YES')
            ->where('item_code', '!=', 'USG001')
            ->pluck('item_code');

        $testTypes = InvoiceItemDetail::whereIn('item_code', $qualifyingItemCodes)
            ->where('status', 'Y')
            ->orderBy('item_description_sub')
            ->get(['item_code_sub', 'item_description_sub'])
            ->unique('item_code_sub')
            ->values();

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

        return view('apps-test-report-template', compact('testTypes', 'extraFieldTypes'));
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = TestReportTemplate::query();

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('item_code_sub', 'like', "%{$search}%");
            });
        }

        $templates = $query
            ->orderBy('item_code_sub')
            ->orderBy('title')
            ->paginate($perPage);

        $itemCodeSubs = collect($templates->items())->pluck('item_code_sub')->unique();

        $testTypeNames = InvoiceItemDetail::whereIn('item_code_sub', $itemCodeSubs)
            ->pluck('item_description_sub', 'item_code_sub');

        $templates->getCollection()->transform(function ($row) use ($testTypeNames) {

            $row->test_type_name = $testTypeNames->get($row->item_code_sub);

            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $templates->items(),
            'pagination' => [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'total' => $templates->total()
            ]
        ]);
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validateTemplate($request);

        DB::beginTransaction();

        try {

            $template = TestReportTemplate::create([

                'title' => trim($data['title']),
                'item_code_sub' => $data['item_code_sub'],
                'remarks' => $data['remarks'] ?? null,
                'status' => $data['status'],

                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $this->syncParameters($template, $data['parameters'] ?? []);

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $template,
                array_merge($template->only($template->getFillable()), ['parameters' => $data['parameters'] ?? []]),
                'Test Report Template created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Test Report Template created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save Test Report Template.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $template = TestReportTemplate::with('values')->findOrFail($id);

        $parameters = $template->values->map(function ($v) {
            return [
                'field_type_id' => $v->test_extra_field_type_id,
                'value' => $v->value,
            ];
        });

        return response()->json([
            'status' => true,
            'data' => array_merge($template->toArray(), ['parameters' => $parameters]),
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $data = $this->validateTemplate($request);

        DB::beginTransaction();

        try {

            $template = TestReportTemplate::findOrFail($id);

            $oldData = $template->only($template->getFillable());

            $template->update([

                'title' => trim($data['title']),
                'item_code_sub' => $data['item_code_sub'],
                'remarks' => $data['remarks'] ?? null,
                'status' => $data['status'],

                'updated_by' => Auth::id(),
            ]);

            $this->syncParameters($template, $data['parameters'] ?? []);

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $template,
                $oldData,
                array_merge($template->only($template->getFillable()), ['parameters' => $data['parameters'] ?? []]),
                'Test Report Template updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Test Report Template updated successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update Test Report Template.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $template = TestReportTemplate::findOrFail($id);

            $oldData = $template->only($template->getFillable());

            $template->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $template,
                $oldData,
                'Test Report Template deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Test Report Template deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete Test Report Template.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FOR TEST -- lightweight lookup used by the Test Result Entry screen's
    | bundled template picker (separate from the paginated admin list() above).
    |--------------------------------------------------------------------------
    */

    public function forTest($itemCodeSub)
    {
        $templates = TestReportTemplate::with('values.fieldType')
            ->where('item_code_sub', $itemCodeSub)
            ->where('status', 'ACTIVE')
            ->orderBy('title')
            ->get();

        $data = $templates->map(function ($template) {

            return [
                'id' => $template->id,
                'title' => $template->title,
                'remarks' => $template->remarks,
                'parameters' => $template->values->map(function ($v) {
                    return [
                        'field_type_id' => $v->test_extra_field_type_id,
                        'field_name' => $v->fieldType->field_name ?? '',
                        'input_type' => $v->fieldType->input_type ?? 'TEXT',
                        'value' => $v->value,
                    ];
                }),
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'title' => 'required|max:150',
            'item_code_sub' => 'required|exists:invoice_item_details,item_code_sub',
            'remarks' => 'nullable|string',
            'status' => 'required|in:ACTIVE,INACTIVE',
            'parameters' => 'nullable|array',
            'parameters.*.field_type_id' => 'required_with:parameters|exists:test_extra_field_types,id',
            'parameters.*.value' => 'nullable|string',
        ]);
    }

    private function syncParameters(TestReportTemplate $template, array $parameters): void
    {
        TestReportTemplateValue::where('test_report_template_id', $template->id)->delete();

        foreach ($parameters as $param) {

            $value = trim((string) ($param['value'] ?? ''));

            if ($value === '') {
                continue;
            }

            TestReportTemplateValue::create([
                'test_report_template_id' => $template->id,
                'test_extra_field_type_id' => $param['field_type_id'],
                'value' => $value,
            ]);
        }
    }
}
