<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItemMaster;
use App\Services\InvoicePrintLinkResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Merged dashboard for what used to be two separate pages: an all-items
 * accounting rollup (formerly ItemWiseSummaryReportController::summary(),
 * ported verbatim as allItemsSummary()) and a single-item drill-down that
 * used to exist as two near-duplicate detail views (one with payment_status,
 * one with payment_mode/is_settled) -- now one merged detail() carrying both.
 */
class ItemWiseReportController extends Controller
{
    private const DOCTOR_VISIT_ITEM_CODE = 'DOC001';

    // Item name fallbacks for item_codes used in real invoice/settlement data
    // that have no matching row in invoice_item_masters. Membership Fee/
    // Income Other Source now write MEM001/DON001, which both have real
    // invoice_item_masters rows, so no fallback entries are needed here
    // currently -- kept as a mechanism for the next time this happens.
    private const NAME_FALLBACKS = [];

    private const UNCATEGORIZED = 'Uncategorized';

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
    | TAB 1: ALL ITEMS SUMMARY (every item_code billed within the period)
    |--------------------------------------------------------------------------
    */

    public function allItemsSummary(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $fromDate = $request->from_date;
        $toDate = $request->to_date;

        $itemCodeToName = InvoiceItemMaster::pluck('item_name', 'item_code')->toArray();
        $itemCodeToName = array_merge($itemCodeToName, self::NAME_FALLBACKS);

        $totals = [];

        $this->accumulateReceivableAndSettled($totals, $fromDate, $toDate);
        $this->accumulateReceiptsAndRefunds($totals, $fromDate, $toDate);
        $this->accumulateDoctorPayments($totals, $fromDate, $toDate);

        $rows = collect($totals)
            ->map(function ($cols, $itemCode) use ($itemCodeToName) {
                $receivedCash = round($cols['received_cash'], 2);
                $receivedNonCash = round($cols['received_noncash'], 2);
                $refundCash = round($cols['refund_cash'], 2);
                $doctorPaymentCash = round($cols['doctor_payment_cash'], 2);
                $depositCash = round($receivedCash - $refundCash - $doctorPaymentCash, 2);

                return [
                    'item_code' => $itemCode,
                    'item_name' => $itemCodeToName[$itemCode] ?? self::UNCATEGORIZED,
                    'invoice_count' => (int) $cols['invoice_count'],
                    'receivable' => round($cols['receivable'], 2),
                    'settled_amount' => round($cols['settled'], 2),
                    'received_cash' => $receivedCash,
                    'received_noncash' => $receivedNonCash,
                    'received_total' => round($receivedCash + $receivedNonCash, 2),
                    'refund_cash' => $refundCash,
                    'doctor_payment_cash' => $doctorPaymentCash,
                    'deposited' => round($depositCash + $receivedNonCash, 2),
                ];
            })
            ->sortBy('item_name')
            ->values();

        $grandTotal = [
            'invoice_count' => (int) $rows->sum('invoice_count'),
            'receivable' => round($rows->sum('receivable'), 2),
            'settled_amount' => round($rows->sum('settled_amount'), 2),
            'received_cash' => round($rows->sum('received_cash'), 2),
            'received_noncash' => round($rows->sum('received_noncash'), 2),
            'received_total' => round($rows->sum('received_total'), 2),
            'refund_cash' => round($rows->sum('refund_cash'), 2),
            'doctor_payment_cash' => round($rows->sum('doctor_payment_cash'), 2),
            'deposited' => round($rows->sum('deposited'), 2),
        ];

        return response()->json([
            'status' => true,
            'rows' => $rows,
            'grand_total' => $grandTotal,
        ]);
    }

