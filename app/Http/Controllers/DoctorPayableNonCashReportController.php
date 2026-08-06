<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DoctorPayableNonCashReportController extends Controller
{
    private const INVOICE_TYPES = ['DOCTOR_VISIT', 'DIAGNOSTIC'];

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('apps-doctor-payable-non-cash-report', [
            'doctors' => Doctor::where('status', 'Active')->orderBy('doctor_name')->get(['id', 'doctor_name']),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL (one row per qualifying invoice)
    |--------------------------------------------------------------------------
    | Invoice date range -> invoices paid by the patient in a non-cash mode
    | (or unrecorded) -> that also owe at least one doctor a non-zero
    | payable. For each such invoice: total payable, total already settled
    | (any mode -- doctor_payables.paid_amount, verified in sync with the
    | sum of its settlement items), and the pending balance.
    */

    public function detail(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'doctor_id' => 'nullable|exists:doctors,id',
        ]);

        $invoices = $this->baseQuery($request)
            ->groupBy(
                'inv.id',
                'inv.invoice_no',
                'inv.invoice_date',
                'inv.invoice_type',
                'inv.payment_mode',
                'inv.patient_name',
                'inv.total_amount',
                'inv.created_by'
            )
            ->orderBy('inv.invoice_date')
            ->orderBy('inv.invoice_no')
            ->get([
                'inv.id',
                'inv.invoice_no',
                'inv.invoice_date',
                'inv.invoice_type',
                'inv.payment_mode as patient_payment_mode',
                'inv.patient_name',
                'inv.total_amount',
                'inv.created_by',
                DB::raw('GROUP_CONCAT(DISTINCT dp.doctor_name ORDER BY dp.doctor_name SEPARATOR ", ") as doctor_names'),
                DB::raw('SUM(dp.payable_amount) as total_payable'),
                DB::raw('SUM(dp.paid_amount) as total_settled'),
            ]);

        if ($invoices->isEmpty()) {
            return response()->json([
                'status' => true,
                'rows' => [],
                'grand_total' => ['invoice_count' => 0, 'payable' => 0, 'settled' => 0, 'pending' => 0],
            ]);
        }

        $invoiceIds = $invoices->pluck('id');

        $settlementDetails = DB::table('doctor_settlement_items as dsi')
            ->join('doctor_settlements as ds', 'ds.id', '=', 'dsi.settlement_id')
            ->join('doctor_payables as dp', 'dp.id', '=', 'dsi.payable_id')
            ->whereIn('dp.invoice_id', $invoiceIds)
            ->where('dp.payable_amount', '>', 0)
            ->select('dp.invoice_id', 'ds.settlement_no', 'ds.settlement_date', 'ds.payment_mode')
            ->distinct()
            ->get()
            ->groupBy('invoice_id');

        $userNames = User::whereIn('id', $invoices->pluck('created_by')->filter()->unique())->pluck('name', 'id');

        $rows = $invoices->map(function ($inv) use ($settlementDetails, $userNames) {

            $settlements = $settlementDetails->get($inv->id, collect());

            $inv->invoice_date_fmt = Carbon::parse($inv->invoice_date)->format('d-m-Y');
            $inv->patient_payment_mode = $inv->patient_payment_mode ?: 'Not Recorded';
            $inv->total_payable = round((float) $inv->total_payable, 2);
            $inv->total_settled = round((float) $inv->total_settled, 2);
            $inv->total_pending = round($inv->total_payable - $inv->total_settled, 2);
            $inv->settlement_nos = $settlements->pluck('settlement_no')->unique()->implode(', ') ?: '-';
            $inv->settlement_dates = $settlements->pluck('settlement_date')
                ->unique()
                ->map(fn ($d) => Carbon::parse($d)->format('d-m-Y'))
                ->implode(', ') ?: '-';
            $inv->settlement_modes = $settlements->pluck('payment_mode')->unique()->implode(', ') ?: '-';
            $inv->user_involved = $userNames->get($inv->created_by) ?? '-';
            $inv->print_url = $inv->invoice_type === 'DOCTOR_VISIT'
                ? '/doctor-visit-invoice/print/' . $inv->id
                : '/diagnostic-invoice/print/' . $inv->id;

            return $inv;
        });

        return response()->json([
            'status' => true,
            'rows' => $rows->values(),
            'grand_total' => [
                'invoice_count' => $rows->count(),
                'payable' => round($rows->sum('total_payable'), 2),
                'settled' => round($rows->sum('total_settled'), 2),
                'pending' => round($rows->sum('total_pending'), 2),
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

        $pdf = Pdf::loadView('apps-doctor-payable-non-cash-report-detail-pdf', [
            'rows' => $result['rows'],
            'grandTotal' => $result['grand_total'],
            'fromDate' => Carbon::parse($request->from_date),
            'toDate' => Carbon::parse($request->to_date),
            'printedBy' => optional(auth()->user())->name,
        ]);

        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream(
            'Doctor-Payable-Non-Cash-Report-Detail-' .
            Carbon::parse($request->from_date)->format('d-m-Y') . '-to-' .
            Carbon::parse($request->to_date)->format('d-m-Y') . '.pdf'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY (by doctor, across all qualifying non-cash invoices)
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
                DB::raw('COUNT(DISTINCT dp.invoice_id) as invoice_count'),
                DB::raw('SUM(dp.payable_amount) as payable'),
                DB::raw('SUM(dp.paid_amount) as settled'),
            ])
            ->map(function ($row) {
                $row->payable = round((float) $row->payable, 2);
                $row->settled = round((float) $row->settled, 2);
                $row->pending = round($row->payable - $row->settled, 2);
                return $row;
            });

        return response()->json([
            'status' => true,
            'rows' => $rows->values(),
            'grand_total' => [
                'invoice_count' => (int) $rows->sum('invoice_count'),
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

        $pdf = Pdf::loadView('apps-doctor-payable-non-cash-report-summary-pdf', [
            'rows' => $result['rows'],
            'grandTotal' => $result['grand_total'],
            'fromDate' => Carbon::parse($request->from_date),
            'toDate' => Carbon::parse($request->to_date),
            'printedBy' => optional(auth()->user())->name,
        ]);

        return $pdf->stream(
            'Doctor-Payable-Non-Cash-Report-Summary-' .
            Carbon::parse($request->from_date)->format('d-m-Y') . '-to-' .
            Carbon::parse($request->to_date)->format('d-m-Y') . '.pdf'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY HELPER
    |--------------------------------------------------------------------------
    | Non-cash (or unrecorded) patient invoices, in the given invoice-date
    | range, that owe a non-zero payable to at least one doctor.
    */

    private function baseQuery(Request $request)
    {
        $query = DB::table('invoices as inv')
            ->join('doctor_payables as dp', 'dp.invoice_id', '=', 'inv.id')
            ->whereIn('inv.invoice_type', self::INVOICE_TYPES)
            ->whereBetween('inv.invoice_date', [$request->from_date, $request->to_date])
            ->whereRaw('(inv.payment_mode IS NULL OR UPPER(inv.payment_mode) != ?)', ['CASH'])
            ->where(function ($q) {
                $q->whereNull('inv.cancelled')->orWhere('inv.cancelled', '!=', 'Y');
            })
            ->where('dp.payable_amount', '>', 0);

        if ($request->filled('doctor_id')) {
            $query->where('dp.doctor_id', $request->doctor_id);
        }

        return $query;
    }
}
