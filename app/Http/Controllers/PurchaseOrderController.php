<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class PurchaseOrderController extends Controller
{
    private const MODULE_CODE = 'PURCHASE_ORDER';

    public function index()
    {
        return view('apps-purchase-order');
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = PurchaseOrder::query();

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                $q->where('po_no', 'like', "%{$search}%")
                    ->orWhere('vendor_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query
            ->orderByDesc('id')
            ->paginate($perPage);

        $orders->getCollection()->transform(function ($row) {

            $row->po_date_fmt = $row->po_date
                ? \Carbon\Carbon::parse($row->po_date)->format('d-m-Y')
                : null;

            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total()
            ]
        ]);
    }

    public function show($id)
    {
        $po = PurchaseOrder::with('items.item')->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $po
        ]);
    }

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'po_date' => 'required|date',
            'requisition_ref' => 'nullable|string|max:50',
            'requisition_date' => 'nullable|date',
            'vendor_name' => 'required|string|max:150',
            'payment_term' => 'nullable|string|max:50',
            'note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.inventory_item_id' => 'required|exists:inventory_items,id',
            'items.*.uom' => 'required|string|max:20',
            'items.*.po_qty' => 'required|numeric|min:0.01',
            'items.*.unit_rate' => 'required|numeric|min:0',
            'items.*.gst_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::beginTransaction();

        try {

            $total = 0;

            $lineItems = [];

            foreach ($request->items as $item) {

                $amount = round($item['po_qty'] * $item['unit_rate'], 2);

                $lineItems[] = [
                    'inventory_item_id' => $item['inventory_item_id'],
                    'uom' => $item['uom'],
                    'po_qty' => $item['po_qty'],
                    'unit_rate' => $item['unit_rate'],
                    'gst_percent' => $item['gst_percent'] ?? 0,
                    'amount' => $amount,
                    'received_qty' => 0,
                ];

                $total += $amount;
            }

            $po = PurchaseOrder::create([

                'po_no' => $this->generatePoNo(),
                'po_date' => $request->po_date,
                'requisition_ref' => $request->requisition_ref,
                'requisition_date' => $request->requisition_date,
                'vendor_name' => $request->vendor_name,
                'payment_term' => $request->payment_term,
                'total_value' => round($total, 2),
                'note' => $request->note,
                'status' => 'OPEN',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id()
            ]);

            $po->items()->createMany($lineItems);

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $po,
                $po->only($po->getFillable()),
                'Purchase order created'
            );

            return response()->json([
                'status' => true,
                'po_id' => $po->id,
                'po_no' => $po->po_no,
                'message' => 'Purchase order created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to create purchase order.'
            ], 500);
        }
    }

    public function cancel($id, AuditService $auditService)
    {
        DB::beginTransaction();

        try {

            $po = PurchaseOrder::findOrFail($id);

            if ($po->status !== 'OPEN') {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'Only an open purchase order can be cancelled.'
                ], 422);
            }

            $hasReceipts = DB::table('goods_receipt_items')
                ->whereIn('purchase_order_item_id', $po->items()->pluck('id'))
                ->exists();

            if ($hasReceipts) {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'This purchase order already has goods receipts recorded against it and cannot be cancelled.'
                ], 422);
            }

            $po->update([
                'status' => 'CANCELLED',
                'updated_by' => Auth::id()
            ]);

            DB::commit();

            $auditService->logAction(
                self::MODULE_CODE,
                $po,
                'CUSTOM',
                'Purchase order cancelled'
            );

            return response()->json([
                'status' => true,
                'message' => 'Purchase order cancelled successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to cancel purchase order.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | OPEN PURCHASE ORDERS (FOR GOODS RECEIPT SELECTION)
    |--------------------------------------------------------------------------
    */

    public function openOrders()
    {
        $orders = PurchaseOrder::where('status', 'OPEN')
            ->orderByDesc('id')
            ->get(['id', 'po_no', 'vendor_name', 'po_date']);

        return response()->json([
            'status' => true,
            'data' => $orders
        ]);
    }

    public function pendingItems($id)
    {
        $po = PurchaseOrder::with('items.item')->findOrFail($id);

        $pending = $po->items->filter(function ($item) {
            return $item->received_qty < $item->po_qty;
        })->values();

        return response()->json([
            'status' => true,
            'vendor_name' => $po->vendor_name,
            'items' => $pending
        ]);
    }

    private function generatePoNo(): string
    {
        $prefix = \App\Support\OfflineMode::prefix('PO/') .
            now()->format('my') .
            '/' .
            str_pad(Auth::id(), 3, '0', STR_PAD_LEFT);

        $lastOrder = PurchaseOrder::where('po_no', 'like', $prefix . '%')
            ->latest('id')
            ->first();

        $serial = 1;

        if ($lastOrder) {

            $explode = explode('/', $lastOrder->po_no);

            $serial = intval(end($explode)) + 1;
        }

        return $prefix . '/' . str_pad($serial, 4, '0', STR_PAD_LEFT);
    }
}
