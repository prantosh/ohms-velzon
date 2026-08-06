<?php

namespace App\Http\Controllers;

use App\Models\ExpenditureCategory;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class ExpenditureCategoryController extends Controller
{
    private const MODULE_CODE = 'EXPENDITURE_CATEGORY';

    public function index()
    {
        return view('apps-expenditure-category');
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = ExpenditureCategory::query();

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where('description', 'like', "%{$search}%");
        }

        $categories = $query
            ->orderBy('description')
            ->paginate($perPage);

        $categories->getCollection()->transform(function ($row) {

            $row->created_dt = optional($row->created_at)->format('d-m-Y H:i');

            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $categories->items(),
            'pagination' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'total' => $categories->total()
            ]
        ]);
    }

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'description' => 'required|max:150|unique:expenditure_categories,description',
        ]);

        try {

            $category = ExpenditureCategory::create([

                'description' => strtoupper(trim($request->description)),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id()
            ]);

            $auditService->logCreate(
                self::MODULE_CODE,
                $category,
                $category->only($category->getFillable()),
                'Expenditure category created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Expenditure category created successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save expenditure category.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $category = ExpenditureCategory::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $category
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'description' => 'required|max:150|unique:expenditure_categories,description,' . $id,
        ]);

        try {

            $category = ExpenditureCategory::findOrFail($id);

            $oldData = $category->only($category->getFillable());

            $category->update([

                'description' => strtoupper(trim($request->description)),
                'updated_by' => Auth::id()
            ]);

            $auditService->logUpdate(
                self::MODULE_CODE,
                $category,
                $oldData,
                $category->only($category->getFillable()),
                'Expenditure category updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Expenditure category updated successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update expenditure category.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $category = ExpenditureCategory::findOrFail($id);

            $oldData = $category->only($category->getFillable());

            $category->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $category,
                $oldData,
                'Expenditure category deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Expenditure category deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete expenditure category.'
            ], 500);
        }
    }
}
