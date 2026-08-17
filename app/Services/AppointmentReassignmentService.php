<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\DoctorAppointment;
use App\Models\DoctorScheduleException;
use App\Models\DoctorScheduleSession;
use App\Models\Invoice;
use App\Models\WhatsappAutoSendSetting;
use App\Support\OfflineMode;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Suggests and commits bulk reassignment of Booked appointments that got
 * caught by a newly-added doctor schedule exception (blackout date) --
 * triggered from DoctorScheduleExceptionController::store(), not a
 * general-purpose "reschedule any appointment" feature (no such feature
 * exists elsewhere in this app yet). Deliberately NOT built on top of
 * AppointmentBookingService::book(), which matches/creates bookings by
 * patient identity and isn't safe to point at a specific existing row --
 * feeding it a reassignment risks silently merging into an unrelated
 * booking for the same patient.
 */
class AppointmentReassignmentService
{
    private const APPOINTMENT_MODULE_CODE = 'DOCTOR_APPOINTMENT';

    // Matches AppointmentBookingService::FIRST_TOKEN_NO -- must stay in sync,
    // it's the same numbering scheme applied to the same table/column.
    private const FIRST_TOKEN_NO = 3;

    private const SUGGESTION_HORIZON_DAYS = 45;

    public function __construct(
        private DoctorScheduleQueryService $scheduleQuery,
        private AuditService $auditService,
        private WatiService $wati
    ) {
    }

    /**
     * @param iterable<DoctorAppointment> $affectedAppointments already
     *   filtered to Booked rows on the just-blacked-out date(s), ordered by
     *   (appointment_date, token_no) for stable earliest-first assignment.
     * @return array<int, array>
     */
    public function suggestReassignments(int $doctorId, iterable $affectedAppointments): array
    {
        $batchUsage = []; // "date|session_id|time" => count already claimed within this call

        $queue = [];

        foreach ($affectedAppointments as $appointment) {

            $slot = $this->findNextAvailableSlot($doctorId, $appointment->appointment_date, $batchUsage);

            $queue[] = [
                'appointment_id' => $appointment->id,
                'appointment_no' => $appointment->appointment_no,
                'patient_name' => $appointment->patient_name,
                'patient_mobile_no' => $appointment->patient_mobile_no,
                'old_date' => $appointment->appointment_date,
                'old_time' => $appointment->appointment_time,
                'old_time_display' => Carbon::parse($appointment->appointment_time)->format('h:i A'),
                'suggested_date' => $slot['date'] ?? null,
                'suggested_time' => $slot['time'] ?? null,
                'suggested_time_display' => $slot['display'] ?? null,
                'suggested_session_id' => $slot['session_id'] ?? null,
                'no_slot_found' => $slot === null,
            ];

            if ($slot) {
                $batchUsage[$slot['key']] = ($batchUsage[$slot['key']] ?? 0) + 1;
            }
        }

        return $queue;
    }

