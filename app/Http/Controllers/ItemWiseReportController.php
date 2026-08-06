<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItemMaster;
use App\Services\InvoicePrintLinkResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemWiseReportController extends Controller
{
    private const DOCTOR_VISIT_ITEM_CODE = 'DOC001';

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $itemMasters = InvoiceItemMaster::where('status', 1)
            ->orderBy('item_name')
            ->get(['item_code', 'item_name']);

        return view('apps-item-wise-report', compact('itemMasters'));
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL (one row per invoice, item-code's own line amount summed)
    |--------------------------------------------------------------------------
    */

    public function detail(Request $request)
    {
        $request->validate([
            'item_code' => 'required|string',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $itemName = InvoiceItemMaster::where('item_code', $request->item_code)->value('item_name');

        $rows = $this->fetchDetailRows($request->item_code, $request->from_date, $request->to_date)
            ->map(fn ($row) => $this->decorateDetailRow($row));

        return response()->json([
            'status' => true,
            'item_name' => $itemName,
            'rows' => $rows->values(),
            'grand_total' => [
                'invoice_count' => $rows->count(),
                'amount' => round($rows->sum('item_amount'), 2),
            ],
        ]);
    }

    public function printDetail(Request $request)
    {
        $request->validate([
            'item_code' => 'required|string',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $result = json_decode($this->detail($request)->getContent(), true);

        $pdf = Pdf::loadView('apps-item-wise-report-detail-pdf', [
            'rows' => $result['rows'],
            'grandTotal' => $result['grand_total'],
            'itemName' => $result['item_name'],
            'fromDate' => Carbon::parse($request->from_date),
            'toDate' => Carbon::parse($request->to_date),
            'printedBy' => optional(auth()->user())->name,
        ]);

        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream(
            'Item-Wise-Detail-' . $request->item_code . '-' .
            Carbon::parse($request->from_date)->format('d-m-Y') . '-to-' .
            Carbon::parse($request->to_date)->format('d-m-Y') . '.pdf'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY (by date, and by test/sub-item or doctor)
    |--------------------------------------------------------------------------
    */

    public function summary(Request $request)
    {
        $request->validate([
            'item_code' => 'required|string',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $itemName = InvoiceItemMaster::where('item_code', $request->item_code)->value('item_name');

        $byDate = $this->fetchByDateSummary($request->item_code, $request->from_date, $request->to_date);
        $bySubItem = $this->fetchBySubItemSummary($request->item_code, $request->from_date, $request->to_date);

        return response()->json([
            'status' => true,
            'item_name' => $itemName,
            'by_date' => $byDate,
            'by_sub_item' => $bySubItem,
            'grand_total' => [
                'invoice_count' => (int) $byDate->sum('invoice_count'),
                'amount' => round($byDate->sum('amount'), 2),
            ],
        ]);
    }

    public function printSummary(Request $request)
    {
        $request->validate([
            'item_code' => 'required|string',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $result = json_decode($this->summary($request)->getContent(), true);

        $pdf = Pdf::loadView('apps-item-wise-report-summary-pdf', [
            'byDate' => $result['by_date'],
            'bySubItem' => $result['by_sub_item'],
            'grandTotal' => $result['grand_total'],
            'itemName' => $result['item_name'],
            'fromDate' => Carbon::parse($request->from_date),
            'toDate' => Carbon::parse($request->to_date),
            'printedBy' => optional(auth()->user())->name,
        ]);

        return $pdf->stream(
            'Item-Wise-Summary-' . $request->item_code . '-' .
            Carbon::parse($request->from_date)->format('d-m-Y') . '-to-' .
            Carbon::parse($request->to_date)->format('d-m-Y') . '.pdf'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY HELPERS
    |--------------------------------------------------------------------------
    */

    private function fetchDetailRows(string $itemCode, string $fromDate, string $toDate)
    {
        if ($itemCode === self::DOCTOR_VISIT_ITEM_CODE) {

            // invoices.doctor_name is not populated for DOCTOR_VISIT rows --
            // the real name lives on the doctors table via doctor_id.
            return DB::table('invoices')
                ->leftJoin('doctors', 'doctors.id', '=', 'invoices.doctor_id')
                ->where('invoices.item_code', self::DOCTOR_VISIT_ITEM_CODE)
                ->whereBetween('invoices.invoice_date', [$fromDate, $toDate])
                ->orderBy('invoices.invoice_date')
                ->orderBy('invoices.invoice_no')
                ->get([
                    'invoices.id', 'invoices.invoice_no', 'invoices.invoice_date', 'invoices.patient_name',
                    'doctors.doctor_name as sub_item_label',
                    'invoices.total_amount as item_amount',
                    'invoices.due_amount', 'invoices.paid_amount_1', 'invoices.paid_amount_2',
                    'invoices.invoice_type',
                ]);
        }

        return DB::table('invoices')
            ->join('invoice_details', 'invoices.invoice_no', '=', 'invoice_details.invoice_no')
            ->where('invoice_details.item_code', $itemCode)
            ->whereBetween('invoices.invoice_date', [$fromDate, $toDate])
            ->groupBy(
                'invoices.id', 'invoices.invoice_no', 'invoices.invoice_date', 'invoices.patient_name',
                'invoices.due_amount', 'invoices.paid_amount_1', 'invoices.paid_amount_2', 'invoices.invoice_type'
            )
            ->orderBy('invoices.invoice_date')
            ->orderBy('invoices.invoice_no')
            ->get([
                'invoices.id', 'invoices.invoice_no', 'invoices.invoice_date', 'invoices.patient_name',
                DB::raw('GROUP_CONCAT(DISTINCT invoice_details.item_description SEPARATOR ", ") as sub_item_label'),
                DB::raw('SUM(invoice_details.amount) as item_amount'),
                'invoices.due_amount', 'invoices.paid_amount_1', 'invoices.paid_amount_2', 'invoices.invoice_type',
            ]);
    }

    private function fetchByDateSummary(string $itemCode, string $fromDate, string $toDate)
    {
        if ($itemCode === self::DOCTOR_VISIT_ITEM_CODE) {

            $rows = DB::table('invoices')
                ->where('item_code', self::DOCTOR_VISIT_ITEM_CODE)
                ->whereBetween('invoice_date', [$fromDate, $toDate])
                ->groupBy('invoice_date')
                ->orderBy('invoice_date')
                ->get([
                    'invoice_date',
                    DB::raw('COUNT(*) as invoice_count'),
                    DB::raw('SUM(total_amount) as amount'),
                ]);

        } else {

            $rows = DB::table('invoices')
                ->join('invoice_details', 'invoices.invoice_no', '=', 'invoice_details.invoice_no')
                ->where('invoice_details.item_code', $itemCode)
                ->whereBetween('invoices.invoice_date', [$fromDate, $toDate])
                ->groupBy('invoices.invoice_date')
                ->orderBy('invoices.invoice_date')
                ->get([
                    'invoices.invoice_date',
                    DB::raw('COUNT(DISTINCT invoices.id) as invoice_count'),
                    DB::raw('SUM(invoice_details.amount) as amount'),
                ]);
        }

        return $rows->map(function ($row) {
            $row->date_fmt = Carbon::parse($row->invoice_date)->format('d-m-Y (D)');
            $row->amount = round((float) $row->amount, 2);
            return $row;
        });
    }

    private function fetchBySubItemSummary(string $itemCode, string $fromDate, string $toDate)
    {
        if ($itemCode === self::DOCTOR_VISIT_ITEM_CODE) {

            // invoices.doctor_name is not populated for DOCTOR_VISIT rows --
            // the real name lives on the doctors table via doctor_id.
            $rows = DB::table('invoices')
                ->leftJoin('doctors', 'doctors.id', '=', 'invoices.doctor_id')
                ->where('invoices.item_code', self::DOCTOR_VISIT_ITEM_CODE)
                ->whereBetween('invoices.invoice_date', [$fromDate, $toDate])
                ->groupBy('doctors.doctor_name')
                ->orderByRaw('SUM(invoices.total_amount) DESC')
                ->get([
                    DB::raw("COALESCE(doctors.doctor_name, 'Unspecified') as label"),
                    DB::raw('COUNT(*) as invoice_count'),
                    DB::raw('SUM(invoices.total_amount) as amount'),
                ]);

        } else {

            $rows = DB::table('invoice_details')
                ->join('invoices', 'invoices.invoice_no', '=', 'invoice_details.invoice_no')
                ->where('invoice_details.item_code', $itemCode)
                ->whereBetween('invoices.invoice_date', [$fromDate, $toDate])
                ->groupBy('invoice_details.item_description')
                ->orderByRaw('SUM(invoice_details.amount) DESC')
                ->get([
                    DB::raw("COALESCE(invoice_details.item_description, 'Unspecified') as label"),
                    DB::raw('COUNT(*) as invoice_count'),
                    DB::raw('SUM(invoice_details.amount) as amount'),
                ]);
        }

        return $rows->map(function ($row) {
            $row->amount = round((float) $row->amount, 2);
            return $row;
        });
    }

    private function decorateDetailRow($row)
    {
        $row->invoice_date_fmt = Carbon::parse($row->invoice_date)->format('d-m-Y');
        $row->item_amount = round((float) $row->item_amount, 2);

        $printLinks = InvoicePrintLinkResolver::resolve($row->invoice_type, (int) $row->id);
        $row->print_url = $printLinks['print_url'];
        $row->print_ajax_url = $printLinks['print_ajax_url'];

        $isFullyPaid = (float) $row->due_amount <= 0;
        $wasPhased = !empty($row->paid_amount_1) || !empty($row->paid_amount_2);

        $row->payment_status = !$isFullyPaid
            ? 'Partial'
            : ($wasPhased ? 'Final' : 'Full');

        return $row;
    }
}
