<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorAppointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class DoctorConsultationListController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $doctors = Doctor::where('status', 'Active')
            ->where('doctor_name', 'like', 'Dr.%')
            ->orderBy('doctor_name')
            ->get(['id', 'doctor_name']);

        return view('apps-doctor-consultation-list', compact('doctors'));
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

        $rows = $this->buildList($request->doctor_id, $request->date);

        return response()->json([
            'status' => true,
            'data' => $rows
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

        $rows = $this->buildList($request->doctor_id, $request->date);

        $date = Carbon::parse($request->date);

        $pdf = Pdf::loadView(
            'apps-doctor-consultation-list-pdf',
            compact('doctor', 'rows', 'date')
        );

        $fileName = 'Consultation-List-' .
            str_replace(' ', '-', $doctor->doctor_name) .
            '-' . $date->format('d-m-Y') . '.pdf';

        return $pdf->stream($fileName);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function buildList($doctorId, $date)
    {
        $appointments = DoctorAppointment::leftJoin('invoices', function ($join) {

                $join->on('invoices.appointment_id', '=', 'doctor_appointments.id')
                    ->where('invoices.invoice_type', '=', 'DOCTOR_VISIT')
                    ->where(function ($q) {
                        $q->whereNull('invoices.cancelled')
                            ->orWhere('invoices.cancelled', '!=', 'Y');
                    });
            })
            ->where('doctor_appointments.doctor_id', $doctorId)
            ->whereDate('doctor_appointments.appointment_date', $date)
            ->where('doctor_appointments.appointment_status', '!=', 'Cancelled')
            ->orderBy('doctor_appointments.token_no')
            ->select(
                'doctor_appointments.id',
                'doctor_appointments.appointment_no',
                'doctor_appointments.token_no',
                'doctor_appointments.patient_name',
                'doctor_appointments.patient_mobile_no',
                'doctor_appointments.patient_age',
                'doctor_appointments.patient_gender',
                'doctor_appointments.appointment_time',
                'doctor_appointments.appointment_status',
                'invoices.id as invoice_id',
                'invoices.invoice_no'
            )
            ->get();

        return $appointments->map(function ($row) {

            $row->appointment_time_fmt = $row->appointment_time
                ? Carbon::parse($row->appointment_time)->format('h:i A')
                : null;

            $row->invoice_prepared = !is_null($row->invoice_id);

            return $row;
        });
    }
}
