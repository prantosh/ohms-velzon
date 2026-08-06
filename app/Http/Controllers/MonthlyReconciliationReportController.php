<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItemMaster;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonthlyReconciliationReportController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Category name fallbacks for item_codes used in real invoice/settlement
    | data that have no matching row in invoice_item_masters. Membership
    | Fee/Income Other Source now write MEM001/DON001, which both have
    | real invoice_item_masters rows, so no fallback entries are needed
    | here currently -- kept as a mechanism for the next time this happens.
    |--------------------------------------------------------------------------
    */
    private const CATEGORY_FALLBACKS = [];

    private const UNCATEGORIZED = 'Uncategorized';

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('apps-monthly-reconciliation-report');
    }

    /*
    |--------------------------------------------------------------------------
    | REPORT (category-wise, for a selected month + year)
    |--------------------------------------------------------------------------
    */

    public function report(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|between:2000,2099',
        ]);

        $month = (int) $request->month;
        $year = (int) $request->year;

        $categoryTotals = $this->fetchCategoryTotals($month, $year);

        $rows = collect($categoryTotals)
            ->map(function ($cols, $name) {
                $receivedCash = round($cols['received_cash'], 2);
                $receivedNonCash = round($cols['received_noncash'], 2);
                $refundCash = round($cols['refund_cash'], 2);
                $doctorPaymentCash = round($cols['doctor_payment_cash'], 2);
                $doctorPaymentNonCashSource = round($cols['doctor_payment_noncash_source'], 2);

                $depositCash = round($receivedCash - $refundCash - $doctorPaymentCash, 2);

                return [
                    'category' => $name,
                    'received_cash' => $receivedCash,
                    'received_noncash' => $receivedNonCash,
                    'refund_cash' => $refundCash,
                    'doctor_payment_cash' => $doctorPaymentCash,
                    'deposit_cash' => $depositCash,
                    'doctor_payment_noncash_source' => $doctorPaymentNonCashSource,
                    'total_income' => round($depositCash + $receivedNonCash, 2),
                ];
            })
            ->sortBy('category')
            ->values();

        $grandTotal = [
            'received_cash' => round($rows->sum('received_cash'), 2),
            'received_noncash' => round($rows->sum('received_noncash'), 2),
            'refund_cash' => round($rows->sum('refund_cash'), 2),
            'doctor_payment_cash' => round($rows->sum('doctor_payment_cash'), 2),
            'doctor_payment_noncash_source' => round($rows->sum('doctor_payment_noncash_source'), 2),
        ];
        $grandTotal['deposit_cash'] = round($grandTotal['received_cash'] - $grandTotal['refund_cash'] - $grandTotal['doctor_payment_cash'], 2);
        $grandTotal['total_income'] = round($grandTotal['deposit_cash'] + $grandTotal['received_noncash'], 2);

        return response()->json([
            'status' => true,
            'month' => $month,
            'year' => $year,
            'rows' => $rows,
            'grand_total' => $grandTotal,
        ]);
    }

    public function print(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|between:2000,2099',
        ]);

        $result = json_decode($this->report($request)->getContent(), true);

        $monthName = Carbon::createFromDate($result['year'], $result['month'], 1)->format('F Y');

        $pdf = Pdf::loadView('apps-monthly-reconciliation-report-pdf', [
            'rows' => $result['rows'],
            'grandTotal' => $result['grand_total'],
            'monthName' => $monthName,
            'printedBy' => optional(auth()->user())->name,
        ]);

        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream('Monthly-Reconciliation-Report-' . str_replace(' ', '-', $monthName) . '.pdf');
    }

    /*
    |--------------------------------------------------------------------------
    | AGGREGATION
    |--------------------------------------------------------------------------
    */

    private function fetchCategoryTotals(int $month, int $year): array
    {
        $itemCodeToName = InvoiceItemMaster::pluck('item_name', 'item_code')->toArray();
        $itemCodeToName = array_merge($itemCodeToName, self::CATEGORY_FALLBACKS);

        $categoryTotals = [];

        $this->accumulateReceiptsAndRefunds($categoryTotals, $itemCodeToName, $month, $year);
        $this->accumulateDoctorPayments($categoryTotals, $itemCodeToName, $month, $year);

        return $categoryTotals;
    }

    /**
     * Received Cash / Received Non-Cash: split by the RECEIVED row's own
     * payment_mode (how the patient actually paid).
     *
     * Refund Cash: ALL refunds, regardless of payment_mode -- refunds are
     * always physically disbursed in cash, even when the original payment
     * was non-cash.
     *
     * daily_transactions rows are invoice-level; when an invoice spans
     * multiple categories (invoice_details.item_code), the amount is
     * apportioned across categories by each category's share of that
     * invoice's line total.
     */
    private function accumulateReceiptsAndRefunds(array &$categoryTotals, array $itemCodeToName, int $month, int $year): void
    {
        $transactions = DB::table('daily_transactions as dt')
            ->leftJoin('invoices as inv', 'inv.invoice_no', '=', 'dt.invoice_reference')
            ->whereIn('dt.transaction_type', ['RECEIVED', 'REFUND'])
            ->where('dt.status', '!=', 'CANCELLED')
            ->whereYear('dt.transaction_date', $year)
            ->whereMonth('dt.transaction_date', $month)
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
                ? ['DOC001' => 1.0]
                : ($weightMaps[$t->invoice_no] ?? [self::UNCATEGORIZED => 1.0]);

            $isCash = $t->payment_mode === 'Cash';

            foreach ($weights as $itemCode => $share) {
                $categoryName = $itemCodeToName[$itemCode] ?? self::UNCATEGORIZED;

                if ($t->transaction_type === 'RECEIVED') {
                    $this->addAmount($categoryTotals, $categoryName, $isCash ? 'received_cash' : 'received_noncash', (float) $t->received_amount * $share);
                } else {
                    // Refunds are always cash disbursements, regardless of the
                    // original payment's mode -- count every refund here.
                    $this->addAmount($categoryTotals, $categoryName, 'refund_cash', (float) $t->refund_amount * $share);
                }
            }
        }
    }

    /**
     * Per-invoice category weight map (invoice_no => [item_code => share of that invoice's line total]),
     * built from invoice_details for every non-DOCTOR_VISIT invoice touched this month.
     */
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

    /**
     * Doctor Payment Cash: ALL doctor settlement payments for the month --
     * doctors are always physically paid in cash by the settling user,
     * regardless of the settlement's own recorded payment_mode.
     *
     * Doctor Payment (Non-Cash Source), informational only, already included
     * in Doctor Payment Cash above, never subtracted separately: the portion
     * of that cash payout that traces back to invoices whose patient payment
     * was originally collected in non-cash mode (invoices.payment_mode).
     */
    private function accumulateDoctorPayments(array &$categoryTotals, array $itemCodeToName, int $month, int $year): void
    {
        $settlementRows = DB::table('doctor_settlement_items as dsi')
            ->join('doctor_settlements as ds', 'ds.id', '=', 'dsi.settlement_id')
            ->leftJoin('invoices as inv', 'inv.id', '=', 'dsi.invoice_id')
            ->where('ds.status', 'COMPLETED')
            ->whereYear('ds.settlement_date', $year)
            ->whereMonth('ds.settlement_date', $month)
            ->select(
                'dsi.item_code',
                'dsi.invoice_type',
                DB::raw("COALESCE(inv.payment_mode, 'Cash') as source_payment_mode"),
                DB::raw('SUM(dsi.settlement_amount) as total')
            )
            ->groupBy('dsi.item_code', 'dsi.invoice_type', 'source_payment_mode')
            ->get();

        foreach ($settlementRows as $s) {
            // DOCTOR_VISIT settlement items are stored with an empty item_code
            // (no line-item exists for a consultation) -- attribute to DOC001 explicitly.
            $itemCode = $s->invoice_type === 'DOCTOR_VISIT' ? 'DOC001' : $s->item_code;
            $categoryName = $itemCodeToName[$itemCode] ?? self::UNCATEGORIZED;
            $amount = (float) $s->total;

            $this->addAmount($categoryTotals, $categoryName, 'doctor_payment_cash', $amount);

            if ($s->source_payment_mode !== 'Cash') {
                $this->addAmount($categoryTotals, $categoryName, 'doctor_payment_noncash_source', $amount);
            }
        }
    }

    private function addAmount(array &$categoryTotals, string $category, string $column, float $amount): void
    {
        if (!isset($categoryTotals[$category])) {
            $categoryTotals[$category] = [
                'received_cash' => 0.0,
                'received_noncash' => 0.0,
                'refund_cash' => 0.0,
                'doctor_payment_cash' => 0.0,
                'doctor_payment_noncash_source' => 0.0,
            ];
        }

        $categoryTotals[$category][$column] += $amount;
    }
}
