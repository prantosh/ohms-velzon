<?php

namespace App\Http\Controllers;

use App\Models\TestExtraFieldType;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class TestExtraFieldTypeController extends Controller
{
    private const MODULE_CODE = 'TEST_EXTRA_FIELD_TYPE';

    public function index()
    {
        return view('apps-test-extra-field-type');
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = TestExtraFieldType::query();

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where('field_name', 'like', "%{$search}%");
        }

        $fields = $query
            ->orderBy('sort_order')
            ->orderBy('field_name')
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $fields->items(),
            'pagination' => [
                'current_page' => $fields->currentPage(),
                'last_page' => $fields->lastPage(),
                'total' => $fields->total()
            ]
        ]);
    }

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'field_name' => 'required|max:100|unique:test_extra_field_types,field_name',
            'input_type' => 'required|in:TEXT,TEXTAREA,SELECT',
            'source_master' => 'required_if:input_type,SELECT|nullable|in:instrument,kit,note,microscopy,impression',
            'sort_order' => 'nullable|integer',
            'status' => 'required|in:ACTIVE,INACTIVE'
        ]);

        DB::beginTransaction();

        try {

            $field = TestExtraFieldType::create([

                'field_name' => trim($request->field_name),

                'input_type' => $request->input_type,

                'source_master' => $request->input_type === 'SELECT' ? $request->source_master : null,

                'sort_order' => $request->sort_order ?? 0,

                'status' => $request->status,

                'created_by' => Auth::id(),

                'updated_by' => Auth::id()
            ]);

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $field,
                $field->only($field->getFillable()),
                'Test extra field type created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Extra field created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save extra field.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $field = TestExtraFieldType::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $field
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'field_name' => 'required|max:100|unique:test_extra_field_types,field_name,' . $id,
            'input_type' => 'required|in:TEXT,TEXTAREA,SELECT',
            'source_master' => 'required_if:input_type,SELECT|nullable|in:instrument,kit,note,microscopy,impression',
            'sort_order' => 'nullable|integer',
            'status' => 'required|in:ACTIVE,INACTIVE'
        ]);

        DB::beginTransaction();

        try {

            $field = TestExtraFieldType::findOrFail($id);

            $oldData = $field->only($field->getFillable());

            $field->update([

                'field_name' => trim($request->field_name),

                'input_type' => $request->input_type,

                'source_master' => $request->input_type === 'SELECT' ? $request->source_master : null,

                'sort_order' => $request->sort_order ?? 0,

                'status' => $request->status,

                'updated_by' => Auth::id()
            ]);

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $field,
                $oldData,
                $field->only($field->getFillable()),
                'Test extra field type updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Extra field updated successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update extra field.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $field = TestExtraFieldType::findOrFail($id);

            if (DB::table('test_result_extra_values')->where('test_extra_field_type_id', $id)->exists()) {

                return response()->json([
                    'status' => false,
                    'message' => 'This extra field cannot be deleted. It has recorded values against one or more tests.'
                ]);
            }

            $oldData = $field->only($field->getFillable());

            $field->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $field,
                $oldData,
                'Test extra field type deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Extra field deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete extra field.'
            ], 500);
        }
    }
}
