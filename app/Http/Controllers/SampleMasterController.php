<?php

namespace App\Http\Controllers;

use App\Models\SampleMaster;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SampleMasterController extends Controller
{
    private const MODULE_CODE = 'SAMPLE_MASTER';

    public function index()
    {
        return view('apps-sample-master');
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = SampleMaster::query();

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

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'name' => 'required|max:100|unique:sample_masters,name',
            'status' => 'required|in:ACTIVE,INACTIVE'
        ]);

        DB::beginTransaction();

        try {

            $row = SampleMaster::create([

                'name' => strtoupper(trim($request->name)),

                'status' => $request->status,

                'created_by' => Auth::id(),

                'updated_by' => Auth::id()
            ]);

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $row,
                $row->only($row->getFillable()),
                'Sample created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Sample created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save sample.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $row = SampleMaster::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $row
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'name' => 'required|max:100|unique:sample_masters,name,' . $id,
            'status' => 'required|in:ACTIVE,INACTIVE'
        ]);

        DB::beginTransaction();

        try {

            $row = SampleMaster::findOrFail($id);

            $oldData = $row->only($row->getFillable());

            $row->update([

                'name' => strtoupper(trim($request->name)),

                'status' => $request->status,

                'updated_by' => Auth::id()
            ]);

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $row,
                $oldData,
                $row->only($row->getFillable()),
                'Sample updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Sample updated successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update sample.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $row = SampleMaster::findOrFail($id);

            if (DB::table('invoice_item_details')->where('sample_master_id', $id)->exists()) {

                return response()->json([
                    'status' => false,
                    'message' => 'Sample cannot be deleted. It is in use by one or more test parameters.'
                ]);
            }

            $oldData = $row->only($row->getFillable());

            $row->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $row,
                $oldData,
                'Sample deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Sample deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete sample.'
            ], 500);
        }
    }
}
