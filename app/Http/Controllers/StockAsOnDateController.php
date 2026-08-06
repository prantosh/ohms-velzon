<?php

namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockAsOnDateController extends Controller
{
    public function index()
    {
        return view('apps-stock-as-on-date', [
            'categories' => InventoryCategory::orderBy('category_name')->get(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | REPORT (all items' closing balance as of a cutoff date)
    |--------------------------------------------------------------------------
    | Effective rate shown per item is (closing_value / closing_qty) -- an
    | approximation for display purposes, not a re-walked moving-average
    | rate. It matches the true average rate only when every receipt/issue
    | before the cutoff has been recorded through Goods Receipt/Stock Issue.
    */

    public function report(Request $request)
    {
        $request->validate([
            'as_on_date' => 'required|date',
            'inventory_category_id' => 'nullable|exists:inventory_categories,id',
            'search' => 'nullable|string',
        ]);

        $asOnDate = Carbon::parse($request->as_on_date)->endOfDay();

        $rows = $this->closingBalanceQuery($asOnDate, $request->inventory_category_id, $request->search)
            ->orderBy('inventory_items.item_name')
            ->get()
            ->map(function ($row) {
                $row->closing_qty = round((float) $row->closing_qty, 2);
                $row->closing_value = round((float) $row->closing_value, 2);
                $row->effective_rate = $row->closing_qty != 0
                    ? round($row->closing_value / $row->closing_qty, 2)
                    : 0;
                return $row;
            });

        return response()->json([
            'status' => true,
            'as_on_date' => $asOnDate->format('d-m-Y'),
            'rows' => $rows->values(),
            'grand_total' => [
                'closing_qty' => round($rows->sum('closing_qty'), 2),
                'closing_value' => round($rows->sum('closing_value'), 2),
            ],
        ]);
    }

    public function print(Request $request)
    {
        $request->validate([
            'as_on_date' => 'required|date',
            'inventory_category_id' => 'nullable|exists:inventory_categories,id',
            'search' => 'nullable|string',
        ]);

        $result = json_decode($this->report($request)->getContent(), true);

        // Unlike the other reports, this one can render every inventory item (300+)
        // unfiltered in a single table; dompdf's rendering of that many bordered/
        // shaded rows can exceed the default 128M PHP memory limit.
        ini_set('memory_limit', '512M');

        $pdf = Pdf::loadView('apps-stock-as-on-date-pdf', [
            'rows' => $result['rows'],
            'grandTotal' => $result['grand_total'],
            'asOnDate' => $result['as_on_date'],
            'printedBy' => optional(auth()->user())->name,
        ]);

        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream('Stock-As-On-Date-' . $result['as_on_date'] . '.pdf');
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY HELPER (set-based, no per-item loop)
    |--------------------------------------------------------------------------
    */

    private function closingBalanceQuery(Carbon $asOnDate, ?int $categoryId, ?string $search)
    {
        $receiptTotals = DB::table('goods_receipt_items')
            ->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
            ->where('goods_receipts.receipt_date', '<=', $asOnDate)
            ->groupBy('goods_receipt_items.inventory_item_id')
            ->select(
                'goods_receipt_items.inventory_item_id',
                DB::raw('SUM(goods_receipt_items.received_qty) as qty'),
                DB::raw('SUM(goods_receipt_items.amount) as value')
            );

        $issueTotals = DB::table('stock_issue_items')
            ->join('stock_issues', 'stock_issues.id', '=', 'stock_issue_items.stock_issue_id')
            ->where('stock_issues.issue_date', '<=', $asOnDate)
            ->groupBy('stock_issue_items.inventory_item_id')
            ->select(
                'stock_issue_items.inventory_item_id',
                DB::raw('SUM(stock_issue_items.issue_qty) as qty'),
                DB::raw('SUM(stock_issue_items.amount) as value')
            );

        $query = InventoryItem::query()
            ->leftJoin('inventory_categories', 'inventory_categories.id', '=', 'inventory_items.inventory_category_id')
            ->leftJoinSub($receiptTotals, 'r', function ($join) {
                $join->on('r.inventory_item_id', '=', 'inventory_items.id');
            })
            ->leftJoinSub($issueTotals, 'i', function ($join) {
                $join->on('i.inventory_item_id', '=', 'inventory_items.id');
            })
            ->select(
                'inventory_items.item_code',
                'inventory_items.item_name',
                'inventory_items.uom',
                'inventory_categories.category_name',
                DB::raw('(inventory_items.opening_stock + COALESCE(r.qty,0) - COALESCE(i.qty,0)) as closing_qty'),
                DB::raw('(inventory_items.opening_value + COALESCE(r.value,0) - COALESCE(i.value,0)) as closing_value')
            );

        if ($categoryId) {
            $query->where('inventory_items.inventory_category_id', $categoryId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('inventory_items.item_code', 'like', "%{$search}%")
                    ->orWhere('inventory_items.item_name', 'like', "%{$search}%");
            });
        }

        return $query;
    }
}
