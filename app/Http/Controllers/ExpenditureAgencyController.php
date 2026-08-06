<?php

namespace App\Http\Controllers;

use App\Models\ExpenditureAgency;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class ExpenditureAgencyController extends Controller
{
    private const MODULE_CODE = 'EXPENDITURE_AGENCY';

    public function index()
    {
        return view('apps-expenditure-agency');
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = ExpenditureAgency::query();

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where('description', 'like', "%{$search}%");
        }

        $agencies = $query
            ->orderBy('description')
            ->paginate($perPage);

        $agencies->getCollection()->transform(function ($row) {

            $row->created_dt = optional($row->created_at)->format('d-m-Y H:i');

            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $agencies->items(),
            'pagination' => [
                'current_page' => $agencies->currentPage(),
                'last_page' => $agencies->lastPage(),
                'total' => $agencies->total()
            ]
        ]);
    }

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'description' => 'required|max:150|unique:expenditure_agencies,description',
        ]);

        try {

            $agency = ExpenditureAgency::create([

                'description' => strtoupper(trim($request->description)),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id()
            ]);

            $auditService->logCreate(
                self::MODULE_CODE,
                $agency,
                $agency->only($agency->getFillable()),
                'Expenditure agency created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Expenditure agency created successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save expenditure agency.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $agency = ExpenditureAgency::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $agency
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'description' => 'required|max:150|unique:expenditure_agencies,description,' . $id,
        ]);

        try {

            $agency = ExpenditureAgency::findOrFail($id);

            $oldData = $agency->only($agency->getFillable());

            $agency->update([

                'description' => strtoupper(trim($request->description)),
                'updated_by' => Auth::id()
            ]);

            $auditService->logUpdate(
                self::MODULE_CODE,
                $agency,
                $oldData,
                $agency->only($agency->getFillable()),
                'Expenditure agency updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Expenditure agency updated successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update expenditure agency.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $agency = ExpenditureAgency::findOrFail($id);

            $oldData = $agency->only($agency->getFillable());

            $agency->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $agency,
                $oldData,
                'Expenditure agency deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Expenditure agency deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete expenditure agency.'
            ], 500);
        }
    }
}
