<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Date-wise version of the home page "Today's Services" tiles.
 *
 * Patients   -> doctor_appointments, split into Appointments (booked, net of
 *               cancellations) and Visited (of those, appointments that
 *               already produced a non-cancelled invoice -- same definition
 *               as the home page's "Patients Confirmed").
 * Services   -> invoice_details lines per item_code on non-cancelled
 *               invoices, by invoice date (the home page counts every line
 *               incl. cancelled ones; a historical report shouldn't).
 */
class ServiceSummaryReportController extends Controller
{
    private const PATIENTS = 'PATIENTS';

    private const SERVICES = [
        'USG001' => 'USG',
        'XRY001' => 'X-Ray',
        'PAT001' => 'Pathology',
        'END001' => 'Endoscopy',
        'DEN001' => 'Dental',
        'EYE001' => 'Eye',
        'CRD001' => 'Cardiology',
    ];

    public function index()
    {
        $doctors = Doctor::orderBy('doctor_name')->get(['id', 'doctor_name']);
        $employees = User::orderBy('name')->get(['id', 'name']);
        $items = [self::PATIENTS => 'Patients (Appointments / Visited)'] + self::SERVICES;

        return view('apps-service-summary-report', compact('doctors', 'employees', 'items'));
    }

    public function report(Request $request)
    {
        return response()->json(['status' => true] + $this->buildReport($request));
    }

    public function print(Request $request)
    {
        $data = $this->buildReport($request);

        $pdf = Pdf::loadView('apps-service-summary-report-pdf', $data + [
            'fromDate' => Carbon::parse($request->from_date),
            'toDate' => Carbon::parse($request->to_date),
            'printedBy' => optional(auth()->user())->name,
            'filterText' => $this->filterText($request),
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream('Service-Summary-' .
            Carbon::parse($request->from_date)->format('d-m-Y') . '-to-' .
            Carbon::parse($request->to_date)->format('d-m-Y') . '.pdf');
    }

    private function buildReport(Request $request): array
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'items' => 'nullable|array',
            'items.*' => 'string',
            'doctor_id' => 'nullable|integer',
            'employee_id' => 'nullable|integer',
        ]);

        $from = Carbon::parse($request->from_date)->toDateString();
        $to = Carbon::parse($request->to_date)->toDateString();
        $selected = array_filter((array) $request->items);
        $doctorId = $request->filled('doctor_id') ? (int) $request->doctor_id : null;
        $employeeId = $request->filled('employee_id') ? (int) $request->employee_id : null;

        $showPatients = !$selected || in_array(self::PATIENTS, $selected, true);
        $serviceCodes = $selected
            ? array_values(array_intersect(array_keys(self::SERVICES), $selected))
            : array_keys(self::SERVICES);

        $rows = [];
        $blank = fn () => array_fill_keys(
            array_merge(['appointments', 'visited'], array_keys(self::SERVICES)),
            0
        );

        if ($showPatients) {
            $appts = DB::table('doctor_appointments as da')
                ->whereBetween(DB::raw('DATE(da.appointment_date)'), [$from, $to])
                ->where('da.appointment_status', '!=', 'Cancelled')
                ->when($doctorId, fn ($q) => $q->where('da.doctor_id', $doctorId))
                ->when($employeeId, fn ($q) => $q->where('da.created_by', $employeeId))
                ->selectRaw('DATE(da.appointment_date) as d, COUNT(*) as booked, SUM(CASE WHEN EXISTS (
                        SELECT 1 FROM invoices i WHERE i.appointment_id = da.id
                        AND (i.cancelled IS NULL OR i.cancelled != \'Y\')
                    ) THEN 1 ELSE 0 END) as visited')
                ->groupBy('d')
                ->get();

            foreach ($appts as $r) {
                $rows[$r->d] ??= $blank();
                $rows[$r->d]['appointments'] = (int) $r->booked;
                $rows[$r->d]['visited'] = (int) $r->visited;
            }
        }

        if ($serviceCodes) {
            $lines = DB::table('invoice_details as d')
                ->join('invoices as i', 'i.invoice_no', '=', 'd.invoice_no')
                ->whereIn('d.item_code', $serviceCodes)
                ->whereBetween(DB::raw('DATE(i.invoice_date)'), [$from, $to])
                ->where(fn ($q) => $q->whereNull('i.cancelled')->orWhere('i.cancelled', '!=', 'Y'))
                ->when($doctorId, fn ($q) => $q->where('d.doctor_id', $doctorId))
                ->when($employeeId, fn ($q) => $q->where('i.created_by', $employeeId))
                ->selectRaw('DATE(i.invoice_date) as d, d.item_code, COUNT(*) as c')
                ->groupBy('d', 'd.item_code')
                ->get();

            foreach ($lines as $r) {
                $rows[$r->d] ??= $blank();
                $rows[$r->d][$r->item_code] = (int) $r->c;
            }
        }

        ksort($rows);

        $out = [];
        $totals = $blank();
        foreach ($rows as $date => $cols) {
            $out[] = ['date' => $date] + $cols;
            foreach ($cols as $k => $v) {
                $totals[$k] += $v;
            }
        }

        $columns = [];
        if ($showPatients) {
            $columns[] = ['key' => 'appointments', 'label' => 'Appointments'];
            $columns[] = ['key' => 'visited', 'label' => 'Visited'];
        }
        foreach ($serviceCodes as $code) {
            $columns[] = ['key' => $code, 'label' => self::SERVICES[$code]];
        }

        return ['columns' => $columns, 'rows' => $out, 'totals' => $totals];
    }

    private function filterText(Request $request): string
    {
        $parts = [];
        if ($request->filled('doctor_id')) {
            $parts[] = 'Doctor: ' . Doctor::where('id', $request->doctor_id)->value('doctor_name');
        }
        if ($request->filled('employee_id')) {
            $parts[] = 'Employee: ' . User::where('id', $request->employee_id)->value('name');
        }
        $items = array_filter((array) $request->items);
        if ($items) {
            $parts[] = 'Items: ' . implode(', ', array_map(
                fn ($c) => $c === self::PATIENTS ? 'Patients' : (self::SERVICES[$c] ?? $c),
                $items
            ));
        }

        return implode(' | ', $parts);
    }
}
