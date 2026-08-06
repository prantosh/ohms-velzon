<?php
namespace App\Http\Controllers;

use App\Models\Specialisation;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SpecialisationController extends Controller
{
    private const MODULE_CODE = 'SPECIALISATION';

    public function index()
    {
        return view('apps-ecommerce-specialisations');
    }

    public function getSpecialisations(Request $request)
    {
        try {

            $perPage = $request->get('per_page', 10);

            $rows = Specialisation::orderBy('category')
                ->paginate($perPage);

            return response()->json([
                'status' => true,
                'data' => $rows->items(),
                'pagination' => [
                    'current_page' => $rows->currentPage(),
                    'last_page' => $rows->lastPage(),
                    'per_page' => $rows->perPage(),
                    'total' => $rows->total()
                ]
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'category' => 'required|max:30'
        ]);

        $row = Specialisation::create([
            'category' => strtoupper($request->category),
            'created_by' => Auth::id()
        ]);

        $auditService->logCreate(
            self::MODULE_CODE,
            $row,
            $row->only($row->getFillable()),
            'Specialisation created'
        );

        return response()->json([
            'status' => true,
            'message' => 'Specialisation added successfully'
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'category' => 'required|max:30'
        ]);

        $row = Specialisation::findOrFail($id);

        $oldData = $row->only($row->getFillable());

        $row->update([
            'category' => strtoupper($request->category),
            'update_by' => Auth::id()
        ]);

        $auditService->logUpdate(
            self::MODULE_CODE,
            $row,
            $oldData,
            $row->only($row->getFillable()),
            'Specialisation updated'
        );

        return response()->json([
            'status' => true,
            'message' => 'Specialisation updated successfully'
        ]);
    }

    public function destroy($id, AuditService $auditService)
    {
        try {
            if (
                \App\Models\Doctor::where(
                    'specialisation_code',
                    $id
                )->exists()
            ) {

                return response()->json([
                    'status' => false,
                    'message' =>
                        'Specialisation is already used by doctors.'
                ], 422);
            }

            $row = Specialisation::findOrFail($id);

            $oldData = $row->only($row->getFillable());

            $row->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $row,
                $oldData,
                'Specialisation deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Specialisation deleted successfully'
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function exportExcel()
    {
        $rows = Specialisation::orderBy('category')->get();

        $filename = 'specialisations.csv';

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
        ];

        $callback = function () use ($rows) {

            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'ID',
                'Category',
                'Created Date'
            ]);

            foreach ($rows as $row) {

                fputcsv($file, [
                    $row->id,
                    $row->category,
                    $row->created_dt
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function printSpecialisations()
    {
        $rows = Specialisation::orderBy('category')->get();

        return view(
            'specialisations-print',
            compact('rows')
        );
    }
}
