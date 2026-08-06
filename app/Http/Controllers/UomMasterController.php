<?php

namespace App\Http\Controllers;

use App\Models\UomMaster;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class UomMasterController extends Controller
{
    private const MODULE_CODE = 'UOM_MASTER';

    public function index()
    {
        return view('apps-uom-master');
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = UomMaster::query();

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
            'name' => 'required|max:30|unique:uom_masters,name',
            'status' => 'required|in:ACTIVE,INACTIVE'
        ]);

        DB::beginTransaction();

        try {

            $row = UomMaster::create([

                // UOM notation is case-sensitive (e.g. "mg/dl" vs "MG/DL"
                // are conventionally distinct) -- preserve as entered,
                // unlike label-style masters that force uppercase.
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
                'UOM created'
            );

            return response()->json([
                'status' => true,
                'message' => 'UOM created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save UOM.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $row = UomMaster::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $row
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'name' => 'required|max:30|unique:uom_masters,name,' . $id,
            'status' => 'required|in:ACTIVE,INACTIVE'
        ]);

        DB::beginTransaction();

        try {

            $row = UomMaster::findOrFail($id);

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
                'UOM updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'UOM updated successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update UOM.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $row = UomMaster::findOrFail($id);

            // uom is stored as a plain string copy (not a foreign key) on
            // both invoice_item_details and test_analytes, so "in use" is
            // checked by matching name, not id.
            $inUse = DB::table('invoice_item_details')->where('uom', $row->name)->exists()
                || DB::table('test_analytes')->where('uom', $row->name)->exists();

            if ($inUse) {

                return response()->json([
                    'status' => false,
                    'message' => 'UOM cannot be deleted. It is in use by one or more test parameters.'
                ]);
            }

            $oldData = $row->only($row->getFillable());

            $row->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $row,
                $oldData,
                'UOM deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'UOM deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete UOM.'
            ], 500);
        }
    }
}
