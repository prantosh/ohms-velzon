<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class GoodsReceiptController extends Controller
{
    private const MODULE_CODE = 'GOODS_RECEIPT';

    public function index()
    {
        return view('apps-goods-receipt');
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = GoodsReceipt::with('purchaseOrder:id,po_no');

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                $q->where('receipt_no', 'like', "%{$search}%")
                    ->orWhere('vendor_name', 'like', "%{$search}%");
            });
        }

        $receipts = $query
            ->orderByDesc('id')
            ->paginate($perPage);

        $receipts->getCollection()->transform(function ($row) {

            $row->receipt_date_fmt = $row->receipt_date
                ? \Carbon\Carbon::parse($row->receipt_date)->format('d-m-Y')
                : null;

            $row->po_no = optional($row->purchaseOrder)->po_no;

            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $receipts->items(),
            'pagination' => [
                'current_page' => $receipts->currentPage(),
                'last_page' => $receipts->lastPage(),
                'total' => $receipts->total()
            ]
        ]);
    }

    public function show($id)
    {
        $receipt = GoodsReceipt::with('items.item', 'purchaseOrder:id,po_no')->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $receipt
        ]);
    }

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'receipt_date' => 'required|date',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'ref_no' => 'nullable|string|max:50',
            'vendor_name' => 'nullable|string|max:150',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.inventory_item_id' => 'required|exists:inventory_items,id',
            'items.*.purchase_order_item_id' => 'nullable|exists:purchase_order_items,id',
            'items.*.uom' => 'required|string|max:20',
            'items.*.received_qty' => 'required|numeric|min:0.01',
            'items.*.unit_rate' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {

            $receipt = GoodsReceipt::create([

                'receipt_no' => $this->generateReceiptNo(),
                'receipt_date' => $request->receipt_date,
                'purchase_order_id' => $request->purchase_order_id,
                'ref_no' => $request->ref_no,
                'vendor_name' => $request->vendor_name,
                'remarks' => $request->remarks,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id()
            ]);

            foreach ($request->items as $line) {

                $amount = round($line['received_qty'] * $line['unit_rate'], 2);

                $receipt->items()->create([

                    'purchase_order_item_id' => $line['purchase_order_item_id'] ?? null,
                    'inventory_item_id' => $line['inventory_item_id'],
                    'uom' => $line['uom'],
                    'received_qty' => $line['received_qty'],
                    'unit_rate' => $line['unit_rate'],
                    'amount' => $amount,
                ]);

                $this->applyStockReceipt(
                    $line['inventory_item_id'],
                    (float) $line['received_qty'],
                    (float) $line['unit_rate']
                );

                if (!empty($line['purchase_order_item_id'])) {

                    $poItem = PurchaseOrderItem::find($line['purchase_order_item_id']);

                    if ($poItem) {

                        $poItem->increment('received_qty', $line['received_qty']);
                    }
                }
            }

            if ($request->purchase_order_id) {

                $this->closePoIfFullyReceived($request->purchase_order_id);
            }

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $receipt,
                $receipt->only($receipt->getFillable()),
                'Goods receipt recorded'
            );

            return response()->json([
                'status' => true,
                'receipt_id' => $receipt->id,
                'receipt_no' => $receipt->receipt_no,
                'message' => 'Goods receipt recorded successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to record goods receipt.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function applyStockReceipt(int $itemId, float $qty, float $rate): void
    {
        $item = InventoryItem::findOrFail($itemId);

        $existingValue = $item->current_stock * $item->avg_rate;

        $newStock = $item->current_stock + $qty;

        $newAvgRate = $newStock > 0
            ? round(($existingValue + ($qty * $rate)) / $newStock, 2)
            : 0;

        $item->update([
            'current_stock' => $newStock,
            'avg_rate' => $newAvgRate,
            'updated_by' => Auth::id()
        ]);
    }

    private function closePoIfFullyReceived(int $poId): void
    {
        $po = PurchaseOrder::with('items')->find($poId);

        if (!$po || $po->status !== 'OPEN') {
            return;
        }

        $fullyReceived = $po->items->every(function ($item) {
            return $item->received_qty >= $item->po_qty;
        });

        if ($fullyReceived) {

            $po->update([
                'status' => 'CLOSED',
                'updated_by' => Auth::id()
            ]);
        }
    }

    private function generateReceiptNo(): string
    {
        $prefix = \App\Support\OfflineMode::prefix('GRN/') .
            now()->format('my') .
            '/' .
            str_pad(Auth::id(), 3, '0', STR_PAD_LEFT);

        $last = GoodsReceipt::where('receipt_no', 'like', $prefix . '%')
            ->latest('id')
            ->first();

        $serial = 1;

        if ($last) {

            $explode = explode('/', $last->receipt_no);

            $serial = intval(end($explode)) + 1;
        }

        return $prefix . '/' . str_pad($serial, 4, '0', STR_PAD_LEFT);
    }
}
