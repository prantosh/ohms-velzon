<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DoctorWisePaymentReportController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('apps-doctor-wise-payment-report', [
            'doctors' => Doctor::where('status', 'Active')->orderBy('doctor_name')->get(['id', 'doctor_name']),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL (one row per doctor_payables line)
    |--------------------------------------------------------------------------
    */

    public function detail(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'doctor_id' => 'nullable|exists:doctors,id',
        ]);

        $rows = $this->baseQuery($request)
            ->orderBy('inv.invoice_date')
            ->orderBy('dp.created_at')
            ->get([
                'inv.invoice_date',
                'dp.invoice_no',
                'dp.doctor_name',
                'inv.patient_name',
                'dp.item_description',
                'dp.gross_amount',
                'dp.payable_amount',
                'dp.paid_amount',
                'dp.payment_status',
                'dp.last_settlement_no',
                'dp.last_settlement_date',
                'dp.created_by',
            ]);

        $userNames = User::whereIn('id', $rows->pluck('created_by')->filter()->unique())->pluck('name', 'id');

        $rows = $rows->map(function ($row) use ($userNames) {
            $row->invoice_date_fmt = $row->invoice_date ? Carbon::parse($row->invoice_date)->format('d-m-Y') : null;
            $row->gross_amount = round((float) $row->gross_amount, 2);
            $row->payable_amount = round((float) $row->payable_amount, 2);
            $row->settled_amount = round((float) $row->paid_amount, 2);
            $row->pending_amount = round($row->payable_amount - $row->settled_amount, 2);
            $row->last_settlement_date_fmt = $row->last_settlement_date ? Carbon::parse($row->last_settlement_date)->format('d-m-Y') : '-';
            $row->settlement_display = $row->last_settlement_no ?: 'Not Settled';
            $row->user_name = $userNames->get($row->created_by) ?? '-';
            return $row;
        });

        return response()->json([
            'status' => true,
            'rows' => $rows->values(),
            'grand_total' => [
                'count' => $rows->count(),
                'gross' => round($rows->sum('gross_amount'), 2),
                'payable' => round($rows->sum('payable_amount'), 2),
                'settled' => round($rows->sum('settled_amount'), 2),
                'pending' => round($rows->sum('pending_amount'), 2),
            ],
        ]);
    }

    public function printDetail(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'doctor_id' => 'nullable|exists:doctors,id',
        ]);

        $result = json_decode($this->detail($request)->getContent(), true);

        $pdf = Pdf::loadView('apps-doctor-wise-payment-report-detail-pdf', [
            'rows' => $result['rows'],
            'grandTotal' => $result['grand_total'],
            'fromDate' => Carbon::parse($request->from_date),
            'toDate' => Carbon::parse($request->to_date),
            'printedBy' => optional(auth()->user())->name,
        ]);

        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream(
            'Doctor-Wise-Payment-Report-Detail-' .
            Carbon::parse($request->from_date)->format('d-m-Y') . '-to-' .
            Carbon::parse($request->to_date)->format('d-m-Y') . '.pdf'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY (by doctor)
    |--------------------------------------------------------------------------
    */

    public function summary(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'doctor_id' => 'nullable|exists:doctors,id',
        ]);

        $rows = $this->baseQuery($request)
            ->groupBy('dp.doctor_name')
            ->orderBy('dp.doctor_name')
            ->get([
                'dp.doctor_name',
                DB::raw('COUNT(*) as item_count'),
                DB::raw('SUM(dp.gross_amount) as gross'),
                DB::raw('SUM(dp.payable_amount) as payable'),
                DB::raw('SUM(dp.paid_amount) as settled'),
            ])
            ->map(function ($row) {
                $row->gross = round((float) $row->gross, 2);
                $row->payable = round((float) $row->payable, 2);
                $row->settled = round((float) $row->settled, 2);
                $row->pending = round($row->payable - $row->settled, 2);
                return $row;
            });

        return response()->json([
            'status' => true,
            'rows' => $rows->values(),
            'grand_total' => [
                'count' => (int) $rows->sum('item_count'),
                'gross' => round($rows->sum('gross'), 2),
                'payable' => round($rows->sum('payable'), 2),
                'settled' => round($rows->sum('settled'), 2),
                'pending' => round($rows->sum('pending'), 2),
            ],
        ]);
    }

    public function printSummary(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'doctor_id' => 'nullable|exists:doctors,id',
        ]);

        $result = json_decode($this->summary($request)->getContent(), true);

        $pdf = Pdf::loadView('apps-doctor-wise-payment-report-summary-pdf', [
            'rows' => $result['rows'],
            'grandTotal' => $result['grand_total'],
            'fromDate' => Carbon::parse($request->from_date),
            'toDate' => Carbon::parse($request->to_date),
            'printedBy' => optional(auth()->user())->name,
        ]);

        return $pdf->stream(
            'Doctor-Wise-Payment-Report-Summary-' .
            Carbon::parse($request->from_date)->format('d-m-Y') . '-to-' .
            Carbon::parse($request->to_date)->format('d-m-Y') . '.pdf'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY HELPER
    |--------------------------------------------------------------------------
    | Every non-cancelled, non-zero doctor payable within the given
    | invoice-date range, regardless of how the patient paid.
    */

    private function baseQuery(Request $request)
    {
        $query = DB::table('doctor_payables as dp')
            ->join('invoices as inv', 'inv.id', '=', 'dp.invoice_id')
            ->whereBetween('inv.invoice_date', [$request->from_date, $request->to_date])
            ->where('dp.payment_status', '!=', 'CANCELLED')
            ->where('dp.payable_amount', '>', 0);

        if ($request->filled('doctor_id')) {
            $query->where('dp.doctor_id', $request->doctor_id);
        }

        return $query;
    }
}
