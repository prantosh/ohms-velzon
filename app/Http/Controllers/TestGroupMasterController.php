<?php

namespace App\Http\Controllers;

use App\Models\TestGroupMaster;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class TestGroupMasterController extends Controller
{
    private const MODULE_CODE = 'TEST_GROUP_MASTER';

    public function index()
    {
        return view('apps-test-group-master');
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = TestGroupMaster::with('creator');

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where('test_group_name', 'like', "%{$search}%");
        }

        $groups = $query
            ->orderBy('test_group_name')
            ->paginate($perPage);

        $groups->getCollection()->transform(function ($row) {

            $row->created_dt = optional($row->created_at)
                ->format('d-m-Y H:i');

            $row->created_by_name = optional($row->creator)->name;

            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $groups->items(),
            'pagination' => [
                'current_page' => $groups->currentPage(),
                'last_page' => $groups->lastPage(),
                'total' => $groups->total()
            ]
        ]);
    }

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'test_group_name' => 'required|max:100|unique:test_group_masters,test_group_name',
            'status' => 'required|in:ACTIVE,INACTIVE',
            'additional_content' => 'nullable|string'
        ]);

        DB::beginTransaction();

        try {

            $group = TestGroupMaster::create([

                'test_group_name' => strtoupper(trim($request->test_group_name)),

                'status' => $request->status,

                'additional_content' => $request->additional_content,

                'created_by' => Auth::id(),

                'updated_by' => Auth::id()
            ]);

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $group,
                $group->only($group->getFillable()),
                'Test Group created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Test Group created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save Test Group.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $group = TestGroupMaster::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $group
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'test_group_name' => 'required|max:100|unique:test_group_masters,test_group_name,' . $id,
            'status' => 'required|in:ACTIVE,INACTIVE',
            'additional_content' => 'nullable|string'
        ]);

        DB::beginTransaction();

        try {

            $group = TestGroupMaster::findOrFail($id);

            $oldData = $group->only($group->getFillable());

            $group->update([

                'test_group_name' => strtoupper(trim($request->test_group_name)),

                'status' => $request->status,

                'additional_content' => $request->additional_content,

                'updated_by' => Auth::id()
            ]);

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $group,
                $oldData,
                $group->only($group->getFillable()),
                'Test Group updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Test Group updated successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update Test Group.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $group = TestGroupMaster::findOrFail($id);

            if (DB::table('invoice_item_details')->where('test_group_code', $id)->exists()) {

                return response()->json([
                    'status' => false,
                    'message' => 'Test Group cannot be deleted. It is in use by one or more test details.'
                ]);
            }

            $oldData = $group->only($group->getFillable());

            $group->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $group,
                $oldData,
                'Test Group deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Test Group deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete Test Group.'
            ], 500);
        }
    }
}
