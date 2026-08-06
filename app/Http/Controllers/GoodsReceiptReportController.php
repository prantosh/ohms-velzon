<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoodsReceiptReportController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('apps-goods-receipt-report');
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL (one row per GRN line, for a date range)
    |--------------------------------------------------------------------------
    */

    public function detail(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $rows = $this->baseQuery($request->from_date, $request->to_date)
            ->orderBy('goods_receipts.receipt_date')
            ->orderBy('goods_receipts.receipt_no')
            ->get([
                'goods_receipts.receipt_no',
                'goods_receipts.receipt_date',
                'goods_receipts.vendor_name',
                'purchase_orders.po_no',
                'inventory_items.item_code',
                'inventory_items.item_name',
                'goods_receipt_items.uom',
                'goods_receipt_items.received_qty',
                'goods_receipt_items.unit_rate',
                'goods_receipt_items.amount',
            ])
            ->map(function ($row) {
                $row->receipt_date_fmt = Carbon::parse($row->receipt_date)->format('d-m-Y');
                $row->received_qty = round((float) $row->received_qty, 2);
                $row->unit_rate = round((float) $row->unit_rate, 2);
                $row->amount = round((float) $row->amount, 2);
                return $row;
            });

        return response()->json([
            'status' => true,
            'rows' => $rows->values(),
            'grand_total' => [
                'qty' => round($rows->sum('received_qty'), 2),
                'amount' => round($rows->sum('amount'), 2),
            ],
        ]);
    }

    public function printDetail(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $result = json_decode($this->detail($request)->getContent(), true);

        $pdf = Pdf::loadView('apps-goods-receipt-report-detail-pdf', [
            'rows' => $result['rows'],
            'grandTotal' => $result['grand_total'],
            'fromDate' => Carbon::parse($request->from_date),
            'toDate' => Carbon::parse($request->to_date),
            'printedBy' => optional(auth()->user())->name,
        ]);

        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream(
            'Goods-Receipt-Report-Detail-' .
            Carbon::parse($request->from_date)->format('d-m-Y') . '-to-' .
            Carbon::parse($request->to_date)->format('d-m-Y') . '.pdf'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY (by item, for a date range)
    |--------------------------------------------------------------------------
    */

    public function summary(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $rows = $this->baseQuery($request->from_date, $request->to_date)
            ->groupBy('inventory_items.id', 'inventory_items.item_code', 'inventory_items.item_name', 'goods_receipt_items.uom')
            ->orderBy('inventory_items.item_name')
            ->get([
                'inventory_items.item_code',
                'inventory_items.item_name',
                'goods_receipt_items.uom',
                DB::raw('SUM(goods_receipt_items.received_qty) as qty'),
                DB::raw('SUM(goods_receipt_items.amount) as amount'),
                DB::raw('COUNT(DISTINCT goods_receipts.id) as grn_count'),
            ])
            ->map(function ($row) {
                $row->qty = round((float) $row->qty, 2);
                $row->amount = round((float) $row->amount, 2);
                return $row;
            });

        return response()->json([
            'status' => true,
            'rows' => $rows->values(),
            'grand_total' => [
                'qty' => round($rows->sum('qty'), 2),
                'amount' => round($rows->sum('amount'), 2),
            ],
        ]);
    }

    public function printSummary(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $result = json_decode($this->summary($request)->getContent(), true);

        $pdf = Pdf::loadView('apps-goods-receipt-report-summary-pdf', [
            'rows' => $result['rows'],
            'grandTotal' => $result['grand_total'],
            'fromDate' => Carbon::parse($request->from_date),
            'toDate' => Carbon::parse($request->to_date),
            'printedBy' => optional(auth()->user())->name,
        ]);

        return $pdf->stream(
            'Goods-Receipt-Report-Summary-' .
            Carbon::parse($request->from_date)->format('d-m-Y') . '-to-' .
            Carbon::parse($request->to_date)->format('d-m-Y') . '.pdf'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY HELPER
    |--------------------------------------------------------------------------
    */

    private function baseQuery(string $fromDate, string $toDate)
    {
        return DB::table('goods_receipt_items')
            ->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
            ->join('inventory_items', 'inventory_items.id', '=', 'goods_receipt_items.inventory_item_id')
            ->leftJoin('purchase_orders', 'purchase_orders.id', '=', 'goods_receipts.purchase_order_id')
            ->whereBetween('goods_receipts.receipt_date', [$fromDate, $toDate]);
    }
}
