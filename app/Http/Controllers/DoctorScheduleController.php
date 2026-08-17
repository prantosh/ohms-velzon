<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DoctorSchedule;
use App\Models\DoctorScheduleSession;
use App\Models\Doctor;
use App\Models\DoctorAppointment;
use App\Models\WhatsappAutoSendSetting;
use App\Services\WatiService;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
class DoctorScheduleController extends Controller
{
    private const MODULE_CODE = 'DOCTOR_SCHEDULE';

    private const DAYS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    /*
    |--------------------------------------------------------------------------
    | SCHEDULE PAGE
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $doctors = Doctor::orderBy('doctor_name')
            ->get();

        return view(
            'apps-ecommerce-doctor-schedules',
            compact('doctors')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | AJAX LIST
    |--------------------------------------------------------------------------
    */

    public function getSchedules(Request $request)
    {
        try {

            $schedules = DoctorSchedule::with(['doctor.specialisation', 'sessions'])
                ->orderBy('id', 'desc')
                ->get();

            // Doctor::specialisation() (the relation) and doctors.specialisation (the
            // free-text column) share the same name -- when serialized to JSON, the
            // eager-loaded relation silently overwrites the text column. Doctors whose
            // specialisation_code is missing/stale (a real data gap, e.g. Papiya Das)
            // would then show a blank specialisation even though the text column has
            // a value. Expose an explicit, unambiguous field instead of relying on the
            // colliding key.
            $schedules->each(function ($schedule) {
                if ($schedule->doctor) {
                    // ->getRelation() reads the eager-loaded relation object directly,
                    // bypassing the attribute-vs-relation name collision that plain
                    // property access (->specialisation) would hit.
                    $schedule->doctor->specialisation_display =
                        optional($schedule->doctor->getRelation('specialisation'))->category
                        ?: $schedule->doctor->getRawOriginal('specialisation');
                }
            });

            return response()->json([
                'status' => true,
                'data' => $schedules
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function calendar()
    {
        $doctors = Doctor::where('status', 1)
            ->where('doctor_name', 'like', 'Dr.%')
            ->orderBy('doctor_name')
            ->get(['id', 'doctor_name']);

        $specialisations = \App\Models\Specialisation::orderBy('category')->get(['id', 'category']);

        return view('doctor-schedule-calendar', compact('doctors', 'specialisations'));
    }

    public function calendarEvents(Request $request)
    {
        $appointmentCounts = DB::table('doctor_appointments')
            ->select(
                'doctor_schedule_session_id',
                DB::raw('COUNT(*) as total')
            )
            ->whereNotNull('doctor_schedule_session_id')
            ->groupBy('doctor_schedule_session_id')
            ->get()
            ->keyBy('doctor_schedule_session_id');

        $sessionsQuery = DoctorScheduleSession::with('schedule.doctor')
            ->where('status', 1);

        if ($request->filled('doctor_id')) {

            $sessionsQuery->whereHas('schedule', function ($query) use ($request) {
                $query->where('doctor_id', $request->doctor_id);
            });
        }

        if ($request->filled('specialisation_code')) {

            $sessionsQuery->whereHas('schedule.doctor', function ($query) use ($request) {
                $query->where('specialisation_code', $request->specialisation_code);
            });
        }

        if ($request->filled('day_of_week')) {

            $sessionsQuery->where('day_of_week', strtolower($request->day_of_week));
        }

        $sessions = $sessionsQuery->get();

        $events = [];

        $dayNumbers = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];

        $colors = [
            '#0d6efd',
            '#198754',
            '#dc3545',
            '#fd7e14',
            '#6f42c1',
            '#20c997',
            '#d63384',
            '#6610f2',
            '#198754',
            '#0dcaf0'
        ];

        foreach ($sessions as $session) {

            $schedule = $session->schedule;

            // doctor_schedules.status is a whole-doctor kill switch (e.g. on
            // leave) that suspends every session at once, independent of any
            // individual session's own status.
            if (!$schedule || (int) $schedule->status !== 1) {
                continue;
            }

            $doctor = $schedule->doctor;

            if (!$doctor) {
                continue;
            }

            $dayNumber = $dayNumbers[$session->day_of_week] ?? null;

            if ($dayNumber === null) {
                continue;
            }

            $color = $colors[$doctor->id % count($colors)];

            $totalAppointments = optional($appointmentCounts->get($session->id))->total ?? 0;

            $events[] = [

                'title' =>
                    $doctor->doctor_name .
                    ($session->is_by_appointment ? ' (By Appointment)' : '') .
                    ($session->session_label ? " ({$session->session_label})" : ''),

                'daysOfWeek' => [$dayNumber],

                'startTime' => substr($session->start_time, 0, 5),

                // Displayed box is a fixed-size marker at the start time, not
                // stretched to the session's real duration -- a 2-3 hour
                // session used to render as a tall block that crowded the
                // calendar. The real end_time is still available below in
                // extendedProps for the click popup.
                'endTime' => Carbon::parse($session->start_time)->addMinutes(30)->format('H:i'),

                'backgroundColor' => $color,

                'borderColor' => $color,

                'extendedProps' => [

                    'doctor' => $doctor->doctor_name,

                    'specialisation' => $doctor->specialisation,

                    'session_label' => $session->session_label,

                    // "By Appointment" is a display flag only -- the real
                    // time is always present and used for internal staff
                    // booking; the calendar tile/popup just suppress showing
                    // it (see the blade's use of this flag).
                    'is_by_appointment' => (bool) $session->is_by_appointment,

                    'start_time' => substr($session->start_time, 0, 5),

                    'end_time' => substr($session->end_time, 0, 5),

                    'appointments' => $totalAppointments,

                    'max_patient' => $session->max_patient,

                    'booking_status' =>
                        $totalAppointments . ' / ' . $session->max_patient,

                    'day' => ucfirst($session->day_of_week)
                ]
            ];
        }

        return response()->json($events);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE / UPDATE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, AuditService $auditService)
    {
        return $this->saveSchedule($request, $auditService);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        return $this->saveSchedule($request, $auditService, $id);
    }

    private function saveSchedule(Request $request, AuditService $auditService, $id = null)
    {
        $request->validate([

            'doctor_id' => 'required|exists:doctors,id',
            'status' => 'nullable|in:0,1',

            'sessions' => 'nullable|array',
            'sessions.*.day_of_week' => 'required|in:' . implode(',', self::DAYS),
            'sessions.*.session_label' => 'nullable|string|max:50',
            // "By Appointment" is a display/public-booking-eligibility flag only --
            // every session still has a real, required time range, always used for
            // internal staff booking. See [[doctor_schedule_by_appointment]].
            'sessions.*.is_by_appointment' => 'nullable|boolean',
            'sessions.*.start_time' => 'required|date_format:H:i',
            'sessions.*.end_time' => 'required|date_format:H:i',
            'sessions.*.max_patient' => 'required|integer|min:1',
            'sessions.*.slot_duration_minutes' => 'nullable|integer|min:1',
        ]);

        $sessionsInput = $request->input('sessions', []);

        $this->validateSessionTimesAndOverlap($sessionsInput);

        $schedule = $id
            ? DoctorSchedule::findOrFail($id)
            : DoctorSchedule::where('doctor_id', $request->doctor_id)->first();

        $isNewSchedule = !$schedule;

        $oldScheduleData = $isNewSchedule ? null : $schedule->only($schedule->getFillable());

        $existingSessions = $isNewSchedule
            ? collect()
            : DoctorScheduleSession::where('doctor_schedule_id', $schedule->id)->get()->keyBy('id');

        $submittedIds = collect($sessionsInput)
            ->pluck('id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->all();

        $removedSessions = $existingSessions->reject(
            fn ($session) => in_array($session->id, $submittedIds, true)
        );

        // Validate deletions BEFORE writing anything -- don't silently
        // orphan a patient's future booking.
        foreach ($removedSessions as $removed) {

            $futureBooked = DoctorAppointment::where('doctor_schedule_session_id', $removed->id)
                ->where('appointment_status', 'Booked')
                ->where('appointment_date', '>=', Carbon::today()->toDateString())
                ->exists();

            if ($futureBooked) {
                throw ValidationException::withMessages([
                    'sessions' => 'Cannot remove the ' . ucfirst($removed->day_of_week) .
                        ' session (' . substr($removed->start_time, 0, 5) . '-' . substr($removed->end_time, 0, 5) .
                        ') -- it has future booked appointments. Reassign or cancel them first.',
                ]);
            }
        }

        $changedSessions = [];
        $rescheduleQueue = [];

        DB::transaction(function () use (
            $request, &$schedule, $isNewSchedule, $oldScheduleData, $sessionsInput,
            $existingSessions, $removedSessions, &$changedSessions, &$rescheduleQueue,
            $auditService
        ) {

            if ($isNewSchedule) {

                $schedule = new DoctorSchedule();
                $schedule->doctor_id = $request->doctor_id;
                $schedule->created_by = Auth::id();
            }

            $schedule->status = $request->status ?? 1;
            $schedule->updated_by = Auth::id();
            $schedule->save();

            if ($isNewSchedule) {
                $auditService->logCreate(
                    self::MODULE_CODE,
                    $schedule,
                    $schedule->only($schedule->getFillable()),
                    'Doctor schedule created'
                );
            } else {
                $auditService->logUpdate(
                    self::MODULE_CODE,
                    $schedule,
                    $oldScheduleData,
                    $schedule->only($schedule->getFillable()),
                    'Doctor schedule updated'
                );
            }

            foreach ($sessionsInput as $row) {

                $sessionId = isset($row['id']) ? (int) $row['id'] : null;

                $isByAppointment = filter_var($row['is_by_appointment'] ?? false, FILTER_VALIDATE_BOOLEAN);

                $startTime = $row['start_time'] . ':00';
                $endTime = $row['end_time'] . ':00';

                if ($sessionId && $existingSessions->has($sessionId)) {

                    $session = $existingSessions->get($sessionId);

                    $oldSessionData = $session->only($session->getFillable());

                    $oldStart = Carbon::parse($session->start_time);
                    $oldEnd = Carbon::parse($session->end_time);
                    $newStart = Carbon::parse($startTime);
                    $newEnd = Carbon::parse($endTime);

                    if (!$oldStart->eq($newStart) || !$oldEnd->eq($newEnd)) {

                        $changedSessions[$session->id] = [
                            'delta_minutes' => ($newStart->timestamp - $oldStart->timestamp) / 60,
                            'new_start' => $newStart,
                            'new_end' => $newEnd,
                        ];
                    }

                    $session->update([
                        'day_of_week' => $row['day_of_week'],
                        'session_label' => $row['session_label'] ?? null,
                        'is_by_appointment' => $isByAppointment,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'slot_duration_minutes' => $row['slot_duration_minutes'] ?? 120,
                        'max_patient' => $row['max_patient'],
                        'updated_by' => Auth::id(),
                    ]);

                    $auditService->logUpdate(
                        self::MODULE_CODE,
                        $session,
                        $oldSessionData,
                        $session->only($session->getFillable()),
                        'Doctor schedule session updated'
                    );

                } else {

                    $newSession = DoctorScheduleSession::create([
                        'doctor_schedule_id' => $schedule->id,
                        'day_of_week' => $row['day_of_week'],
                        'session_label' => $row['session_label'] ?? null,
                        'is_by_appointment' => $isByAppointment,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'slot_duration_minutes' => $row['slot_duration_minutes'] ?? 120,
                        'max_patient' => $row['max_patient'],
                        'status' => 1,
                        'created_by' => Auth::id(),
                    ]);

                    $auditService->logCreate(
                        self::MODULE_CODE,
                        $newSession,
                        $newSession->only($newSession->getFillable()),
                        'Doctor schedule session created'
                    );
                }
            }

            foreach ($removedSessions as $removed) {

                $oldSessionData = $removed->only($removed->getFillable());

                $removed->delete();

                $auditService->logDelete(
                    self::MODULE_CODE,
                    $removed,
                    $oldSessionData,
                    'Doctor schedule session deleted'
                );
            }

            if (!empty($changedSessions)) {
                $rescheduleQueue = $this->rescheduleAffectedAppointments(
                    $schedule->doctor_id,
                    $changedSessions,
                    $auditService
                );
            }
        });

        $notifiedCount = 0;

        if (!empty($rescheduleQueue)) {

            $doctor = Doctor::find($schedule->doctor_id);

            foreach ($rescheduleQueue as $item) {

                if ($this->sendScheduleChangeWhatsapp($item, $doctor)) {
                    $notifiedCount++;
                }
            }
        }

        $message = $isNewSchedule ? 'Schedule added successfully' : 'Schedule updated successfully';

        if (!empty($rescheduleQueue)) {

            $message .=
                '. ' . count($rescheduleQueue) .
                ' appointment(s) rescheduled, ' .
                $notifiedCount .
                ' patient(s) notified via WhatsApp.';
        }

        return response()->json([

            'status' => true,

            'message' => $message
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SESSION VALIDATION (time order + per-day overlap)
    |--------------------------------------------------------------------------
    */

    private function validateSessionTimesAndOverlap(array $sessions): void
    {
        $byDay = [];

        foreach ($sessions as $row) {

            $day = $row['day_of_week'] ?? null;
            $start = $row['start_time'] ?? null;
            $end = $row['end_time'] ?? null;

            if ($start && $end && $start >= $end) {
                throw ValidationException::withMessages([
                    'sessions' => 'Invalid session on ' . ucfirst($day ?? '') . ': start time must be before end time.',
                ]);
            }

            if ($day) {
                $byDay[$day][] = ['start' => $start, 'end' => $end];
            }
        }

        foreach ($byDay as $day => $ranges) {

            usort($ranges, fn ($a, $b) => strcmp($a['start'], $b['start']));

            for ($i = 1; $i < count($ranges); $i++) {

                if ($ranges[$i]['start'] < $ranges[$i - 1]['end']) {

                    throw ValidationException::withMessages([
                        'sessions' => 'Overlapping sessions on ' . ucfirst($day) . ': ' .
                            $ranges[$i - 1]['start'] . '-' . $ranges[$i - 1]['end'] . ' and ' .
                            $ranges[$i]['start'] . '-' . $ranges[$i]['end'] . '.',
                    ]);
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RESCHEDULE APPOINTMENTS AFFECTED BY A SCHEDULE CHANGE
    |--------------------------------------------------------------------------
    | Keyed by doctor_schedule_session_id (not day name) -- a booked
    | appointment records exactly which session it belongs to, so a change
    | to one session on a day can never be misapplied to another session
    | on the same day.
    */

    private function rescheduleAffectedAppointments($doctorId, array $changedSessions, AuditService $auditService): array
    {
        $appointments = DoctorAppointment::where('doctor_id', $doctorId)
            ->whereIn('doctor_schedule_session_id', array_keys($changedSessions))
            ->where('appointment_status', 'Booked')
            ->where('appointment_date', '>=', Carbon::today()->toDateString())
            ->get();

        $queue = [];

        foreach ($appointments as $appointment) {

            $change = $changedSessions[$appointment->doctor_schedule_session_id];

            $oldApptTime = Carbon::parse($appointment->appointment_time);

            $newApptTime = $oldApptTime->copy()->addMinutes($change['delta_minutes']);

            if ($newApptTime->gt($change['new_end'])) {
                $newApptTime = $change['new_end']->copy();
            }

            if ($newApptTime->lt($change['new_start'])) {
                $newApptTime = $change['new_start']->copy();
            }

            $oldTimeDisplay = $oldApptTime->format('h:i A');
            $newTimeDisplay = $newApptTime->format('h:i A');

            $oldAppointmentData = $appointment->only($appointment->getFillable());

            $appointment->update([
                'appointment_time' => $newApptTime->format('H:i:s'),
                'updated_by' => Auth::id()
            ]);

            $auditService->logUpdate(
                'DOCTOR_APPOINTMENT',
                $appointment,
                $oldAppointmentData,
                $appointment->only($appointment->getFillable()),
                'Appointment rescheduled due to doctor schedule change'
            );

            $queue[] = [
                'appointment_no' => $appointment->appointment_no,
                'patient_name' => $appointment->patient_name,
                'patient_mobile_no' => $appointment->patient_mobile_no,
                'appointment_date' => $appointment->appointment_date,
                'old_time' => $oldTimeDisplay,
                'new_time' => $newTimeDisplay,
            ];
        }

        return $queue;
    }

    /*
    |--------------------------------------------------------------------------
    | WHATSAPP: SCHEDULE CHANGE NOTIFICATION
    |--------------------------------------------------------------------------
    */

    private function sendScheduleChangeWhatsapp(array $item, ?Doctor $doctor): bool
    {
        if (!WhatsappAutoSendSetting::isEnabled('DOCTOR_SCHEDULE_CHANGE')) {
            WhatsappAutoSendSetting::logSkipped(
                'DOCTOR_SCHEDULE_CHANGE',
                $item['appointment_no'],
                $item['patient_mobile_no'],
                $item['patient_name']
            );
            return false;
        }

        $sent = false;

        $responseBody = null;

        try {

            $mobile = '91' . preg_replace('/\D/', '', $item['patient_mobile_no']);

            $wati = new WatiService();

            $appointmentDateDisplay = Carbon::parse($item['appointment_date'])->format('d-m-Y');

            $response = $wati->sendTemplateMessage(
                $mobile,
                'doctor_schedule_change',
                'doctor_schedule_change_notify',
                [
                    ['name' => 'first_name', 'value' => $item['patient_name']],
                    ['name' => 'doctor_name', 'value' => preg_replace('/^dr\.?\s+/i', '', optional($doctor)->doctor_name ?? '')],
                    ['name' => 'visit_date_old', 'value' => $appointmentDateDisplay],
                    ['name' => 'visit_time_old', 'value' => $item['old_time']],
                    ['name' => 'visit_date_new', 'value' => $appointmentDateDisplay],
                    ['name' => 'visit_time_new', 'value' => $item['new_time']]
                ]
            );

            $sent = is_array($response) && ($response['result'] ?? false) === true;

            $responseBody = json_encode($response);

        } catch (\Exception $e) {

            Log::error('Doctor Schedule Change WhatsApp Failed: ' . $e->getMessage());

            $responseBody = $e->getMessage();
        }

        DB::table('whatsapp_message_logs')->insert([

            'invoice_no' => $item['appointment_no'],
            'mobile_no' => $item['patient_mobile_no'],
            'patient_name' => $item['patient_name'],
            'message_type' => 'DOCTOR_SCHEDULE_CHANGE',
            'status' => $sent ? 'SENT' : 'FAILED',
            'response' => $responseBody,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return $sent;
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function destroy($id, AuditService $auditService)
    {
        try {

            $schedule = DoctorSchedule::findOrFail($id);

            $sessionIds = DoctorScheduleSession::where('doctor_schedule_id', $schedule->id)->pluck('id');

            if ($sessionIds->isNotEmpty()) {

                $futureBooked = DoctorAppointment::whereIn('doctor_schedule_session_id', $sessionIds)
                    ->where('appointment_status', 'Booked')
                    ->where('appointment_date', '>=', Carbon::today()->toDateString())
                    ->exists();

                if ($futureBooked) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Cannot delete this schedule -- it has future booked appointments. Reassign or cancel them first.',
                    ], 422);
                }
            }

            $oldScheduleData = $schedule->only($schedule->getFillable());

            DB::transaction(function () use ($schedule, $sessionIds, $oldScheduleData, $auditService) {

                if ($sessionIds->isNotEmpty()) {

                    $sessions = DoctorScheduleSession::whereIn('id', $sessionIds)->get();

                    foreach ($sessions as $session) {

                        $oldSessionData = $session->only($session->getFillable());

                        $session->delete();

                        $auditService->logDelete(
                            self::MODULE_CODE,
                            $session,
                            $oldSessionData,
                            'Doctor schedule session deleted'
                        );
                    }
                }

                $schedule->delete();

                $auditService->logDelete(
                    self::MODULE_CODE,
                    $schedule,
                    $oldScheduleData,
                    'Doctor schedule deleted'
                );
            });

            return response()->json([

                'status' => true,

                'message' =>
                    'Schedule deleted successfully'
            ]);

        } catch (\Exception $e) {

            return response()->json([

                'status' => false,
                'message' => $e->getMessage()

            ], 500);
        }
    }
}
