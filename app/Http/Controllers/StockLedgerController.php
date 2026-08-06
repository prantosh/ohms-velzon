<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockLedgerController extends Controller
{
    public function index()
    {
        return view('apps-stock-ledger');
    }

    /*
    |--------------------------------------------------------------------------
    | STOCK SUMMARY (ALL ITEMS, CURRENT STOCK + VALUE)
    |--------------------------------------------------------------------------
    */

    public function summary(Request $request)
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

        $items = $query
            ->orderBy('item_name')
            ->paginate($perPage);

        $items->getCollection()->transform(function ($row) {

            $row->category_name = optional($row->category)->category_name;

            $row->stock_value = round($row->current_stock * $row->avg_rate, 2);

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

    /*
    |--------------------------------------------------------------------------
    | ITEM-WISE LEDGER (BIN CARD STYLE)
    |--------------------------------------------------------------------------
    */

    public function detail(Request $request)
    {
        $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $item = InventoryItem::findOrFail($request->inventory_item_id);

        $fromDate = $request->from_date
            ? Carbon::parse($request->from_date)->startOfDay()
            : null;

        $toDate = $request->to_date
            ? Carbon::parse($request->to_date)->endOfDay()
            : null;

        $receipts = DB::table('goods_receipt_items')
            ->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
            ->where('goods_receipt_items.inventory_item_id', $item->id)
            ->select(
                'goods_receipts.receipt_date as txn_date',
                'goods_receipts.receipt_no as txn_no',
                'goods_receipt_items.received_qty as qty',
                'goods_receipt_items.unit_rate as rate',
                'goods_receipt_items.amount as value'
            )
            ->get()
            ->map(function ($row) {
                $row->type = 'RECEIPT';
                return $row;
            });

        $issues = DB::table('stock_issue_items')
            ->join('stock_issues', 'stock_issues.id', '=', 'stock_issue_items.stock_issue_id')
            ->where('stock_issue_items.inventory_item_id', $item->id)
            ->select(
                'stock_issues.issue_date as txn_date',
                'stock_issues.issue_no as txn_no',
                'stock_issue_items.issue_qty as qty',
                'stock_issue_items.unit_rate as rate',
                'stock_issue_items.amount as value'
            )
            ->get()
            ->map(function ($row) {
                $row->type = 'ISSUE';
                return $row;
            });

        $allTransactions = $receipts->concat($issues)
            ->sortBy('txn_date')
            ->values();

        $openingQty = (float) $item->opening_stock;
        $openingValue = (float) $item->opening_value;

        $priorTransactions = $allTransactions->filter(function ($row) use ($fromDate) {
            return $fromDate && Carbon::parse($row->txn_date)->lt($fromDate);
        });

        foreach ($priorTransactions as $row) {

            if ($row->type === 'RECEIPT') {
                $openingQty += $row->qty;
                $openingValue += $row->value;
            } else {
                $openingQty -= $row->qty;
                $openingValue -= $row->value;
            }
        }

        $periodTransactions = $allTransactions->filter(function ($row) use ($fromDate, $toDate) {

            $date = Carbon::parse($row->txn_date);

            if ($fromDate && $date->lt($fromDate)) {
                return false;
            }

            if ($toDate && $date->gt($toDate)) {
                return false;
            }

            return true;
        })->values();

        $runningQty = $openingQty;
        $runningValue = $openingValue;

        $ledger = $periodTransactions->map(function ($row) use (&$runningQty, &$runningValue) {

            if ($row->type === 'RECEIPT') {
                $runningQty += $row->qty;
                $runningValue += $row->value;
            } else {
                $runningQty -= $row->qty;
                $runningValue -= $row->value;
            }

            return [
                'txn_date' => Carbon::parse($row->txn_date)->format('d-m-Y'),
                'txn_no' => $row->txn_no,
                'type' => $row->type,
                'qty' => (float) $row->qty,
                'rate' => (float) $row->rate,
                'value' => (float) $row->value,
                'balance_qty' => round($runningQty, 2),
                'balance_value' => round($runningValue, 2),
            ];
        });

        return response()->json([
            'status' => true,
            'item' => [
                'item_code' => $item->item_code,
                'item_name' => $item->item_name,
                'uom' => $item->uom,
                'current_stock' => $item->current_stock,
                'avg_rate' => $item->avg_rate,
            ],
            'opening_qty' => round($openingQty, 2),
            'opening_value' => round($openingValue, 2),
            'closing_qty' => round($runningQty, 2),
            'closing_value' => round($runningValue, 2),
            'ledger' => $ledger,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ITEM-WISE LEDGER PDF EXPORT
    |--------------------------------------------------------------------------
    */

    public function printDetail(Request $request)
    {
        $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $result = json_decode($this->detail($request)->getContent(), true);

        $pdf = Pdf::loadView('apps-stock-ledger-pdf', [
            'item' => $result['item'],
            'openingQty' => $result['opening_qty'],
            'openingValue' => $result['opening_value'],
            'closingQty' => $result['closing_qty'],
            'closingValue' => $result['closing_value'],
            'ledger' => $result['ledger'],
            'fromDate' => $request->from_date ? Carbon::parse($request->from_date) : null,
            'toDate' => $request->to_date ? Carbon::parse($request->to_date) : null,
            'printedBy' => optional(auth()->user())->name,
        ]);

        return $pdf->stream('Stock-Ledger-' . $result['item']['item_code'] . '.pdf');
    }
}
