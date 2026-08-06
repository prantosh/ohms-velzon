<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\InvoicePrintLinkResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class DailyTransactionReportController extends Controller
{
    private const TYPE_LABELS = [
        'RECEIVED' => 'Collection',
        'REFUND' => 'Refund',
        'PAYMENT' => 'Payment',
    ];

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('apps-daily-transaction-report');
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY (date range, grouped by day, cash/non-cash split)
    |--------------------------------------------------------------------------
    */

    public function summary(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $rows = DB::table('daily_transactions')
            ->whereBetween('transaction_date', [$request->from_date, $request->to_date])
            ->where('status', '!=', 'CANCELLED')
            ->whereIn('transaction_type', ['RECEIVED', 'REFUND', 'PAYMENT'])
            ->selectRaw("
                transaction_date,
                SUM(CASE WHEN transaction_type = 'RECEIVED' AND payment_mode = 'Cash' THEN received_amount ELSE 0 END) as collection_cash,
                SUM(CASE WHEN transaction_type = 'RECEIVED' AND payment_mode != 'Cash' THEN received_amount ELSE 0 END) as collection_noncash,
                SUM(CASE WHEN transaction_type = 'REFUND' AND payment_mode = 'Cash' THEN refund_amount ELSE 0 END) as refund_cash,
                SUM(CASE WHEN transaction_type = 'REFUND' AND payment_mode != 'Cash' THEN refund_amount ELSE 0 END) as refund_noncash,
                SUM(CASE WHEN transaction_type = 'PAYMENT' AND payment_mode = 'Cash' THEN doctor_payment_amount ELSE 0 END) as payment_cash,
                SUM(CASE WHEN transaction_type = 'PAYMENT' AND payment_mode != 'Cash' THEN doctor_payment_amount ELSE 0 END) as payment_noncash
            ")
            ->groupBy('transaction_date')
            ->orderBy('transaction_date')
            ->get()
            ->map(function ($row) {
                return $this->decorateSummaryRow($row);
            });

        return response()->json([
            'status' => true,
            'rows' => $rows->values(),
            'grand_total' => $this->sumSummaryRows($rows),
        ]);
    }

    public function printSummary(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $result = json_decode($this->summary($request)->getContent(), true);

        $pdf = Pdf::loadView(
            'apps-daily-transaction-summary-pdf',
            [
                'rows' => $result['rows'],
                'grandTotal' => $result['grand_total'],
                'fromDate' => Carbon::parse($request->from_date),
                'toDate' => Carbon::parse($request->to_date),
                'printedBy' => optional(auth()->user())->name,
            ]
        );

        return $pdf->stream(
            'Daily-Transaction-Summary-' .
            Carbon::parse($request->from_date)->format('d-m-Y') . '-to-' .
            Carbon::parse($request->to_date)->format('d-m-Y') . '.pdf'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL (single day, every record)
    |--------------------------------------------------------------------------
    */

    public function detail(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'payment_mode' => 'nullable|in:CASH,NON-CASH,ALL',
        ]);

        $query = DB::table('daily_transactions as dt')
            ->leftJoin('invoices as inv', 'inv.invoice_no', '=', 'dt.invoice_reference')
            ->leftJoin('doctors as doc', 'doc.id', '=', 'inv.doctor_id')
            ->whereDate('dt.transaction_date', $request->date)
            ->where('dt.status', '!=', 'CANCELLED')
            ->whereIn('dt.transaction_type', ['RECEIVED', 'REFUND', 'PAYMENT']);

        $paymentMode = $request->payment_mode ?: 'ALL';

        if ($paymentMode === 'CASH') {
            $query->whereRaw('BINARY dt.payment_mode = ?', ['Cash']);
        } elseif ($paymentMode === 'NON-CASH') {
            $query->where(function ($q) {
                $q->whereRaw('BINARY dt.payment_mode != ?', ['Cash'])->orWhereNull('dt.payment_mode');
            });
        }

        $rows = $query
            ->orderBy('dt.created_at')
            ->get([
                'dt.id', 'dt.transaction_no', 'dt.transaction_type', 'dt.received_amount', 'dt.refund_amount',
                'dt.doctor_payment_amount', 'dt.invoice_reference', 'dt.patient_name', 'dt.payment_mode',
                'dt.operator_name', 'dt.remarks', 'dt.status', 'dt.created_at',
                'doc.doctor_name',
                'inv.id as invoice_id', 'inv.invoice_type',
                'inv.discount', 'inv.total_amount as billed_amount',
                'inv.due_amount', 'inv.paid_amount_1', 'inv.paid_amount_2',
            ])
            ->map(function ($row) {
                return $this->decorateDetailRow($row);
            });

        return response()->json([
            'status' => true,
            'rows' => $rows->values(),
            'summary' => $this->sumDetailRows($rows),
        ]);
    }

    public function printDetail(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'payment_mode' => 'nullable|in:CASH,NON-CASH,ALL',
        ]);

        $result = json_decode($this->detail($request)->getContent(), true);

        $date = Carbon::parse($request->date);

        $pdf = Pdf::loadView(
            'apps-daily-transaction-detail-pdf',
            [
                'rows' => $result['rows'],
                'summary' => $result['summary'],
                'date' => $date,
                'paymentModeFilter' => $request->payment_mode ?: 'ALL',
                'printedBy' => optional(auth()->user())->name,
            ]
        );

        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream('Daily-Transaction-Detail-' . $date->format('d-m-Y') . '.pdf');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function decorateSummaryRow($row)
    {
        $row->date_fmt = Carbon::parse($row->transaction_date)->format('d-m-Y (D)');
        $row->collection_total = round((float) $row->collection_cash + (float) $row->collection_noncash, 2);
        $row->refund_total = round((float) $row->refund_cash + (float) $row->refund_noncash, 2);
        $row->payment_total = round((float) $row->payment_cash + (float) $row->payment_noncash, 2);
        $row->net_cash = round((float) $row->collection_cash - (float) $row->refund_cash - (float) $row->payment_cash, 2);
        $row->net_total = round($row->collection_total - $row->refund_total - $row->payment_total, 2);
        return $row;
    }

    private function sumSummaryRows($rows): array
    {
        return [
            'collection_cash' => round($rows->sum('collection_cash'), 2),
            'collection_noncash' => round($rows->sum('collection_noncash'), 2),
            'collection_total' => round($rows->sum('collection_total'), 2),
            'refund_cash' => round($rows->sum('refund_cash'), 2),
            'refund_noncash' => round($rows->sum('refund_noncash'), 2),
            'refund_total' => round($rows->sum('refund_total'), 2),
            'payment_cash' => round($rows->sum('payment_cash'), 2),
            'payment_noncash' => round($rows->sum('payment_noncash'), 2),
            'payment_total' => round($rows->sum('payment_total'), 2),
            'net_cash' => round($rows->sum('net_cash'), 2),
            'net_total' => round($rows->sum('net_total'), 2),
        ];
    }

    private function decorateDetailRow($row)
    {
        $amount = match ($row->transaction_type) {
            'RECEIVED' => (float) $row->received_amount,
            'REFUND' => -1 * (float) $row->refund_amount,
            'PAYMENT' => -1 * (float) $row->doctor_payment_amount,
            default => 0,
        };

        $row->type_label = self::TYPE_LABELS[$row->transaction_type] ?? $row->transaction_type;
        $row->received_total = round($amount, 2);
        $row->is_cash = $row->payment_mode === 'Cash';
        $row->time_fmt = Carbon::parse($row->created_at)->format('h:i A');

        // Invoice context (only present when invoice_reference matched a real invoice)
        $row->doctor_name = $row->doctor_name ?: null;

        $printLinks = InvoicePrintLinkResolver::resolve($row->invoice_type, $row->invoice_id);
        $row->print_url = $printLinks['print_url'];
        $row->print_ajax_url = $printLinks['print_ajax_url'];

        if ($row->billed_amount !== null) {
            $row->rate = round((float) $row->billed_amount + (float) $row->discount, 2);
            $row->billed_amount = round((float) $row->billed_amount, 2);
            $row->discount = round((float) $row->discount, 2);

            $isFullyPaid = (float) $row->due_amount <= 0;
            $wasPhased = !empty($row->paid_amount_1) || !empty($row->paid_amount_2);

            $row->payment_status = !$isFullyPaid
                ? 'Partial'
                : ($wasPhased ? 'Final' : 'Full');
        } else {
            $row->rate = null;
            $row->billed_amount = null;
            $row->discount = null;
            $row->payment_status = '-';
        }

        return $row;
    }

    private function sumDetailRows($rows): array
    {
        $collection = $rows->where('transaction_type', 'RECEIVED');
        $refund = $rows->where('transaction_type', 'REFUND');
        $payment = $rows->where('transaction_type', 'PAYMENT');

        $totalCollection = round($collection->sum('received_amount'), 2);
        $totalRefund = round($refund->sum('refund_amount'), 2);
        $totalPayment = round($payment->sum('doctor_payment_amount'), 2);

        return [
            'total_records' => $rows->count(),
            'total_collection' => $totalCollection,
            'total_refund' => $totalRefund,
            'total_payment' => $totalPayment,
            'net_total' => round($totalCollection - $totalRefund - $totalPayment, 2),
        ];
    }
}
