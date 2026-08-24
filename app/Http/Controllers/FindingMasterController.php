<?php

namespace App\Http\Controllers;

use App\Models\FindingMaster;
use App\Services\AuditService;
use App\Services\HtmlSanitizerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class FindingMasterController extends Controller
{
    private const MODULE_CODE = 'FINDING_MASTER';

    public function index()
    {
        return view('apps-finding-master');
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = FindingMaster::query();

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
            'name' => 'required|max:50000|unique:finding_masters,name',
            'status' => 'required|in:ACTIVE,INACTIVE'
        ]);

        DB::beginTransaction();

        try {

            $row = FindingMaster::create([

                'name' => HtmlSanitizerService::sanitizeClinicalText($request->name) ?? '',

                'status' => $request->status,

                'created_by' => Auth::id(),

                'updated_by' => Auth::id()
            ]);

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $row,
                $row->only($row->getFillable()),
                'Finding created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Finding created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save finding.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $row = FindingMaster::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $row
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'name' => 'required|max:50000|unique:finding_masters,name,' . $id,
            'status' => 'required|in:ACTIVE,INACTIVE'
        ]);

        DB::beginTransaction();

        try {

            $row = FindingMaster::findOrFail($id);

            $oldData = $row->only($row->getFillable());

            $row->update([

                'name' => HtmlSanitizerService::sanitizeClinicalText($request->name) ?? '',

                'status' => $request->status,

                'updated_by' => Auth::id()
            ]);

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $row,
                $oldData,
                $row->only($row->getFillable()),
                'Finding updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Finding updated successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update finding.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $row = FindingMaster::findOrFail($id);

            $oldData = $row->only($row->getFillable());

            $row->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $row,
                $oldData,
                'Finding deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Finding deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete finding.'
            ], 500);
        }
    }
}