    public function printAllItemsSummary(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $result = json_decode($this->allItemsSummary($request)->getContent(), true);

        $pdf = Pdf::loadView('apps-item-wise-summary-report-summary-pdf', [
            'rows' => $result['rows'],
            'grandTotal' => $result['grand_total'],
            'fromDate' => Carbon::parse($request->from_date),
            'toDate' => Carbon::parse($request->to_date),
            'printedBy' => optional(auth()->user())->name,
        ]);

        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream(
            'Item-Wise-All-Items-Summary-' .
            Carbon::parse($request->from_date)->format('d-m-Y') . '-to-' .
            Carbon::parse($request->to_date)->format('d-m-Y') . '.pdf'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TAB 2: ITEM DETAIL (one row per invoice, for a single item_code) --
    | carries both payment_status (Partial/Final/Full, phased-payment-derived)
    | and payment_mode/is_settled, merged from the two formerly-separate
    | detail views.
    |--------------------------------------------------------------------------
    */

    public function detail(Request $request)
    {
        $request->validate([
            'item_code' => 'required|string',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $itemCode = $request->item_code;
        $itemName = InvoiceItemMaster::where('item_code', $itemCode)->value('item_name')
            ?? self::NAME_FALLBACKS[$itemCode]
            ?? self::UNCATEGORIZED;

        $rows = $this->fetchDetailRows($itemCode, $request->from_date, $request->to_date)
            ->map(fn ($row) => $this->decorateDetailRow($row));

        return response()->json([
            'status' => true,
            'item_name' => $itemName,
            'rows' => $rows->values(),
            'grand_total' => [
                'invoice_count' => $rows->count(),
                'amount' => round($rows->sum('item_amount'), 2),
                'settled_amount' => round($rows->where('is_settled', true)->sum('item_amount'), 2),
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
    | TAB 3: ITEM SUMMARY (by date, and by test/sub-item or doctor)
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
    | AGGREGATION HELPERS (Tab 1 -- all-items summary)
    |--------------------------------------------------------------------------
    */

    // Receivable = total billed amount per item_code for invoices raised in the
    // period. Settled = the portion of that belonging to invoices that are now
    // fully paid off (due_amount <= 0) -- not partially-paid ones.
    private function accumulateReceivableAndSettled(array &$totals, string $fromDate, string $toDate): void
    {
        $nonCancelled = function ($q) {
            $q->whereNull('inv.cancelled')->orWhere('inv.cancelled', '!=', 'Y');
        };

        $rows = DB::table('invoices as inv')
            ->join('invoice_details as idt', 'idt.invoice_no', '=', 'inv.invoice_no')
            ->whereBetween('inv.invoice_date', [$fromDate, $toDate])
            ->where($nonCancelled)
            ->groupBy('idt.item_code')
            ->select(
                'idt.item_code',
                DB::raw('COUNT(DISTINCT inv.id) as invoice_count'),
                DB::raw('SUM(idt.amount) as receivable'),
                DB::raw('SUM(CASE WHEN inv.due_amount <= 0 THEN idt.amount ELSE 0 END) as settled')
            )
            ->get();

        foreach ($rows as $r) {
            $code = $r->item_code ?: self::UNCATEGORIZED;
            $this->addAmount($totals, $code, 'invoice_count', (float) $r->invoice_count);
            $this->addAmount($totals, $code, 'receivable', (float) $r->receivable);
            $this->addAmount($totals, $code, 'settled', (float) $r->settled);
        }

        // DOCTOR_VISIT invoices carry no invoice_details rows -- read straight
        // off invoices, same as the item_code === DOCTOR_VISIT_ITEM_CODE
        // special case used everywhere else in this controller.
        $docRow = DB::table('invoices as inv')
            ->where('inv.item_code', self::DOCTOR_VISIT_ITEM_CODE)
            ->whereBetween('inv.invoice_date', [$fromDate, $toDate])
            ->where($nonCancelled)
            ->select(
                DB::raw('COUNT(*) as invoice_count'),
                DB::raw('SUM(inv.total_amount) as receivable'),
                DB::raw('SUM(CASE WHEN inv.due_amount <= 0 THEN inv.total_amount ELSE 0 END) as settled')
            )
            ->first();

        if ($docRow && (int) $docRow->invoice_count > 0) {
            $this->addAmount($totals, self::DOCTOR_VISIT_ITEM_CODE, 'invoice_count', (float) $docRow->invoice_count);
            $this->addAmount($totals, self::DOCTOR_VISIT_ITEM_CODE, 'receivable', (float) $docRow->receivable);
            $this->addAmount($totals, self::DOCTOR_VISIT_ITEM_CODE, 'settled', (float) $docRow->settled);
        }
    }

    // Received Cash / Received Non-Cash: split by the RECEIVED transaction's own
    // payment_mode. Refund Cash: ALL refunds, regardless of payment_mode --
    // refunds are always physically disbursed in cash. Filtered by the
    // transaction's own date (cash movement during the period), not invoice_date.
    private function accumulateReceiptsAndRefunds(array &$totals, string $fromDate, string $toDate): void
    {
        $transactions = DB::table('daily_transactions as dt')
            ->leftJoin('invoices as inv', 'inv.invoice_no', '=', 'dt.invoice_reference')
            ->whereIn('dt.transaction_type', ['RECEIVED', 'REFUND'])
            ->where('dt.status', '!=', 'CANCELLED')
            ->whereBetween('dt.transaction_date', [$fromDate, $toDate])
            ->select(
                'dt.transaction_type',
                'dt.payment_mode',
                'dt.received_amount',
                'dt.refund_amount',
                'inv.invoice_type',
                DB::raw('COALESCE(inv.invoice_no, dt.invoice_reference) as invoice_no')
            )
            ->get();

        $invoiceNosNeedingWeights = $transactions
            ->filter(fn ($t) => $t->invoice_type !== 'DOCTOR_VISIT')
            ->pluck('invoice_no')
            ->filter()
            ->unique()
            ->values();

        $weightMaps = $this->buildInvoiceWeightMaps($invoiceNosNeedingWeights);

        foreach ($transactions as $t) {
            $weights = $t->invoice_type === 'DOCTOR_VISIT'
                ? [self::DOCTOR_VISIT_ITEM_CODE => 1.0]
                : ($weightMaps[$t->invoice_no] ?? [self::UNCATEGORIZED => 1.0]);

            $isCash = $t->payment_mode === 'Cash';

            foreach ($weights as $itemCode => $share) {
                if ($t->transaction_type === 'RECEIVED') {
                    $this->addAmount($totals, $itemCode, $isCash ? 'received_cash' : 'received_noncash', (float) $t->received_amount * $share);
                } else {
                    $this->addAmount($totals, $itemCode, 'refund_cash', (float) $t->refund_amount * $share);
                }
            }
        }
    }

    private function buildInvoiceWeightMaps($invoiceNos): array
    {
        if ($invoiceNos->isEmpty()) {
            return [];
        }

        $detailRows = DB::table('invoice_details')
            ->whereIn('invoice_no', $invoiceNos)
            ->select('invoice_no', 'item_code', DB::raw('SUM(amount) as line_total'))
            ->groupBy('invoice_no', 'item_code')
            ->get();

        $invoiceTotals = [];
        foreach ($detailRows as $d) {
            $invoiceTotals[$d->invoice_no] = ($invoiceTotals[$d->invoice_no] ?? 0) + (float) $d->line_total;
        }

        $weightMaps = [];
        foreach ($detailRows as $d) {
            $total = $invoiceTotals[$d->invoice_no] ?? 0;
            $code = $d->item_code ?: self::UNCATEGORIZED;
            $weightMaps[$d->invoice_no][$code] = $total > 0 ? ((float) $d->line_total / $total) : 0;
        }

        return $weightMaps;
    }

    // Doctor Payment Cash: ALL doctor settlement payments in the period --
    // doctors are always physically paid in cash by the settling user,
    // regardless of the settlement's own recorded payment_mode. Keyed by the
    // settlement's own date, not invoice_date.
    private function accumulateDoctorPayments(array &$totals, string $fromDate, string $toDate): void
    {
        $settlementRows = DB::table('doctor_settlement_items as dsi')
            ->join('doctor_settlements as ds', 'ds.id', '=', 'dsi.settlement_id')
            ->where('ds.status', 'COMPLETED')
            ->whereBetween('ds.settlement_date', [$fromDate, $toDate])
            ->select(
                'dsi.item_code',
                'dsi.invoice_type',
                DB::raw('SUM(dsi.settlement_amount) as total')
            )
            ->groupBy('dsi.item_code', 'dsi.invoice_type')
            ->get();

        foreach ($settlementRows as $s) {
            // DOCTOR_VISIT settlement items are stored with an empty item_code
            // (no line-item exists for a consultation) -- attribute to DOC001 explicitly.
            $itemCode = $s->invoice_type === 'DOCTOR_VISIT' ? self::DOCTOR_VISIT_ITEM_CODE : ($s->item_code ?: self::UNCATEGORIZED);
            $this->addAmount($totals, $itemCode, 'doctor_payment_cash', (float) $s->total);
        }
    }

    private function addAmount(array &$totals, string $itemCode, string $column, float $amount): void
    {
        if (!isset($totals[$itemCode])) {
            $totals[$itemCode] = [
                'invoice_count' => 0.0,
                'receivable' => 0.0,
                'settled' => 0.0,
                'received_cash' => 0.0,
                'received_noncash' => 0.0,
                'refund_cash' => 0.0,
                'doctor_payment_cash' => 0.0,
            ];
        }

        $totals[$itemCode][$column] += $amount;
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY HELPERS (Tab 2 -- item detail)
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
                    'invoices.payment_mode', 'invoices.invoice_type',
                ]);
        }

        return DB::table('invoices')
            ->join('invoice_details', 'invoices.invoice_no', '=', 'invoice_details.invoice_no')
            ->where('invoice_details.item_code', $itemCode)
            ->whereBetween('invoices.invoice_date', [$fromDate, $toDate])
            ->groupBy(
                'invoices.id', 'invoices.invoice_no', 'invoices.invoice_date', 'invoices.patient_name',
                'invoices.due_amount', 'invoices.paid_amount_1', 'invoices.paid_amount_2',
                'invoices.payment_mode', 'invoices.invoice_type'
            )
            ->orderBy('invoices.invoice_date')
            ->orderBy('invoices.invoice_no')
            ->get([
                'invoices.id', 'invoices.invoice_no', 'invoices.invoice_date', 'invoices.patient_name',
                DB::raw('GROUP_CONCAT(DISTINCT invoice_details.item_description SEPARATOR ", ") as sub_item_label'),
                DB::raw('SUM(invoice_details.amount) as item_amount'),
                'invoices.due_amount', 'invoices.paid_amount_1', 'invoices.paid_amount_2',
                'invoices.payment_mode', 'invoices.invoice_type',
            ]);
    }

    private function decorateDetailRow($row)
    {
        $row->invoice_date_fmt = Carbon::parse($row->invoice_date)->format('d-m-Y');
        $row->item_amount = round((float) $row->item_amount, 2);
        $row->payment_mode = $row->payment_mode ?: 'Not Recorded';

        $printLinks = InvoicePrintLinkResolver::resolve($row->invoice_type, (int) $row->id);
        $row->print_url = $printLinks['print_url'];
        $row->print_ajax_url = $printLinks['print_ajax_url'];

        $isFullyPaid = (float) $row->due_amount <= 0;
        $wasPhased = !empty($row->paid_amount_1) || !empty($row->paid_amount_2);

        $row->payment_status = !$isFullyPaid
            ? 'Partial'
            : ($wasPhased ? 'Final' : 'Full');

        $row->is_settled = $isFullyPaid;

        return $row;
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY HELPERS (Tab 3 -- item summary)
    |--------------------------------------------------------------------------
    */

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
}
