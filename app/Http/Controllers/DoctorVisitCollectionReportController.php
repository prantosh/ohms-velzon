<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Doctor Visit collection, split between what the clinic keeps and what's
 * owed to the doctor. The split is already recorded per invoice in
 * doctor_payables (payable_amount = doctor's fixed share, gross_amount =
 * the posted visit charge) -- clinic share here is actual collected
 * (invoices.paid_amount) minus the doctor's share, i.e. true "collection"
 * accounting rather than the posted/undiscounted split.
 */
class DoctorVisitCollectionReportController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $specialisations = DB::table('doctors')
            ->whereNotNull('specialisation')
            ->where('specialisation', '!=', '')
            ->distinct()
            ->orderBy('specialisation')
            ->pluck('specialisation');

        return view('apps-doctor-visit-collection-report', compact('specialisations'));
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY (specialisation-wise rows, date-wise columns -- capped at a
    | 7-day range at a time, see dateRangeRules())
    |--------------------------------------------------------------------------
    */

    public function summary(Request $request)
    {
        $request->validate($this->dateRangeRules($request));

        $dateColumns = $this->dateColumnsInRange($request->from_date, $request->to_date);
        $specialisations = $this->resolveSpecialisationColumns();

        $raw = $this->baseQuery()
            ->whereBetween('i.invoice_date', [$request->from_date, $request->to_date])
            ->selectRaw("
                i.invoice_date as log_date,
                COALESCE(d.specialisation, 'Unspecified') as specialisation,
                COUNT(*) as visits,
                SUM(i.paid_amount) as collected,
                SUM(dp.payable_amount) as doctor_share
            ")
            ->groupBy('log_date', 'specialisation')
            ->get();

        $bySpecialisation = $raw->groupBy('specialisation');

        $rows = collect($specialisations)
            ->map(fn($spec) => $this->buildSpecialisationRow($spec, $bySpecialisation->get($spec, collect()), $dateColumns))
            ->values();

        return response()->json([
            'status' => true,
            'date_columns' => collect($dateColumns)->map(fn($d) => ['key' => $d, 'label' => Carbon::parse($d)->format('d-m-Y (D)')])->values(),
            'rows' => $rows,
            'grand_total' => $this->sumSummaryRows($rows, $dateColumns),
        ]);
    }

    public function printSummary(Request $request)
    {
        // A full 7-day range (~30 specialisation rows x 7 date columns,
        // each a nested-HTML cell) got close to the 128M default under
        // dompdf at the old 15-day cap -- kept as a safety margin, same
        // gotcha as AllInvoicesReportController/StockAsOnDateController.
        ini_set('memory_limit', '512M');

        $request->validate($this->dateRangeRules($request));

        $result = json_decode($this->summary($request)->getContent(), true);

        $pdf = Pdf::loadView(
            'apps-doctor-visit-collection-summary-pdf',
            [
                'rows' => $result['rows'],
                'grandTotal' => $result['grand_total'],
                'dateColumns' => $result['date_columns'],
                'fromDate' => Carbon::parse($request->from_date),
                'toDate' => Carbon::parse($request->to_date),
                'printedBy' => optional(auth()->user())->name,
            ]
        );

        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream(
            'Doctor-Visit-Collection-Summary-' .
            Carbon::parse($request->from_date)->format('d-m-Y') . '-to-' .
            Carbon::parse($request->to_date)->format('d-m-Y') . '.pdf'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL (single day, single specialisation, every visit)
    |--------------------------------------------------------------------------
    */

    public function detail(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'specialisation' => 'required|string',
        ]);

        $rows = $this->fetchDetailRows($request->date, $request->specialisation);

        return response()->json([
            'status' => true,
            'rows' => $rows,
            'summary' => $this->sumDetailRows($rows),
        ]);
    }

    public function printDetail(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'specialisation' => 'required|string',
        ]);

        $rows = $this->fetchDetailRows($request->date, $request->specialisation);

        $date = Carbon::parse($request->date);

        $pdf = Pdf::loadView(
            'apps-doctor-visit-collection-detail-pdf',
            [
                'rows' => $rows,
                'summary' => $this->sumDetailRows($rows),
                'date' => $date,
                'specialisation' => $request->specialisation,
                'printedBy' => optional(auth()->user())->name,
            ]
        );

        return $pdf->stream(
            'Doctor-Visit-Collection-Detail-' .
            str_replace(' ', '-', $request->specialisation) . '-' .
            $date->format('d-m-Y') . '.pdf'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * invoices joined to doctor_payables (the per-visit doctor/clinic
     * split) and doctors (for specialisation) -- cancelled invoices are
     * excluded, same as every other collection report in this app.
     */
    private function baseQuery()
    {
        return DB::table('invoices as i')
            ->join('doctor_payables as dp', 'dp.invoice_id', '=', 'i.id')
            ->leftJoin('doctors as d', 'd.id', '=', 'i.doctor_id')
            ->where('i.invoice_type', 'DOCTOR_VISIT')
            ->where('i.cancelled', '!=', 'Y')
            ->where('dp.invoice_type', 'DOCTOR_VISIT');
    }

    private function detailQuery(string $date, string $specialisation)
    {
        return $this->baseQuery()
            ->where('i.invoice_date', $date)
            ->where(function ($q) use ($specialisation) {
                $specialisation === 'Unspecified'
                    ? $q->whereNull('d.specialisation')->orWhere('d.specialisation', '')
                    : $q->where('d.specialisation', $specialisation);
            })
            ->orderBy('d.doctor_name')
            ->orderBy('i.created_at')
            ->selectRaw("
                i.invoice_no,
                i.created_at,
                i.patient_name,
                d.doctor_name,
                COALESCE(d.specialisation, 'Unspecified') as specialisation,
                i.paid_amount as collected,
                dp.payable_amount as doctor_share,
                (i.paid_amount - dp.payable_amount) as clinic_share
            ");
    }

    private function fetchDetailRows(string $date, string $specialisation)
    {
        return $this->detailQuery($date, $specialisation)
            ->get()
            ->map(function ($row) {
                $row->time_fmt = Carbon::parse($row->created_at)->format('h:i A');
                return $row;
            });
    }

    /**
     * Every specialisation that has ever had a Doctor Visit invoice --
     * mirrors WhatsappMessageTypeRegistry::resolveTypeColumns()'s pattern
     * of discovering rows from the data itself rather than a hardcoded
     * list, so a newly added specialisation shows up with no code change.
     */
    private function resolveSpecialisationColumns(): array
    {
        return $this->baseQuery()
            ->selectRaw("DISTINCT COALESCE(d.specialisation, 'Unspecified') as specialisation")
            ->orderBy('specialisation')
            ->pluck('specialisation')
            ->all();
    }

    /**
     * The summary date range is capped at 7 days at a time -- a wider
     * range would make the (now date-columned) summary table unreasonably
     * wide, and this report is meant for a quick recent window, not a
     * full-history pull.
     */
    private function dateRangeRules(Request $request): array
    {
        return [
            'from_date' => 'required|date',
            'to_date' => [
                'required',
                'date',
                'after_or_equal:from_date',
                function ($attribute, $value, $fail) use ($request) {
                    if (!$request->from_date) {
                        return;
                    }
                    $days = Carbon::parse($request->from_date)->diffInDays(Carbon::parse($value)) + 1;
                    if ($days > 7) {
                        $fail('The date range cannot exceed 7 days -- please narrow your selection.');
                    }
                },
            ],
        ];
    }

    /** @return array<string> every date (Y-m-d) from $from to $to, inclusive */
    private function dateColumnsInRange(string $from, string $to): array
    {
        $dates = [];
        $cursor = Carbon::parse($from);
        $end = Carbon::parse($to);

        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * @param string $specialisation
     * @param \Illuminate\Support\Collection $specRows one row per date this specialisation had visits on
     * @param array<string> $dateColumns full set of dates to fill (0 where no visits that day)
     */
    private function buildSpecialisationRow(string $specialisation, $specRows, array $dateColumns): array
    {
        $byDate = [];

        foreach ($dateColumns as $date) {
            $match = $specRows->firstWhere('log_date', $date);
            $byDate[$date] = [
                'visits' => $match ? (int) $match->visits : 0,
                'collected' => $match ? (float) $match->collected : 0,
            ];
        }

        $totalCollected = (float) $specRows->sum('collected');
        $totalDoctorShare = (float) $specRows->sum('doctor_share');

        return [
            'specialisation' => $specialisation,
            'visits' => (int) $specRows->sum('visits'),
            'collected' => $totalCollected,
            'doctor_share' => $totalDoctorShare,
            'clinic_share' => $totalCollected - $totalDoctorShare,
            'dates' => $byDate,
        ];
    }

    private function sumSummaryRows($rows, array $dateColumns): array
    {
        $totalCollected = (float) $rows->sum('collected');
        $totalDoctorShare = (float) $rows->sum('doctor_share');

        $byDate = [];

        foreach ($dateColumns as $date) {
            $byDate[$date] = [
                'visits' => (int) $rows->sum(fn($row) => $row['dates'][$date]['visits'] ?? 0),
                'collected' => (float) $rows->sum(fn($row) => $row['dates'][$date]['collected'] ?? 0),
            ];
        }

        return [
            'visits' => (int) $rows->sum('visits'),
            'collected' => $totalCollected,
            'doctor_share' => $totalDoctorShare,
            'clinic_share' => $totalCollected - $totalDoctorShare,
            'dates' => $byDate,
        ];
    }

    private function sumDetailRows($rows): array
    {
        return [
            'visits' => $rows->count(),
            'collected' => (float) $rows->sum('collected'),
            'doctor_share' => (float) $rows->sum('doctor_share'),
            'clinic_share' => (float) $rows->sum('clinic_share'),
        ];
    }
}
