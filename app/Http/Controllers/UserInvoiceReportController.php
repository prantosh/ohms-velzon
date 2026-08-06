<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class UserInvoiceReportController extends Controller
{
    private const STAFF_ROLES = ['Admin', 'Supervisor', 'Employee'];

    private const PAYMENT_MODES = ['Cash', 'Card', 'UPI', 'Bank', 'Cheque'];

    private const INVOICE_TYPE_LABELS = [
        'DOCTOR_VISIT' => 'Doctor Visit',
        'DIAGNOSTIC' => 'Diagnostic',
        'OXYGEN_RENT' => 'Oxygen Concentrator/Cylinder Rental',
        'CONCENTRATOR_RENT' => 'Concentrator Rental',
        'AMBULANCE_RENT' => 'Ambulance Rental',
        'MEMBERSHIP_FEE' => 'Membership Fee',
        'OTHER_INCOME' => 'Income from Other Source',
    ];

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $users = User::whereIn('role', self::STAFF_ROLES)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return view('apps-user-invoice-report', compact('users'));
    }

    /*
    |--------------------------------------------------------------------------
    | LIST
    |--------------------------------------------------------------------------
    */

    public function list(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
        ]);

        $result = $this->buildReport($request->user_id, $request->date);

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
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
        ]);

        $user = User::findOrFail($request->user_id);

        $result = $this->buildReport($request->user_id, $request->date);

        $date = Carbon::parse($request->date);

        $pdf = Pdf::loadView(
            'apps-user-invoice-report-pdf',
            [
                'user' => $user,
                'rows' => $result['rows'],
                'summary' => $result['summary'],
                'date' => $date,
            ]
        );

        $fileName = 'User-Invoice-Report-' .
            str_replace(' ', '-', $user->name) .
            '-' . $date->format('d-m-Y') . '.pdf';

        return $pdf->stream($fileName);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function buildReport($userId, $date): array
    {
        $invoices = DB::table('invoices')
            ->where('created_by', $userId)
            ->whereDate('created_at', $date)
            ->where(function ($q) {
                $q->whereNull('cancelled')->orWhere('cancelled', '!=', 'Y');
            })
            ->orderBy('created_at')
            ->get(['id', 'invoice_no', 'invoice_type', 'patient_name', 'total_amount', 'payment_mode', 'created_at']);

        $docInvoiceIds = $invoices->filter(fn($inv) => str_starts_with($inv->invoice_no, 'DOC'))
            ->pluck('id');

        $settlementItems = DB::table('doctor_settlement_items')
            ->join('doctor_settlements', 'doctor_settlements.id', '=', 'doctor_settlement_items.settlement_id')
            ->whereIn('doctor_settlement_items.invoice_id', $docInvoiceIds)
            ->where('doctor_settlements.status', '!=', 'CANCELLED')
            ->select(
                'doctor_settlement_items.invoice_id',
                'doctor_settlement_items.payable_amount',
                'doctor_settlement_items.settlement_amount',
                'doctor_settlement_items.balance_after_payment',
                'doctor_settlements.settlement_no'
            )
            ->orderBy('doctor_settlement_items.created_at')
            ->get()
            ->groupBy('invoice_id');

        $totalAmount = 0;
        $paymentModeBreakdown = array_fill_keys(self::PAYMENT_MODES, 0);

        $docCount = 0;
        $docSettledCount = 0;
        $docTotalPayable = 0;
        $docTotalSettled = 0;

        $rows = $invoices->map(function ($invoice) use (
            $settlementItems,
            &$totalAmount,
            &$paymentModeBreakdown,
            &$docCount,
            &$docSettledCount,
            &$docTotalPayable,
            &$docTotalSettled
        ) {
            $totalAmount += (float) $invoice->total_amount;

            if (isset($paymentModeBreakdown[$invoice->payment_mode])) {
                $paymentModeBreakdown[$invoice->payment_mode] += (float) $invoice->total_amount;
            }

            $isDoc = str_starts_with($invoice->invoice_no, 'DOC');

            $isSettled = null;
            $payableAmount = null;
            $settledAmount = null;
            $balance = null;
            $settlementNumbers = null;

            if ($isDoc) {

                $docCount++;

                $items = $settlementItems->get($invoice->id, collect());

                $isSettled = $items->count() > 0;

                if ($isSettled) {

                    $docSettledCount++;

                    $payableAmount = (float) $items->max('payable_amount');
                    $settledAmount = (float) $items->sum('settlement_amount');
                    $balance = (float) $items->last()->balance_after_payment;
                    $settlementNumbers = $items->pluck('settlement_no')->unique()->values()->implode(', ');

                    $docTotalPayable += $payableAmount;
                    $docTotalSettled += $settledAmount;
                }
            }

            return [
                'invoice_id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'invoice_type_label' => self::INVOICE_TYPE_LABELS[$invoice->invoice_type] ?? $invoice->invoice_type,
                'patient_name' => $invoice->patient_name,
                'total_amount' => $invoice->total_amount,
                'payment_mode' => $invoice->payment_mode,
                'time' => Carbon::parse($invoice->created_at)->format('h:i A'),
                'is_doc' => $isDoc,
                'is_settled' => $isSettled,
                'payable_amount' => $payableAmount,
                'settled_amount' => $settledAmount,
                'balance' => $balance,
                'settlement_numbers' => $settlementNumbers,
            ];
        });

        return [
            'rows' => $rows->values(),
            'summary' => [
                'total_invoices' => $invoices->count(),
                'total_amount' => round($totalAmount, 2),
                'payment_mode_breakdown' => array_map(fn($v) => round($v, 2), $paymentModeBreakdown),
                'doc_invoice_count' => $docCount,
                'doc_settled_count' => $docSettledCount,
                'doc_not_settled_count' => $docCount - $docSettledCount,
                'doc_total_payable' => round($docTotalPayable, 2),
                'doc_total_settled' => round($docTotalSettled, 2),
                'doc_total_balance' => round($docTotalPayable - $docTotalSettled, 2),
            ],
        ];
    }
}
