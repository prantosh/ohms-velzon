<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoodsIssueReportController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('apps-goods-issue-report');
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL (one row per issue line, for a date range)
    |--------------------------------------------------------------------------
    */

    public function detail(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $rows = $this->baseQuery($request->from_date, $request->to_date)
            ->orderBy('stock_issues.issue_date')
            ->orderBy('stock_issues.issue_no')
            ->get([
                'stock_issues.issue_no',
                'stock_issues.issue_date',
                'stock_issues.issued_to_name',
                'inventory_items.item_code',
                'inventory_items.item_name',
                'stock_issue_items.uom',
                'stock_issue_items.issue_qty',
                'stock_issue_items.unit_rate',
                'stock_issue_items.amount',
            ])
            ->map(function ($row) {
                $row->issue_date_fmt = Carbon::parse($row->issue_date)->format('d-m-Y');
                $row->issue_qty = round((float) $row->issue_qty, 2);
                $row->unit_rate = round((float) $row->unit_rate, 2);
                $row->amount = round((float) $row->amount, 2);
                return $row;
            });

        return response()->json([
            'status' => true,
            'rows' => $rows->values(),
            'grand_total' => [
                'qty' => round($rows->sum('issue_qty'), 2),
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

        $pdf = Pdf::loadView('apps-goods-issue-report-detail-pdf', [
            'rows' => $result['rows'],
            'grandTotal' => $result['grand_total'],
            'fromDate' => Carbon::parse($request->from_date),
            'toDate' => Carbon::parse($request->to_date),
            'printedBy' => optional(auth()->user())->name,
        ]);

        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream(
            'Goods-Issue-Report-Detail-' .
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
            ->groupBy('inventory_items.id', 'inventory_items.item_code', 'inventory_items.item_name', 'stock_issue_items.uom')
            ->orderBy('inventory_items.item_name')
            ->get([
                'inventory_items.item_code',
                'inventory_items.item_name',
                'stock_issue_items.uom',
                DB::raw('SUM(stock_issue_items.issue_qty) as qty'),
                DB::raw('SUM(stock_issue_items.amount) as amount'),
                DB::raw('COUNT(DISTINCT stock_issues.id) as issue_count'),
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

        $pdf = Pdf::loadView('apps-goods-issue-report-summary-pdf', [
            'rows' => $result['rows'],
            'grandTotal' => $result['grand_total'],
            'fromDate' => Carbon::parse($request->from_date),
            'toDate' => Carbon::parse($request->to_date),
            'printedBy' => optional(auth()->user())->name,
        ]);

        return $pdf->stream(
            'Goods-Issue-Report-Summary-' .
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
        return DB::table('stock_issue_items')
            ->join('stock_issues', 'stock_issues.id', '=', 'stock_issue_items.stock_issue_id')
            ->join('inventory_items', 'inventory_items.id', '=', 'stock_issue_items.inventory_item_id')
            ->whereBetween('stock_issues.issue_date', [$fromDate, $toDate]);
    }
}
