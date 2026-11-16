<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Doctor visits whose fee was waived (DoctorVisitInvoiceController::waiveFee)
 * within a date range -- by the day the waiver was recorded.
 */
class DoctorFeeWaiverReportController extends Controller
{
    public function index()
    {
        $doctors = DB::table('doctors')->orderBy('doctor_name')->get(['id', 'doctor_name']);

        return view('apps-doctor-fee-waiver-report', compact('doctors'));
    }

    public function list(Request $request)
    {
        $request->validate($this->rules());

        $rows = $this->rows($request);

        return response()->json([
            'status' => true,
            'rows' => $rows->values(),
            'grand_total' => $this->grandTotal($rows),
        ]);
    }

    public function print(Request $request)
    {
        $request->validate($this->rules());

        $rows = $this->rows($request);

        $pdf = Pdf::loadView('apps-doctor-fee-waiver-report-pdf', [
            'rows' => $rows,
            'grandTotal' => $this->grandTotal($rows),
            'fromDate' => Carbon::parse($request->from_date),
            'toDate' => Carbon::parse($request->to_date),
            'doctorName' => $request->filled('doctor_id')
                ? (DB::table('doctors')->where('id', $request->doctor_id)->value('doctor_name') ?? 'Unknown')
                : 'All Doctors',
            'printedBy' => optional(auth()->user())->name,
        ]);

        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream(
            'Doctor-Fee-Waiver-Report-' .
            Carbon::parse($request->from_date)->format('d-m-Y') . '-to-' .
            Carbon::parse($request->to_date)->format('d-m-Y') . '.pdf'
        );
    }

    private function rules(): array
    {
        return [
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'doctor_id' => 'nullable|integer',
        ];
    }

    private function rows(Request $request)
    {
        $query = DB::table('invoices as i')
            ->leftJoin('doctors as d', 'd.id', '=', 'i.doctor_id')
            ->leftJoin('users as u', 'u.id', '=', 'i.waived_by')
            ->where('i.fee_waived', 1)
            ->where(function ($q) {
                $q->whereNull('i.cancelled')->orWhere('i.cancelled', '!=', 'Y');
            })
            ->whereDate('i.waived_at', '>=', Carbon::parse($request->from_date)->toDateString())
            ->whereDate('i.waived_at', '<=', Carbon::parse($request->to_date)->toDateString());

        if ($request->filled('doctor_id')) {
            $query->where('i.doctor_id', (int) $request->doctor_id);
        }

        return $query
            ->orderBy('i.waived_at')
            ->orderBy('i.id')
            ->get([
                'i.invoice_no',
                'i.invoice_date',
                'i.patient_name',
                'd.doctor_name',
                'i.total_amount',
                'i.waived_amount',
                'i.waived_at',
                'i.waiver_reason',
                'u.name as waived_by_name',
            ])
            ->map(function ($row) {
                $row->visit_date_fmt = $row->invoice_date ? Carbon::parse($row->invoice_date)->format('d-m-Y') : '';
                $row->waived_at_fmt = $row->waived_at ? Carbon::parse($row->waived_at)->format('d-m-Y h:i A') : '';
                $row->total_amount = round((float) $row->total_amount, 2);
                $row->waived_amount = round((float) $row->waived_amount, 2);
                return $row;
            });
    }

    private function grandTotal($rows): array
    {
        return [
            'count' => $rows->count(),
            'amount' => round($rows->sum('waived_amount'), 2),
        ];
    }
}
