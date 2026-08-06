<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\DoctorAppointment;
use App\Models\DoctorScheduleException;
use App\Models\DoctorScheduleSession;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The actual "create a booking" logic, shared by the internal staff booking
 * screen (DoctorAppointmentController) and the public patient booking page
 * (PublicAppointmentController), so the two entry points can't drift apart.
 */
class AppointmentBookingService
{
    // Token numbering for a doctor's appointments on a given date starts
    // at 3 (not 1), per explicit request -- the first two token slots are
    // intentionally left unused by online booking.
    private const FIRST_TOKEN_NO = 3;

    private const PATIENT_MODULE_CODE = 'PATIENT';

    private const APPOINTMENT_MODULE_CODE = 'DOCTOR_APPOINTMENT';

    public function __construct(private WatiService $wati, private AuditService $auditService)
    {
    }

    /**
     * @param array $data doctor_id, doctor_schedule_session_id, patient_name,
     *   patient_mobile_no, appointment_date, appointment_time, patient_age,
     *   patient_gender (optional), patient_id (optional -- real patients.id
     *   of an existing patient matched by the operator; when present that
     *   patient's name/age/gender are corrected in place instead of
     *   matching by name text), remarks (optional, null for public bookings),
     *   created_by (optional, null for public bookings)
     * @return array{status: bool, message: string, appointment: ?DoctorAppointment}
     */
    public function book(array $data): array
    {
        $doctor = Doctor::find($data['doctor_id']);

        if (!$doctor) {
            return ['status' => false, 'message' => 'Doctor not found.', 'appointment' => null];
        }

        // Belt-and-suspenders: getAvailableSlots() already keeps a blacked-out
        // date's slots off the client entirely, but a booking could still
        // arrive here via a stale form, a retried request, or a direct API
        // call -- never trust the client to have honored that.
        $isBlackedOut = DoctorScheduleException::where('doctor_id', $data['doctor_id'])
            ->whereDate('exception_date', $data['appointment_date'])
            ->exists();

        if ($isBlackedOut) {
            return [
                'status' => false,
                'message' => 'The doctor is not available on this date. Please choose a different date.',
                'appointment' => null,
            ];
        }

        $session = DoctorScheduleSession::find($data['doctor_schedule_session_id']);

        if (!$session) {
            return ['status' => false, 'message' => 'Schedule session not found.', 'appointment' => null];
        }

        // The client always refetches slots for the exact picked date, and
        // each slot's session_id is scoped to that date's weekday -- but
        // nothing here re-checks that after the fact. A stale/out-of-order
        // AJAX response (the date field changes again before an earlier
        // available-slots response comes back -- plausible on the public
        // page's mobile-network patients), a retried request, or a direct
        // API call could otherwise submit a session for one weekday
        // alongside an appointment_date that falls on a different one.
        $requestedDay = strtolower(Carbon::parse($data['appointment_date'])->format('l'));

        if ($requestedDay !== $session->day_of_week) {
            return [
                'status' => false,
                'message' => 'This slot is not available on the selected date. Please re-select the date and slot.',
                'appointment' => null,
            ];
        }

        $slotCount = DoctorAppointment::where('doctor_id', $data['doctor_id'])
            ->where('doctor_schedule_session_id', $data['doctor_schedule_session_id'])
            ->where('appointment_date', $data['appointment_date'])
            ->where('appointment_time', $data['appointment_time'])
            ->count();

        if ($slotCount >= $session->max_patient) {
            return [
                'status' => false,
                'message' => "Maximum {$session->max_patient} patients are allowed in this slot. Try for different slot.",
                'appointment' => null,
            ];
        }

        // A real patients.id (set when the operator matched an existing
        // patient via the mobile-number search before this booking) means
        // this is a known patient whose record we should correct in place
        // -- matching by exact name text instead would silently miss them
        // the moment their name is corrected and create a duplicate.
        $patient = !empty($data['patient_id'])
            ? Patient::find($data['patient_id'])
            : null;

        if ($patient) {

            $oldPatientData = $patient->only(['patient_name', 'age', 'gender']);

            $patient->update([
                'patient_name' => $data['patient_name'],
                'age' => $data['patient_age'] ?? $patient->age,
                'gender' => $data['patient_gender'] ?? $patient->gender,
            ]);

            $newPatientData = $patient->only(['patient_name', 'age', 'gender']);

            if ($oldPatientData !== $newPatientData) {

                $this->auditService->logUpdate(
                    self::PATIENT_MODULE_CODE,
                    $patient,
                    $oldPatientData,
                    $newPatientData,
                    'Patient details corrected during appointment booking'
                );
            }

        } else {

            $patient = Patient::where('mobile_no', $data['patient_mobile_no'])
                ->where('patient_name', $data['patient_name'])
                ->first();
        }

        if (!$patient) {

            $count = Patient::where('mobile_no', $data['patient_mobile_no'])->count() + 1;

            $patientCode = \App\Support\OfflineMode::prefix('P') . $data['patient_mobile_no'] . '-' . str_pad($count, 2, '0', STR_PAD_LEFT);

            $patient = Patient::create([
                'patient_id' => $patientCode,
                'patient_name' => $data['patient_name'],
                'mobile_no' => $data['patient_mobile_no'],
                'age' => $data['patient_age'] ?? null,
                'gender' => $data['patient_gender'] ?? null,
                'status' => 'A',
            ]);
        }

        $existingAppointment = DoctorAppointment::where('doctor_id', $data['doctor_id'])
            ->where('patient_id', $patient->patient_id)
            ->where('appointment_date', $data['appointment_date'])
            ->where('doctor_schedule_session_id', $data['doctor_schedule_session_id'])
            ->first();

        if ($existingAppointment && $existingAppointment->appointment_time == $data['appointment_time']) {
            return [
                'status' => false,
                'message' => 'Appointment already booked for same patient, doctor, date and slot.',
                'appointment' => null,
            ];
        }

        if ($existingAppointment && $existingAppointment->appointment_time != $data['appointment_time']) {

            $oldAppointmentData = $existingAppointment->only($existingAppointment->getFillable());

            $existingAppointment->update([
                'appointment_time' => $data['appointment_time'],
                'remarks' => $data['remarks'] ?? null,
                'updated_by' => $data['created_by'] ?? null,
            ]);

            $this->auditService->logUpdate(
                self::APPOINTMENT_MODULE_CODE,
                $existingAppointment,
                $oldAppointmentData,
                $existingAppointment->only($existingAppointment->getFillable()),
                'Appointment slot updated (re-booked to a different time)'
            );

            return [
                'status' => true,
                'message' => 'Appointment slot updated successfully.',
                'appointment' => $existingAppointment,
            ];
        }

        $appointment = $this->createWithRetry($data, $patient, $doctor);

        if (!$appointment) {
            return [
                'status' => false,
                'message' => 'Could not book this slot right now, please try again.',
                'appointment' => null,
            ];
        }

        $this->sendConfirmation($appointment, $doctor, $data);

        return ['status' => true, 'message' => 'Appointment booked successfully.', 'appointment' => $appointment];
    }

