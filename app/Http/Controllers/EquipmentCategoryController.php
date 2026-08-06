<?php

namespace App\Http\Controllers;

use App\Models\EquipmentCategory;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class EquipmentCategoryController extends Controller
{
    private const MODULE_CODE = 'EQUIPMENT_CATEGORY';

    public function index()
    {
        return view('apps-equipment-category');
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = EquipmentCategory::with('creator');

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                $q->where('category_code', 'like', "%{$search}%")
                    ->orWhere('category_name', 'like', "%{$search}%");

            });
        }

        $categories = $query
            ->orderBy('category_name')
            ->paginate($perPage);

        $categories->getCollection()->transform(function ($row) {

            $row->created_dt = optional($row->created_at)
                ->format('d-m-Y H:i');

            $row->created_by_name = optional($row->creator)->name;

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
            'category_name' => 'required|max:150|unique:equipment_categories,category_name',
            'total_quantity' => 'required|integer|min:0',
            'status' => 'required'
        ]);

        DB::beginTransaction();

        try {

            $category = EquipmentCategory::create([

                'category_code' => $this->generateCategoryCode(),

                'category_name' => strtoupper(trim($request->category_name)),

                'total_quantity' => $request->total_quantity,

                'available_quantity' => $request->total_quantity,

                'remarks' => $request->remarks,

                'status' => $request->status,

                'created_by' => Auth::id(),

                'updated_by' => Auth::id()

            ]);

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $category,
                $category->only($category->getFillable()),
                'Equipment category created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Equipment Category created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save Equipment Category.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $category = EquipmentCategory::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $category
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'category_name' => 'required|max:150|unique:equipment_categories,category_name,' . $id,
            'total_quantity' => 'required|integer|min:0',
            'status' => 'required'
        ]);

        DB::beginTransaction();

        try {

            $category = EquipmentCategory::findOrFail($id);

            $rentedOut = $category->total_quantity - $category->available_quantity;

            if ($request->total_quantity < $rentedOut) {

                return response()->json([
                    'status' => false,
                    'message' => "Total quantity cannot be less than the {$rentedOut} unit(s) currently rented out."
                ], 422);
            }

            $oldData = $category->only($category->getFillable());

            $category->update([

                'category_name' => strtoupper(trim($request->category_name)),

                'total_quantity' => $request->total_quantity,

                'available_quantity' => $request->total_quantity - $rentedOut,

                'remarks' => $request->remarks,

                'status' => $request->status,

                'updated_by' => Auth::id()

            ]);

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $category,
                $oldData,
                $category->only($category->getFillable()),
                'Equipment category updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Equipment Category updated successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update Equipment Category.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $category = EquipmentCategory::findOrFail($id);

            if ($category->available_quantity < $category->total_quantity) {

                return response()->json([
                    'status' => false,
                    'message' => 'Category cannot be deleted. Equipment is currently rented out under this category.'
                ]);
            }

            $oldData = $category->only($category->getFillable());

            $category->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $category,
                $oldData,
                'Equipment category deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Equipment Category deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete Equipment Category.'
            ], 500);
        }
    }

    private function generateCategoryCode(): string
    {
        $last = EquipmentCategory::latest('id')->first();

        if (!$last) {
            return 'EC000001';
        }

        $number = intval(substr($last->category_code, 2)) + 1;

        return 'EC' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}