    private function findNextAvailableSlot(int $doctorId, string $fromDate, array $batchUsage): ?array
    {
        $cursor = Carbon::parse($fromDate)->addDay();

        for ($i = 0; $i < self::SUGGESTION_HORIZON_DAYS; $i++, $cursor->addDay()) {

            $dateStr = $cursor->toDateString();

            $slots = $this->scheduleQuery->getAvailableSlots($doctorId, $dateStr);

            foreach ($slots as $slot) {

                $key = "{$dateStr}|{$slot['session_id']}|{$slot['value']}";
                $used = $batchUsage[$key] ?? 0;

                if (($slot['remaining'] - $used) > 0) {

                    return [
                        'date' => $dateStr,
                        'time' => $slot['value'],
                        'session_id' => $slot['session_id'],
                        'display' => $slot['display'],
                        'key' => $key,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param array $assignments each: ['appointment_id','new_date','new_time','new_session_id']
     * @return array{committed: array, failed: array}
     */
    public function commitReassignments(array $assignments, ?int $updatedBy): array
    {
        $committed = [];
        $failed = [];

        foreach ($assignments as $assignment) {

            $appointment = DoctorAppointment::find($assignment['appointment_id']);

            if (!$appointment || $appointment->appointment_status !== 'Booked') {
                $failed[] = ['appointment_id' => $assignment['appointment_id'], 'reason' => 'Appointment no longer booked or not found.'];
                continue;
            }

            if (Invoice::where('appointment_id', $appointment->id)->exists()) {
                $failed[] = ['appointment_id' => $appointment->id, 'reason' => 'This appointment already has an invoice -- reassign it manually.'];
                continue;
            }

            $isBlackedOut = DoctorScheduleException::where('doctor_id', $appointment->doctor_id)
                ->whereDate('exception_date', $assignment['new_date'])
                ->exists();

            if ($isBlackedOut) {
                $failed[] = ['appointment_id' => $appointment->id, 'reason' => 'Selected date is unavailable for this doctor.'];
                continue;
            }

            $session = DoctorScheduleSession::find($assignment['new_session_id']);
            $requestedDay = strtolower(Carbon::parse($assignment['new_date'])->format('l'));

            if (!$session || $requestedDay !== $session->day_of_week) {
                $failed[] = ['appointment_id' => $appointment->id, 'reason' => 'Selected slot is not valid for the chosen date.'];
                continue;
            }

            $slotCount = DoctorAppointment::where('doctor_id', $appointment->doctor_id)
                ->where('doctor_schedule_session_id', $session->id)
                ->where('appointment_date', $assignment['new_date'])
                ->where('appointment_time', $assignment['new_time'])
                ->where('id', '!=', $appointment->id)
                ->count();

            if ($slotCount >= $session->max_patient) {
                $failed[] = ['appointment_id' => $appointment->id, 'reason' => 'Selected slot is now full -- pick another.'];
                continue;
            }

            $saved = $this->saveWithRetry($appointment, $session, $assignment['new_date'], $assignment['new_time'], $updatedBy);

            if (!$saved) {
                $failed[] = ['appointment_id' => $appointment->id, 'reason' => 'Could not reassign right now, please retry.'];
                continue;
            }

            $committed[] = [
                'appointment_no' => $appointment->appointment_no,
                'patient_name' => $appointment->patient_name,
                'patient_mobile_no' => $appointment->patient_mobile_no,
                'doctor_id' => $appointment->doctor_id,
                'old_date' => $saved['old_date'],
                'old_time' => $saved['old_time'],
                'new_date' => $assignment['new_date'],
                'new_time' => Carbon::parse($assignment['new_time'])->format('h:i A'),
            ];
        }

        return ['committed' => $committed, 'failed' => $failed];
    }

    /**
     * Mirrors AppointmentBookingService::createWithRetry()'s token/appointment_no
     * numbering + retry-on-23000 pattern, but for an UPDATE of an existing row
     * rather than an insert -- doc_appts_doctor_date_token_unique applies on
     * update just the same as on insert.
     */
    private function saveWithRetry(
        DoctorAppointment $appointment,
        DoctorScheduleSession $session,
        string $newDate,
        string $newTime,
        ?int $updatedBy,
        int $attemptsLeft = 3
    ): ?array {

        while ($attemptsLeft > 0) {

            $existingCount = DoctorAppointment::where('doctor_id', $appointment->doctor_id)
                ->whereDate('appointment_date', $newDate)
                ->where('id', '!=', $appointment->id)
                ->count();

            $tokenNo = self::FIRST_TOKEN_NO + $existingCount;

            $appointmentNo = OfflineMode::prefix('AP') . Carbon::parse($newDate)->format('dmy') .
                str_pad((string) $appointment->doctor_id, 3, '0', STR_PAD_LEFT) .
                str_pad((string) $tokenNo, 3, '0', STR_PAD_LEFT);

            $oldData = $appointment->only($appointment->getFillable());
            $oldDate = $appointment->appointment_date;
            $oldTimeDisplay = Carbon::parse($appointment->appointment_time)->format('h:i A');

            try {

                $appointment->update([
                    'doctor_schedule_session_id' => $session->id,
                    'appointment_date' => $newDate,
                    'appointment_time' => $newTime,
                    'token_no' => $tokenNo,
                    'appointment_no' => $appointmentNo,
                    'updated_by' => $updatedBy,
                ]);

                $this->auditService->logUpdate(
                    self::APPOINTMENT_MODULE_CODE,
                    $appointment,
                    $oldData,
                    $appointment->only($appointment->getFillable()),
                    'Appointment reassigned due to doctor blackout date'
                );

                return ['old_date' => $oldDate, 'old_time' => $oldTimeDisplay];

            } catch (QueryException $e) {

                $attemptsLeft--;

                if ($e->getCode() !== '23000' || $attemptsLeft <= 0) {
                    return null;
                }
            }
        }

        return null;
    }

    /**
     * Mirrors DoctorScheduleController::sendScheduleChangeWhatsapp() exactly
     * (same WATI template -- its visit_date_old/visit_date_new placeholders
     * already support a pure date change, no new template needed), but logs
     * under a distinct message_type since a blackout-driven date move is a
     * different event than a same-day session-time nudge.
     *
     * @param array $committedQueue rows from commitReassignments()['committed']
     */
    public function notifyPatients(array $committedQueue): int
    {
        $notified = 0;
        $doctorsCache = [];

        foreach ($committedQueue as $item) {

            if (!array_key_exists($item['doctor_id'], $doctorsCache)) {
                $doctorsCache[$item['doctor_id']] = Doctor::find($item['doctor_id']);
            }

            $doctor = $doctorsCache[$item['doctor_id']];

            if (!WhatsappAutoSendSetting::isEnabled('DOCTOR_APPOINTMENT_REASSIGNED')) {
                WhatsappAutoSendSetting::logSkipped(
                    'DOCTOR_APPOINTMENT_REASSIGNED',
                    $item['appointment_no'],
                    $item['patient_mobile_no'],
                    $item['patient_name']
                );
                continue;
            }

            $mobile = '91' . preg_replace('/\D/', '', $item['patient_mobile_no']);
            $sent = false;
            $responseBody = null;

            try {

                $response = $this->wati->sendTemplateMessage(
                    $mobile,
                    'doctor_schedule_change',
                    'doctor_schedule_change_notify',
                    [
                        ['name' => 'first_name', 'value' => $item['patient_name']],
                        ['name' => 'doctor_name', 'value' => preg_replace('/^dr\.?\s+/i', '', optional($doctor)->doctor_name ?? '')],
                        ['name' => 'visit_date_old', 'value' => Carbon::parse($item['old_date'])->format('d-m-Y')],
                        ['name' => 'visit_time_old', 'value' => $item['old_time']],
                        ['name' => 'visit_date_new', 'value' => Carbon::parse($item['new_date'])->format('d-m-Y')],
                        ['name' => 'visit_time_new', 'value' => $item['new_time']],
                    ]
                );

                $sent = is_array($response) && ($response['result'] ?? false) === true;
                $responseBody = json_encode($response);

            } catch (\Exception $e) {

                Log::error('Doctor Appointment Reassignment WhatsApp Failed: ' . $e->getMessage());
                $responseBody = $e->getMessage();
            }

            DB::table('whatsapp_message_logs')->insert([
                'invoice_no' => $item['appointment_no'],
                'mobile_no' => $item['patient_mobile_no'],
                'patient_name' => $item['patient_name'],
                'message_type' => 'DOCTOR_APPOINTMENT_REASSIGNED',
                'status' => $sent ? 'SENT' : 'FAILED',
                'response' => $responseBody,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($sent) {
                $notified++;
            }
        }

        return $notified;
    }
}
