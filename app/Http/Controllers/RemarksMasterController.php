<?php

namespace App\Http\Controllers;

use App\Models\RemarksMaster;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class RemarksMasterController extends Controller
{
    private const MODULE_CODE = 'REMARKS_MASTER';

    public function index()
    {
        return view('apps-remarks-master');
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = RemarksMaster::query();

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where('name', 'like', "%{$search}%");
        }

        $rows = $query
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $rows->items(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total()
            ]
        ]);
    }

    public function activeList()
    {
        return response()->json([
            'status' => true,
            'data' => RemarksMaster::where('status', 'ACTIVE')->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'name' => 'required|max:1000|unique:remarks_masters,name',
            'status' => 'required|in:ACTIVE,INACTIVE'
        ]);

        DB::beginTransaction();

        try {

            $row = RemarksMaster::create([

                'name' => trim($request->name),

                'status' => $request->status,

                'created_by' => Auth::id(),

                'updated_by' => Auth::id()
            ]);

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $row,
                $row->only($row->getFillable()),
                'Remark created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Remark created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save remark.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $row = RemarksMaster::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $row
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'name' => 'required|max:1000|unique:remarks_masters,name,' . $id,
            'status' => 'required|in:ACTIVE,INACTIVE'
        ]);

        DB::beginTransaction();

        try {

            $row = RemarksMaster::findOrFail($id);

            $oldData = $row->only($row->getFillable());

            $row->update([

                'name' => trim($request->name),

                'status' => $request->status,

                'updated_by' => Auth::id()
            ]);

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $row,
                $oldData,
                $row->only($row->getFillable()),
                'Remark updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Remark updated successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update remark.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $row = RemarksMaster::findOrFail($id);

            $oldData = $row->only($row->getFillable());

            $row->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $row,
                $oldData,
                'Remark deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Remark deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete remark.'
            ], 500);
        }
    }
}
