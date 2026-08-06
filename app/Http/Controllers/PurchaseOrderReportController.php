<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderReportController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('apps-purchase-order-report');
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL (one row per PO line, for a date range)
    |--------------------------------------------------------------------------
    */

    public function detail(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $rows = $this->baseQuery($request->from_date, $request->to_date)
            ->orderBy('purchase_orders.po_date')
            ->orderBy('purchase_orders.po_no')
            ->get([
                'purchase_orders.po_no',
                'purchase_orders.po_date',
                'purchase_orders.vendor_name',
                'purchase_orders.status',
                'inventory_items.item_code',
                'inventory_items.item_name',
                'purchase_order_items.uom',
                'purchase_order_items.po_qty',
                'purchase_order_items.unit_rate',
                'purchase_order_items.gst_percent',
                'purchase_order_items.amount',
                'purchase_order_items.received_qty',
            ])
            ->map(function ($row) {
                $row->po_date_fmt = Carbon::parse($row->po_date)->format('d-m-Y');
                $row->po_qty = round((float) $row->po_qty, 2);
                $row->unit_rate = round((float) $row->unit_rate, 2);
                $row->amount = round((float) $row->amount, 2);
                $row->received_qty = round((float) $row->received_qty, 2);
                return $row;
            });

        return response()->json([
            'status' => true,
            'rows' => $rows->values(),
            'grand_total' => [
                'qty' => round($rows->sum('po_qty'), 2),
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

        $pdf = Pdf::loadView('apps-purchase-order-report-detail-pdf', [
            'rows' => $result['rows'],
            'grandTotal' => $result['grand_total'],
            'fromDate' => Carbon::parse($request->from_date),
            'toDate' => Carbon::parse($request->to_date),
            'printedBy' => optional(auth()->user())->name,
        ]);

        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream(
            'Purchase-Order-Report-Detail-' .
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
            ->groupBy('inventory_items.id', 'inventory_items.item_code', 'inventory_items.item_name', 'purchase_order_items.uom')
            ->orderBy('inventory_items.item_name')
            ->get([
                'inventory_items.item_code',
                'inventory_items.item_name',
                'purchase_order_items.uom',
                DB::raw('SUM(purchase_order_items.po_qty) as qty'),
                DB::raw('SUM(purchase_order_items.amount) as amount'),
                DB::raw('COUNT(DISTINCT purchase_orders.id) as po_count'),
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

        $pdf = Pdf::loadView('apps-purchase-order-report-summary-pdf', [
            'rows' => $result['rows'],
            'grandTotal' => $result['grand_total'],
            'fromDate' => Carbon::parse($request->from_date),
            'toDate' => Carbon::parse($request->to_date),
            'printedBy' => optional(auth()->user())->name,
        ]);

        return $pdf->stream(
            'Purchase-Order-Report-Summary-' .
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
        return DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->join('inventory_items', 'inventory_items.id', '=', 'purchase_order_items.inventory_item_id')
            ->whereBetween('purchase_orders.po_date', [$fromDate, $toDate]);
    }
}
