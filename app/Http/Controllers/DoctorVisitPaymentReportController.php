<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class DoctorVisitPaymentReportController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $doctors = Doctor::orderBy('doctor_name')->get(['id', 'doctor_name']);

        return view('apps-doctor-visit-payment-report', compact('doctors'));
    }

    /*
    |--------------------------------------------------------------------------
    | LIST
    |--------------------------------------------------------------------------
    */

    public function list(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'date' => 'required|date',
        ]);

        $result = $this->buildReport($request->doctor_id, $request->date);

        return response()->json([
            'status' => true,
            'rows' => $result['rows'],
            'summary' => $result['summary'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PRINT (PDF)
    |--------------------------------------------------------------------------
    */

    public function print(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'date' => 'required|date',
        ]);

        $doctor = Doctor::findOrFail($request->doctor_id);

        $result = $this->buildReport($request->doctor_id, $request->date);

        $date = Carbon::parse($request->date);

        $pdf = Pdf::loadView(
            'apps-doctor-visit-payment-report-pdf',
            [
                'doctor' => $doctor,
                'rows' => $result['rows'],
                'summary' => $result['summary'],
                'date' => $date,
            ]
        );

        $fileName = 'Doctor-Visit-Payment-Report-' .
            str_replace(' ', '-', $doctor->doctor_name) .
            '-' . $date->format('d-m-Y') . '.pdf';

        return $pdf->stream($fileName);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function buildReport($doctorId, $date): array
    {
        $invoices = DB::table('invoices')
            ->where('doctor_id', $doctorId)
            ->whereDate('invoice_date', $date)
            ->where('invoice_no', 'like', 'DOC%')
            ->where(function ($q) {
                $q->whereNull('cancelled')->orWhere('cancelled', '!=', 'Y');
            })
            ->orderBy('invoice_no')
            ->get(['id', 'invoice_no', 'patient_name', 'patient_mobile_no', 'total_amount', 'created_at']);

        $invoiceIds = $invoices->pluck('id');

        $settlementItems = DB::table('doctor_settlement_items')
            ->join('doctor_settlements', 'doctor_settlements.id', '=', 'doctor_settlement_items.settlement_id')
            ->whereIn('doctor_settlement_items.invoice_id', $invoiceIds)
            ->where('doctor_settlements.status', '!=', 'CANCELLED')
            ->select(
                'doctor_settlement_items.invoice_id',
                'doctor_settlement_items.payable_amount',
                'doctor_settlement_items.settlement_amount',
                'doctor_settlement_items.balance_after_payment',
                'doctor_settlement_items.created_at as item_created_at',
                'doctor_settlements.settlement_no',
                'doctor_settlements.settlement_date',
                'doctor_settlements.payment_mode',
                'doctor_settlements.voucher_no',
                'doctor_settlements.status as settlement_status'
            )
            ->orderBy('doctor_settlement_items.created_at')
            ->get()
            ->groupBy('invoice_id');

        $totalPayable = 0;
        $totalSettledAmount = 0;
        $settledCount = 0;

        $rows = $invoices->map(function ($invoice) use ($settlementItems, &$totalPayable, &$totalSettledAmount, &$settledCount) {

            $items = $settlementItems->get($invoice->id, collect());

            $isSettled = $items->count() > 0;

            $payableAmount = $isSettled ? (float) $items->max('payable_amount') : null;

            $settledAmount = $items->sum('settlement_amount');

            $latest = $items->last();

            $balance = $latest ? (float) $latest->balance_after_payment : null;

            $settlementNumbers = $items->pluck('settlement_no')->unique()->values()->implode(', ');

            if ($isSettled) {
                $totalPayable += $payableAmount;
                $totalSettledAmount += $settledAmount;
                $settledCount++;
            }

            return [
                'invoice_id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'patient_name' => $invoice->patient_name,
                'patient_mobile_no' => $invoice->patient_mobile_no,
                'total_amount' => $invoice->total_amount,
                'invoice_time' => Carbon::parse($invoice->created_at)->format('h:i A'),
                'is_settled' => $isSettled,
                'payable_amount' => $payableAmount,
                'settled_amount' => $settledAmount,
                'balance' => $balance,
                'settlement_numbers' => $settlementNumbers,
                'settlement_date' => optional($latest)->settlement_date,
                'payment_mode' => optional($latest)->payment_mode,
            ];
        });

        return [
            'rows' => $rows->values(),
            'summary' => [
                'total_invoices' => $invoices->count(),
                'settled_count' => $settledCount,
                'not_settled_count' => $invoices->count() - $settledCount,
                'total_payable' => round($totalPayable, 2),
                'total_settled_amount' => round($totalSettledAmount, 2),
                'total_balance' => round($totalPayable - $totalSettledAmount, 2),
            ],
        ];
    }
}
