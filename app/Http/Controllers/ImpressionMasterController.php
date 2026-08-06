<?php

namespace App\Http\Controllers;

use App\Models\ImpressionMaster;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ImpressionMasterController extends Controller
{
    private const MODULE_CODE = 'IMPRESSION_MASTER';

    public function index()
    {
        return view('apps-impression-master');
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = ImpressionMaster::query();

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
            'name' => 'required|max:1000|unique:impression_masters,name',
            'status' => 'required|in:ACTIVE,INACTIVE'
        ]);

        DB::beginTransaction();

        try {

            $row = ImpressionMaster::create([

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
                'Impression created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Impression created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save impression.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $row = ImpressionMaster::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $row
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'name' => 'required|max:1000|unique:impression_masters,name,' . $id,
            'status' => 'required|in:ACTIVE,INACTIVE'
        ]);

        DB::beginTransaction();

        try {

            $row = ImpressionMaster::findOrFail($id);

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
                'Impression updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Impression updated successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update impression.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $row = ImpressionMaster::findOrFail($id);

            $oldData = $row->only($row->getFillable());

            $row->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $row,
                $oldData,
                'Impression deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Impression deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete impression.'
            ], 500);
        }
    }
}
