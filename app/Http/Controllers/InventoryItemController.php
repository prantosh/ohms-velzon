<?php

namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class InventoryItemController extends Controller
{
    private const MODULE_CODE = 'INVENTORY_ITEM';

    public function index()
    {
        $categories = InventoryCategory::where('status', 'ACTIVE')
            ->orderBy('category_name')
            ->get(['id', 'category_name']);

        return view('apps-inventory-item', compact('categories'));
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = InventoryItem::with('category');

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                $q->where('item_code', 'like', "%{$search}%")
                    ->orWhere('item_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('inventory_category_id', $request->category_id);
        }

        $items = $query
            ->orderBy('item_name')
            ->paginate($perPage);

        $items->getCollection()->transform(function ($row) {

            $row->category_name = optional($row->category)->category_name;

            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $items->items(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total()
            ]
        ]);
    }

    public function searchByName(Request $request)
    {
        $search = trim($request->get('term', ''));

        $items = InventoryItem::where('status', 'ACTIVE')
            ->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                    ->orWhere('item_code', 'like', "%{$search}%");
            })
            ->orderBy('item_name')
            ->limit(20)
            ->get(['id', 'item_code', 'item_name', 'uom', 'current_stock', 'avg_rate']);

        return response()->json([
            'status' => true,
            'items' => $items
        ]);
    }

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'item_name' => 'required|max:150',
            'uom' => 'required|max:20',
            'inventory_category_id' => 'nullable|exists:inventory_categories,id',
            'opening_stock' => 'nullable|numeric|min:0',
            'opening_value' => 'nullable|numeric|min:0',
            'status' => 'required|in:ACTIVE,INACTIVE'
        ]);

        DB::beginTransaction();

        try {

            $openingStock = (float) ($request->opening_stock ?? 0);
            $openingValue = (float) ($request->opening_value ?? 0);

            $item = InventoryItem::create([

                'item_code' => $this->generateItemCode(),
                'item_name' => strtoupper(trim($request->item_name)),
                'uom' => $request->uom,
                'inventory_category_id' => $request->inventory_category_id,
                'opening_stock' => $openingStock,
                'opening_value' => $openingValue,
                'current_stock' => $openingStock,
                'avg_rate' => $openingStock > 0 ? round($openingValue / $openingStock, 2) : 0,
                'status' => $request->status,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id()
            ]);

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $item,
                $item->only($item->getFillable()),
                'Inventory item created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Inventory item created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save inventory item.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $item = InventoryItem::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $item
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'item_name' => 'required|max:150',
            'uom' => 'required|max:20',
            'inventory_category_id' => 'nullable|exists:inventory_categories,id',
            'status' => 'required|in:ACTIVE,INACTIVE'
        ]);

        try {

            $item = InventoryItem::findOrFail($id);

            $oldData = $item->only($item->getFillable());

            $item->update([

                'item_name' => strtoupper(trim($request->item_name)),
                'uom' => $request->uom,
                'inventory_category_id' => $request->inventory_category_id,
                'status' => $request->status,
                'updated_by' => Auth::id()
            ]);

            $auditService->logUpdate(
                self::MODULE_CODE,
                $item,
                $oldData,
                $item->only($item->getFillable()),
                'Inventory item updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Inventory item updated successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update inventory item.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $item = InventoryItem::findOrFail($id);

            $hasTransactions = DB::table('purchase_order_items')->where('inventory_item_id', $id)->exists()
                || DB::table('goods_receipt_items')->where('inventory_item_id', $id)->exists()
                || DB::table('stock_issue_items')->where('inventory_item_id', $id)->exists();

            if ($hasTransactions) {

                return response()->json([
                    'status' => false,
                    'message' => 'Item cannot be deleted. It has purchase, receipt or issue transactions recorded against it.'
                ]);
            }

            $oldData = $item->only($item->getFillable());

            $item->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $item,
                $oldData,
                'Inventory item deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Inventory item deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete inventory item.'
            ], 500);
        }
    }

    private function generateItemCode(): string
    {
        $last = InventoryItem::latest('id')->first();

        if (!$last) {
            return 'ITM000001';
        }

        $number = intval(substr($last->item_code, 3)) + 1;

        return 'ITM' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}
