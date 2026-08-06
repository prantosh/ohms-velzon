<?php

namespace App\Http\Controllers;

use App\Models\IncomeCategory;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class IncomeCategoryController extends Controller
{
    private const MODULE_CODE = 'INCOME_CATEGORY';

    public function index()
    {
        return view('apps-income-category');
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = IncomeCategory::query();

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
            'description' => 'required|max:150|unique:income_categories,description',
        ]);

        try {

            $category = IncomeCategory::create([

                'description' => strtoupper(trim($request->description)),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id()
            ]);

            $auditService->logCreate(
                self::MODULE_CODE,
                $category,
                $category->only($category->getFillable()),
                'Income category created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Income from Other Source category created successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save income from other source category.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $category = IncomeCategory::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $category
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'description' => 'required|max:150|unique:income_categories,description,' . $id,
        ]);

        try {

            $category = IncomeCategory::findOrFail($id);

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
                'Income category updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Income from Other Source category updated successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update income from other source category.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $category = IncomeCategory::findOrFail($id);

            $oldData = $category->only($category->getFillable());

            $category->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $category,
                $oldData,
                'Income category deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Income from Other Source category deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete income from other source category.'
            ], 500);
        }
    }
}
