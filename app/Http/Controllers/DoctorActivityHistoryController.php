<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Per-doctor activity history: pick a doctor from a dropdown of every
 * doctor -> period filter -> sectioned tables. Distinct from the existing
 * DoctorHistoryController ("Doctor Visit History", route prefix
 * doctor-history) which is an admin report across any/all doctors for a
 * chosen date range -- that page is left untouched; this one is a single
 * doctor's own history, including diagnostic-test referral involvement
 * (not just their own consultations).
 */
class DoctorActivityHistoryController extends Controller
{
    private const PERIOD_MONTHS = [
        '1' => 1,
        '2' => 2,
        '3' => 3,
        '6' => 6,
        '12' => 12,
        '24' => 24,
    ];

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    /**
     * No search step -- the dropdown is simply every doctor, ordered by
     * name, with specialisation shown alongside each option so staff can
     * spot the right one even when several doctors share a name.
     */
    public function index()
    {
        $doctors = Doctor::orderBy('doctor_name')
            ->get(['id', 'doctor_name', 'doctor_code', 'qualification', 'specialisation']);

        return view('apps-doctor-activity-history', compact('doctors'));
    }

    /*
    |--------------------------------------------------------------------------
    | FULL AGGREGATED HISTORY
    |--------------------------------------------------------------------------
    */

    public function getHistory(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|integer|exists:doctors,id',
            'period' => 'nullable|in:1,2,3,6,12,24,all',
        ]);

        $doctorId = $request->doctor_id;
        $period = $request->period ?? '3';

        $fromDate = $period === 'all'
            ? null
            : now()->subMonths(self::PERIOD_MONTHS[$period])->startOfDay();

        $notCancelled = function ($q) {
            $q->whereNull('cancelled')->orWhere('cancelled', '!=', 'Y');
        };

        /*
        |--------------------------------------------------------------------------
        | APPOINTMENTS
        |--------------------------------------------------------------------------
        */

        $appointments = DB::table('doctor_appointments')
            ->where('doctor_id', $doctorId)
            ->when($fromDate, fn($q) => $q->where('appointment_date', '>=', $fromDate))
            ->select(
                'id', 'appointment_no', 'appointment_date', 'appointment_time',
                'token_no', 'consultation_fee', 'appointment_status',
                'patient_name', 'patient_mobile_no'
            )
            ->orderByDesc('appointment_date')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | DOCTOR VISIT (CONSULTATION) INVOICES
        |--------------------------------------------------------------------------
        */

        $doctorVisitInvoices = DB::table('invoices')
            ->where('invoice_type', 'DOCTOR_VISIT')
            ->where('doctor_id', $doctorId)
            ->when($fromDate, fn($q) => $q->where('invoice_date', '>=', $fromDate))
            ->where($notCancelled)
            ->orderByDesc('invoice_date')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | DIAGNOSTIC TEST INVOLVEMENT (REFERRAL / COMMISSION LINE ITEMS)
        |--------------------------------------------------------------------------
        | invoice_details.doctor_id marks this doctor as the referring/
        | commission doctor for that billed line -- independent of who's
        | actually named on the invoice header, and independent of
        | DoctorHistoryController's appointment-based view above.
        */

        $diagnosticInvolvement = DB::table('invoice_details')
            ->join('invoices', 'invoices.invoice_no', '=', 'invoice_details.invoice_no')
            ->where('invoice_details.doctor_id', $doctorId)
            ->when($fromDate, fn($q) => $q->where('invoices.invoice_date', '>=', $fromDate))
            ->where($notCancelled)
            ->select(
                'invoices.id as invoice_id',
                'invoices.invoice_no',
                'invoices.invoice_date',
                'invoices.patient_name',
                'invoices.due_amount',
                'invoice_details.item_description',
                'invoice_details.amount',
                'invoice_details.payment_value'
            )
            ->orderByDesc('invoices.invoice_date')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | SETTLEMENTS / PAYMENTS RECEIVED
        |--------------------------------------------------------------------------
        */

        $settlements = DB::table('doctor_settlements')
            ->where('doctor_id', $doctorId)
            ->where('status', '!=', 'CANCELLED')
            ->when($fromDate, fn($q) => $q->where('settlement_date', '>=', $fromDate))
            ->orderByDesc('settlement_date')
            ->get();

        return response()->json([
            'status' => true,
            'period' => $period,
            'appointments' => $appointments,
            'doctor_visit_invoices' => $doctorVisitInvoices,
            'diagnostic_involvement' => $diagnosticInvolvement,
            'settlements' => $settlements,
        ]);
    }
}