    /**
     * token_no/appointment_no are generated by a read-count-then-insert pattern,
     * which races under concurrent bookings. doctor_appointments is MyISAM, so
     * DB::transaction()/lockForUpdate() would not actually provide row-level
     * isolation here (MyISAM has no real transactions). The real backstop is
     * the unique index on (doctor_id, appointment_date, token_no) added
     * alongside this service -- a collision throws a 23000 integrity-constraint
     * QueryException, which we catch and retry once with a freshly recomputed
     * token_no instead of silently issuing a duplicate.
     */
    private function createWithRetry(array $data, Patient $patient, Doctor $doctor, int $attemptsLeft = 2): ?DoctorAppointment
    {
        while ($attemptsLeft > 0) {

            $existingCount = DoctorAppointment::where('doctor_id', $data['doctor_id'])
                ->whereDate('appointment_date', $data['appointment_date'])
                ->count();

            $tokenNo = self::FIRST_TOKEN_NO + $existingCount;

            $appointmentNo = \App\Support\OfflineMode::prefix('AP') . Carbon::parse($data['appointment_date'])->format('dmy') .
                str_pad($data['doctor_id'], 3, '0', STR_PAD_LEFT) .
                str_pad($tokenNo, 3, '0', STR_PAD_LEFT);

            try {

                $appointment = DoctorAppointment::create([
                    'appointment_no' => $appointmentNo,
                    'doctor_id' => $data['doctor_id'],
                    'doctor_schedule_session_id' => $data['doctor_schedule_session_id'],
                    'patient_id' => $patient->patient_id,
                    'patient_name' => $data['patient_name'],
                    'patient_mobile_no' => $data['patient_mobile_no'],
                    'patient_age' => $data['patient_age'] ?? null,
                    'patient_gender' => $data['patient_gender'] ?? null,
                    'appointment_date' => $data['appointment_date'],
                    'appointment_time' => $data['appointment_time'],
                    'token_no' => $tokenNo,
                    'consultation_fee' => $doctor->consultation_fee_total,
                    'appointment_status' => 'Booked',
                    'remarks' => $data['remarks'] ?? null,
                    'created_by' => $data['created_by'] ?? null,
                ]);

                $this->auditService->logCreate(
                    self::APPOINTMENT_MODULE_CODE,
                    $appointment,
                    $appointment->only($appointment->getFillable()),
                    'Appointment booked'
                );

                return $appointment;

            } catch (QueryException $e) {

                $attemptsLeft--;

                if ($e->getCode() !== '23000' || $attemptsLeft <= 0) {
                    throw $e;
                }
            }
        }

        return null;
    }

    private function sendConfirmation(DoctorAppointment $appointment, Doctor $doctor, array $data): void
    {
        $whatsappResponse = null;
        $whatsappSent = false;

        try {

            $visitDate = Carbon::parse($data['appointment_date'])->format('d-m-Y');
            $visitTime = date('h:i A', strtotime($data['appointment_time']));

            $whatsappResponse = $this->wati->sendTemplateMessage(

                '91' . preg_replace('/\D/', '', $data['patient_mobile_no']),

                'doctor_appointment32',

                'doctor_appointment_new2',

                [
                    ['name' => 'first_name', 'value' => $data['patient_name']],
                    ['name' => 'name', 'value' => preg_replace('/^dr\.?\s+/i', '', $doctor->doctor_name)],
                    ['name' => 'visit_date', 'value' => $visitDate],
                    ['name' => 'visit_time', 'value' => $visitTime],
                    ['name' => 'queue', 'value' => $appointment->token_no],
                ]
            );

            $whatsappSent = is_array($whatsappResponse) && ($whatsappResponse['result'] ?? false) === true;

        } catch (\Exception $e) {

            Log::error('Doctor Appointment WhatsApp Error: ' . $e->getMessage());

            $whatsappResponse = ['error' => $e->getMessage()];
        }

        DB::table('whatsapp_message_logs')->insert([
            'invoice_no' => $appointment->appointment_no,
            'mobile_no' => $data['patient_mobile_no'],
            'message_type' => 'APPOINTMENT',
            'status' => $whatsappSent ? 'SENT' : 'FAILED',
            'response' => json_encode($whatsappResponse),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
