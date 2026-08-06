<?php

namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class InventoryCategoryController extends Controller
{
    private const MODULE_CODE = 'INVENTORY_CATEGORY';

    public function index()
    {
        return view('apps-inventory-category');
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = InventoryCategory::query();

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where('category_name', 'like', "%{$search}%");
        }

        $categories = $query
            ->orderBy('category_name')
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
            'category_name' => 'required|max:150|unique:inventory_categories,category_name',
            'status' => 'required|in:ACTIVE,INACTIVE'
        ]);

        try {

            $category = InventoryCategory::create([

                'category_name' => strtoupper(trim($request->category_name)),
                'status' => $request->status,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id()
            ]);

            $auditService->logCreate(
                self::MODULE_CODE,
                $category,
                $category->only($category->getFillable()),
                'Inventory category created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Inventory category created successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save inventory category.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $category = InventoryCategory::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $category
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'category_name' => 'required|max:150|unique:inventory_categories,category_name,' . $id,
            'status' => 'required|in:ACTIVE,INACTIVE'
        ]);

        try {

            $category = InventoryCategory::findOrFail($id);

            $oldData = $category->only($category->getFillable());

            $category->update([

                'category_name' => strtoupper(trim($request->category_name)),
                'status' => $request->status,
                'updated_by' => Auth::id()
            ]);

            $auditService->logUpdate(
                self::MODULE_CODE,
                $category,
                $oldData,
                $category->only($category->getFillable()),
                'Inventory category updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Inventory category updated successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update inventory category.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $category = InventoryCategory::findOrFail($id);

            if (DB::table('inventory_items')->where('inventory_category_id', $id)->exists()) {

                return response()->json([
                    'status' => false,
                    'message' => 'Category cannot be deleted. Items exist under this category.'
                ]);
            }

            $oldData = $category->only($category->getFillable());

            $category->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $category,
                $oldData,
                'Inventory category deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Inventory category deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete inventory category.'
            ], 500);
        }
    }
}
