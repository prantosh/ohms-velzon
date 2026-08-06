<?php

namespace App\Http\Controllers;

use App\Models\KitMaster;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class KitMasterController extends Controller
{
    private const MODULE_CODE = 'KIT_MASTER';

    public function index()
    {
        return view('apps-kit-master');
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = KitMaster::query();

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
            'name' => 'required|max:150|unique:kit_masters,name',
            'status' => 'required|in:ACTIVE,INACTIVE'
        ]);

        DB::beginTransaction();

        try {

            $row = KitMaster::create([

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
                'Kit created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Kit created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save kit.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $row = KitMaster::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $row
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'name' => 'required|max:150|unique:kit_masters,name,' . $id,
            'status' => 'required|in:ACTIVE,INACTIVE'
        ]);

        DB::beginTransaction();

        try {

            $row = KitMaster::findOrFail($id);

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
                'Kit updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Kit updated successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update kit.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $row = KitMaster::findOrFail($id);

            $oldData = $row->only($row->getFillable());

            $row->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $row,
                $oldData,
                'Kit deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Kit deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete kit.'
            ], 500);
        }
    }
}
