<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientHistoryController extends Controller
{
    private const MODULE_CODE = 'PATIENT';

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

    public function index()
    {
        return view('apps-patient-history');
    }

    /*
    |--------------------------------------------------------------------------
    | SEARCH PATIENTS BY MOBILE (FOR DROPDOWN)
    |--------------------------------------------------------------------------
    */

    public function searchByMobile(Request $request)
    {
        $request->validate([
            'mobile_no' => 'required'
        ]);

        $mobile = trim($request->mobile_no);

        $patients = Patient::where('mobile_no', $mobile)
            ->orderBy('patient_name')
            ->get(['id', 'patient_id', 'patient_name', 'age', 'gender', 'status']);

        if ($patients->isEmpty()) {

            $member = DB::table('users')
                ->where('mobile_no', $mobile)
                ->where('role', 'Member')
                ->first();

            if ($member) {

                return response()->json([
                    'status' => true,
                    'patients' => [[
                        'id' => null,
                        'patient_id' => null,
                        'patient_name' => $member->name,
                        'age' => null,
                        'gender' => null,
                        'status' => $member->status,
                    ]]
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => 'No patient found with this mobile number.'
            ]);
        }

        return response()->json([
            'status' => true,
            'patients' => $patients
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE PATIENT NAME / AGE / GENDER
    |--------------------------------------------------------------------------
    */

    public function updatePatient(Request $request, AuditService $auditService)
    {
        $request->validate([
            'id' => 'required|exists:patients,id',
            'patient_name' => 'required|string|max:255',
            'age' => 'nullable|integer|min:0|max:150',
            'gender' => 'nullable|in:Male,Female,Other',
        ]);

        $patient = Patient::findOrFail($request->id);

        $oldData = $patient->only($patient->getFillable());

        $patient->update([
            'patient_name' => $request->patient_name,
            'age' => $request->age,
            'gender' => $request->gender,
        ]);

        $auditService->logUpdate(
            self::MODULE_CODE,
            $patient,
            $oldData,
            $patient->only($patient->getFillable()),
            'Patient details updated via Patient History dashboard'
        );

        return response()->json([
            'status' => true,
            'message' => 'Patient details updated successfully.',
            'patient' => [
                'id' => $patient->id,
                'patient_id' => $patient->patient_id,
                'patient_name' => $patient->patient_name,
                'age' => $patient->age,
                'gender' => $patient->gender,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | FULL AGGREGATED HISTORY
    |--------------------------------------------------------------------------
    */

    public function getHistory(Request $request)
    {
        $request->validate([
            'patient_id' => 'nullable|string',
            'mobile_no' => 'required',
            'patient_name' => 'required',
            'period' => 'nullable|in:1,2,3,6,12,24,all',
        ]);

        $mobile = trim($request->mobile_no);
        $patientId = $request->patient_id ?: null;
        $patientName = $request->patient_name;
        $period = $request->period ?? '3';

        $fromDate = $period === 'all'
            ? null
            : now()->subMonths(self::PERIOD_MONTHS[$period])->startOfDay();

        $identityFilter = function ($query) use ($patientId, $mobile, $patientName) {
            $query->where(function ($q) use ($patientId, $mobile, $patientName) {

                if ($patientId) {
                    $q->orWhere('patient_id', $patientId);
                }

                $q->orWhere(function ($q2) use ($mobile, $patientName) {
                    $q2->where('patient_mobile_no', $mobile)
                        ->where('patient_name', $patientName);
                });
            });
        };

        /*
        |--------------------------------------------------------------------------
        | DOCTOR APPOINTMENTS
        |--------------------------------------------------------------------------
        */

        $appointments = DB::table('doctor_appointments')
            ->leftJoin('doctors', 'doctor_appointments.doctor_id', '=', 'doctors.id')
            ->when($fromDate, fn($q) => $q->where('doctor_appointments.appointment_date', '>=', $fromDate))
            ->where(function ($q) use ($patientId, $mobile, $patientName) {

                if ($patientId) {
                    $q->orWhere('doctor_appointments.patient_id', $patientId);
                }

                $q->orWhere(function ($q2) use ($mobile, $patientName) {
                    $q2->where('doctor_appointments.patient_mobile_no', $mobile)
                        ->where('doctor_appointments.patient_name', $patientName);
                });
            })
            ->select(
                'doctor_appointments.id',
                'doctor_appointments.appointment_no',
                'doctor_appointments.appointment_date',
                'doctor_appointments.appointment_time',
                'doctor_appointments.token_no',
                'doctor_appointments.consultation_fee',
                'doctor_appointments.appointment_status',
                'doctors.doctor_name'
            )
            ->orderByDesc('doctor_appointments.appointment_date')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | INVOICE FETCH HELPER (SHARED ACROSS ALL INVOICE-BASED SECTIONS)
        |--------------------------------------------------------------------------
        */

        $fetchInvoices = function ($types) use ($fromDate, $identityFilter) {

            return DB::table('invoices')
                ->whereIn('invoice_type', (array) $types)
                ->when($fromDate, fn($q) => $q->where('invoice_date', '>=', $fromDate))
                ->where($identityFilter)
                ->where(function ($q) {
                    $q->whereNull('cancelled')->orWhere('cancelled', '!=', 'Y');
                })
                ->orderByDesc('invoice_date')
                ->get();
        };

        /*
        |--------------------------------------------------------------------------
        | DOCTOR VISIT INVOICES
        |--------------------------------------------------------------------------
        */

        $doctorVisitInvoices = $fetchInvoices('DOCTOR_VISIT');

        /*
        |--------------------------------------------------------------------------
        | DIAGNOSTIC INVOICES (+ TEST ITEMS)
        |--------------------------------------------------------------------------
        */

        $diagnosticInvoices = $fetchInvoices('DIAGNOSTIC');

        $diagnosticInvoices->transform(function ($invoice) {

            $invoice->items = DB::table('invoice_details')
                ->where('invoice_no', $invoice->invoice_no)
                ->pluck('item_description');

            return $invoice;
        });

        /*
        |--------------------------------------------------------------------------
        | EQUIPMENT RENTALS (OXYGEN / CONCENTRATOR)
        |--------------------------------------------------------------------------
        */

        $equipmentRentals = $fetchInvoices(['OXYGEN_RENT', 'CONCENTRATOR_RENT']);

        $equipmentRentals->transform(function ($invoice) {

            $detail = DB::table('invoice_details')
                ->leftJoin('equipment_categories', 'equipment_categories.id', '=', 'invoice_details.equipment_category_id')
                ->where('invoice_details.invoice_no', $invoice->invoice_no)
                ->select('invoice_details.*', 'equipment_categories.category_name')
                ->first();

            $invoice->category_name = $detail->category_name ?? null;
            $invoice->quantity = $detail->quantity ?? null;
            $invoice->rental_return_date = $detail->rental_return_date ?? null;
            $invoice->rental_days = $detail->rental_days ?? null;
            $invoice->is_final_settlement = !empty($invoice->reference_no);

            return $invoice;
        });

        /*
        |--------------------------------------------------------------------------
        | AMBULANCE BOOKINGS
        |--------------------------------------------------------------------------
        */

        $ambulanceBookings = $fetchInvoices('AMBULANCE_RENT');

        $ambulanceBookings->transform(function ($invoice) {

            $detail = DB::table('invoice_details')
                ->where('invoice_no', $invoice->invoice_no)
                ->first();

            $invoice->destination = $detail->item_description ?? null;
            $invoice->from_destination = $detail->from_destination ?? null;
            $invoice->booking_type = $detail->booking_type ?? null;
            $invoice->pickup_time = $detail->pickup_time ?? null;
            $invoice->release_time = $detail->release_time ?? null;

            return $invoice;
        });

        /*
        |--------------------------------------------------------------------------
        | MEMBERSHIP (MATCHED BY MOBILE NUMBER, INDEPENDENT OF PATIENT_ID)
        |--------------------------------------------------------------------------
        */

        $membership = $this->buildMembershipSummary($mobile, $fromDate);

        return response()->json([
            'status' => true,
            'period' => $period,
            'appointments' => $appointments,
            'doctor_visit_invoices' => $doctorVisitInvoices,
            'diagnostic_invoices' => $diagnosticInvoices,
            'equipment_rentals' => $equipmentRentals,
            'ambulance_bookings' => $ambulanceBookings,
            'membership' => $membership,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function buildMembershipSummary(string $mobile, ?Carbon $fromDate): array
    {
        $member = DB::table('users')
            ->where('mobile_no', $mobile)
            ->where('role', 'Member')
            ->first();

        if (!$member) {
            return ['is_member' => false];
        }

        $lastPaidMonth = DB::table('membership_fee_payments')
            ->where('user_id', $member->id)
            ->max('payment_month');

        $currentMonth = now()->startOfMonth();

        $nextDueMonth = $lastPaidMonth
            ? Carbon::parse($lastPaidMonth)->addMonthNoOverflow()->startOfMonth()
            : $currentMonth;

        $isDue = $nextDueMonth->lte($currentMonth);

        $monthsOverdue = $isDue
            ? $nextDueMonth->diffInMonths($currentMonth) + 1
            : 0;

        $paymentsQuery = DB::table('membership_fee_payments')
            ->where('user_id', $member->id);

        if ($fromDate) {
            $paymentsQuery->where('payment_month', '>=', $fromDate->format('Y-m-01'));
        }

        $payments = $paymentsQuery
            ->orderByDesc('payment_month')
            ->get();

        $payments->transform(function ($row) {

            $row->month_label = Carbon::parse($row->payment_month)->format('F Y');
            $row->created_dt = $row->created_at
                ? Carbon::parse($row->created_at)->format('d-m-Y H:i')
                : null;

            $row->invoice_id = $row->invoice_no
                ? DB::table('invoices')->where('invoice_no', $row->invoice_no)->value('id')
                : null;

            return $row;
        });

        return [
            'is_member' => true,
            'user_id' => $member->id,
            'name' => $member->name,
            'mobile_no' => $member->mobile_no,
            'status' => $member->status,
            'date_of_joining' => $this->safeDate($member->date_of_joining),
            'last_paid_month' => $lastPaidMonth
                ? Carbon::parse($lastPaidMonth)->format('F Y')
                : null,
            'is_due' => $isDue,
            'months_overdue' => $monthsOverdue,
            'payments' => $payments,
        ];
    }

    private function safeDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        try {

            $parsed = Carbon::parse($date);

            if ($parsed->year < 1990) {
                return null;
            }

            return $parsed->toDateString();

        } catch (\Exception $e) {

            return null;
        }
    }
}
