<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class DoctorHistoryController extends Controller
{
    /**
     * ----------------------------------------------------------
     * Doctor History Report
     * ----------------------------------------------------------
     */

    public function index()
    {
        $doctors = Doctor::where('status', 1)
            ->orderBy('doctor_name')
            ->get();

        return view('apps-doctor-history', compact('doctors'));
    }

    /**
     * ----------------------------------------------------------
     * Search Report
     * ----------------------------------------------------------
     */
    /**
     * ------------------------------------------------------------------
     * SEARCH DOCTOR HISTORY
     * ------------------------------------------------------------------
     */
    public function search(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        try {
            $data = $this->getHistoryData($request);

            return response()->json([
                'success' => true,
                'data' => $data['rows'],
                'summary' => $data['summary'],
                'daily_summary' => $data['daily_summary'],
                'doctor_summary' => $data['doctor_summary'],
            ]);



        } catch (\Exception $e) {

            Log::error('Doctor History Search Error', [

                'message' => $e->getMessage(),

                'line' => $e->getLine(),

                'file' => $e->getFile(),

                'user_id' => Auth::id(),

            ]);

            return response()->json([

                'success' => false,

                'message' => $e->getMessage()

            ], 500);

        }
    }

    /**
     * ----------------------------------------------------------
     * PRINT PDF
     * ----------------------------------------------------------
     */
    public function printPdf(Request $request)
    {
        try {

            $request->validate([
                'doctor_id' => 'required',
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Load Report Data
            |--------------------------------------------------------------------------
            */

            $data = $this->getHistoryData($request);

            $doctor = 'All Doctors';

            if ($request->doctor_id != 'ALL') {
                $doctor = Doctor::find($request->doctor_id)?->doctor_name;
            }

            $pdf = Pdf::loadView(
                'apps-doctor-history-pdf',
                [
                    'rows' => $data['rows'],
                    'summary' => $data['summary'],
                    'daily_summary' => $data['daily_summary'],
                    'doctor_summary' => $data['doctor_summary'],
                    'doctor' => $doctor,
                    'fromDate' => Carbon::parse($request->from_date)->format('d-m-Y'),
                    'toDate' => Carbon::parse($request->to_date)->format('d-m-Y'),
                    'printedBy' => Auth::user()->name,
                    'printedOn' => now()->format('d-m-Y h:i A'),
                ]
            );
            $pdf->setPaper('A4', 'landscape');

            return $pdf->stream(
                'Doctor_History_' .
                now()->format('YmdHis') .
                '.pdf'
            );
        } catch (\Exception $e) {

            Log::error('Doctor History PDF Error', [

                'message' => $e->getMessage(),

                'line' => $e->getLine(),

            ]);

            return back()->with('error', $e->getMessage());

        }
    }

    /**
     * ----------------------------------------------------------
     * EXPORT EXCEL
     * ----------------------------------------------------------
     */
    public function exportExcel(Request $request)
    {

        $data = $this->getHistoryData($request);

        $filename = 'DoctorHistory_' .
            now()->format('YmdHis') .
            '.csv';

        $headers = [

            'Content-Type' => 'text/csv',

            'Content-Disposition' =>
                'attachment; filename="' .
                $filename .
                '"',

        ];

        

            $callback = function () use ($data) {

            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Visit Date',
                'Visit Time',
                'Invoice No',
                'Patient',
                'Gender',
                'Age',
                'Mobile',
                'Doctor',
                'Specialisation',
                
            ]);

            foreach ($data['rows'] as $row) {

                fputcsv($file, [

                    $row->visit_date,
                    $row->visit_time,
                    $row->invoice_no,
                    $row->patient_name,
                    $row->patient_gender,
                    $row->patient_age,
                    $row->patient_mobile_no,
                    $row->doctor_name,
                    $row->specialisation,
                    

                ]);

            }

            fclose($file);

        };

        return response()->stream($callback, 200, $headers);

    }

    /**
     * ----------------------------------------------------------
     * ACTION BUTTONS
     * ----------------------------------------------------------
     */
    

    /**
 * ------------------------------------------------------------------
 * COMMON DATA LOADER
 * ------------------------------------------------------------------
 */
private function getHistoryData(Request $request): array
{
    $query = DB::table('doctor_appointments')
        ->join(
            'doctors',
            'doctor_appointments.doctor_id',
            '=',
            'doctors.id'
        )
        ->leftJoin(
            'specialisations',
            'doctors.specialisation_code',
            '=',
            'specialisations.id'
        )
        ->leftJoin('invoices', function ($join) {

            $join->on(
                'doctor_appointments.id',
                '=',
                'invoices.appointment_id'
            )
            ->where('invoices.invoice_type', 'DOCTOR_VISIT')
            ->where(function ($q) {

                $q->whereNull('invoices.cancelled')
                  ->orWhere('invoices.cancelled', 'N');

            });

        })

        ->select(

            'doctor_appointments.id',

            'doctor_appointments.appointment_no',

            'doctor_appointments.patient_id',

            'doctor_appointments.patient_name',

            'doctor_appointments.patient_gender',

            'doctor_appointments.patient_age',

            'doctor_appointments.patient_mobile_no',

            'doctor_appointments.appointment_date',

            'doctor_appointments.appointment_time',

            'doctors.doctor_name',

            'specialisations.category as specialisation',

            'invoices.id as invoice_id',

            'invoices.invoice_no',

            'invoices.doctor_payment_amount',

            'invoices.payment_mode',

            'invoices.status',

            'invoices.cancelled'

        )

        ->whereBetween(
            'doctor_appointments.appointment_date',
            [
                $request->from_date,
                $request->to_date
            ]
        );

    if ($request->doctor_id != 'ALL') {

        $query->where(
            'doctor_appointments.doctor_id',
            $request->doctor_id
        );

    }

    $rows = $query
        ->orderBy('doctor_appointments.appointment_date')
        ->orderBy('doctor_appointments.appointment_time')
        ->get();

            foreach ($rows as $row) {

                $row->visit_date = Carbon::parse(
                    $row->appointment_date
                )->format('d-m-Y');

                $row->visit_time = Carbon::parse(
                    $row->appointment_time
                )->format('h:i A');

                $row->actions = $this->buildActionButtons(
                    $row->invoice_id,
                    $row->cancelled
                );
            }

return [

        'rows' => $rows,

        'summary' => [

            'total_patients' =>
                $rows->count(),

            'male_patients' =>
                $rows->filter(function ($r) {

                    return in_array(
                        strtoupper($r->patient_gender),
                        ['M', 'MALE']
                    );

                })->count(),

            'female_patients' =>
                $rows->filter(function ($r) {

                    return in_array(
                        strtoupper($r->patient_gender),
                        ['F', 'FEMALE']
                    );

                })->count(),

            

        ],

        'daily_summary' =>
            $this->buildDailySummary($rows),

        'doctor_summary' =>
            $this->buildDoctorSummary($rows),

    ];
}

/**
 * ------------------------------------------------------------------
 * DAILY SUMMARY
 * ------------------------------------------------------------------
 */
private function buildDailySummary($rows)
{
    return $rows
        ->groupBy('appointment_date')
        ->map(function ($items) {

            return [

                'patients' =>
                    $items->count(),

                'male' =>
                    $items->filter(function ($r) {

                        return in_array(
                            strtoupper($r->patient_gender),
                            ['M', 'MALE']
                        );

                    })->count(),

                'female' =>
                    $items->filter(function ($r) {

                        return in_array(
                            strtoupper($r->patient_gender),
                            ['F', 'FEMALE']
                        );

                    })->count(),

                'doctor_payment' =>
                    $items->sum('doctor_payment_amount'),

            ];

        });

}

/**
 * ------------------------------------------------------------------
 * DOCTOR SUMMARY
 * ------------------------------------------------------------------
 */
private function buildDoctorSummary($rows)
{
    return $rows
        ->groupBy('doctor_name')
        ->map(function ($items) {

            return [

                'patients' =>
                    $items->count(),

                'amount' =>
                    $items->sum('doctor_payment_amount'),

            ];

        })
        ->sortKeys();
}

/**
 * ------------------------------------------------------------------
 * ACTION BUTTONS
 * ------------------------------------------------------------------
 */
    private function buildActionButtons($invoiceId, $cancelled = 'N')
    {
        if (!$invoiceId) {
            return '';
        }

        $html = '';

        // Print
        $html .=
            '<a href="' .
            url('/doctor-visit-invoice/print/' . $invoiceId) .
            '" target="_blank"
        class="btn btn-sm btn-primary me-1"
        title="Print Invoice">
            <i class="ri-printer-line"></i>
        </a>';

        // WhatsApp
        $html .=
            '<a href="' .
            url('/doctor-visit-invoice/send-whatsapp/' . $invoiceId) .
            '"
        class="btn btn-sm btn-success sendWhatsapp"
        data-id="' . $invoiceId . '"
        title="Send WhatsApp">
            <i class="ri-whatsapp-line"></i>
        </a>';

        return $html;
    }

}
