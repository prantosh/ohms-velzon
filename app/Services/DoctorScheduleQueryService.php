<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\DoctorAppointment;
use App\Models\DoctorSchedule;
use App\Models\DoctorScheduleException;
use App\Models\DoctorScheduleSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only doctor schedule/availability lookups shared by the internal
 * staff booking screen and the public patient booking page. Contains no
 * PII and no auth-dependent branching, so it's safe to share between both
 * entry points without either depending on the other's routes/controller.
 */
class DoctorScheduleQueryService
{
    public function getDoctorsBySpecialisation(int $specialisationId): Collection
    {
        $doctors = Doctor::with('specialisation')
            ->where('specialisation_code', $specialisationId)
            ->where('status', 1)
            ->where('doctor_name', 'like', 'Dr.%')
            ->get();

        $schedules = DoctorSchedule::with(['sessions' => function ($query) {
            $query->where('status', 1);
        }])
            ->whereIn('doctor_id', $doctors->pluck('id'))
            ->where('status', 1)
            ->get()
            ->keyBy('doctor_id');

        $doctors->each(function ($doctor) use ($schedules) {

            $schedule = $schedules->get($doctor->id);

            $doctor->schedule_summary = $schedule
                ? $this->formatScheduleSummary($schedule->sessions)
                : '';
        });

        return $doctors;
    }

    /**
     * $excludeByAppointment: the public patient booking flow must not offer
     * a "By Appointment" day/slot at all (real booking always requires a
     * concrete time there); the internal staff screen keeps using every
     * session, including "By Appointment" ones, exactly as before -- staff
     * still pick from the doctor's real configured time slots for those.
     */
    public function getAvailableDays(int $doctorId, bool $excludeByAppointment = false): Collection
    {
        $schedule = DoctorSchedule::where('doctor_id', $doctorId)->first();

        if (!$schedule || (int) $schedule->status !== 1) {
            return collect();
        }

        $query = DoctorScheduleSession::where('doctor_schedule_id', $schedule->id)
            ->where('status', 1);

        if ($excludeByAppointment) {
            $query->where('is_by_appointment', false);
        }

        return $query
            ->distinct()
            ->pluck('day_of_week')
            ->map(fn ($day) => ucfirst($day))
            ->values();
    }

    public function getAvailableSlots(int $doctorId, string $date, bool $excludeByAppointment = false): array
    {
        // A blackout date (doctor sits on this weekday most weeks, but not
        // this specific date) overrides the recurring schedule entirely --
        // no slots at all, regardless of what sessions normally run on
        // that weekday.
        $isBlackedOut = DoctorScheduleException::where('doctor_id', $doctorId)
            ->whereDate('exception_date', $date)
            ->exists();

        if ($isBlackedOut) {
            return [];
        }

        $day = strtolower(Carbon::parse($date)->format('l'));

        $doctorSchedule = DoctorSchedule::where('doctor_id', $doctorId)->first();

        // doctor_schedules.status is a whole-doctor kill switch (e.g. on leave).
        if (!$doctorSchedule || (int) $doctorSchedule->status !== 1) {
            return [];
        }

        $sessionsQuery = DoctorScheduleSession::where('doctor_schedule_id', $doctorSchedule->id)
            ->where('day_of_week', $day)
            ->where('status', 1);

        if ($excludeByAppointment) {
            $sessionsQuery->where('is_by_appointment', false);
        }

        $sessions = $sessionsQuery->orderBy('start_time')->get();

        $availableSlots = [];

        foreach ($sessions as $session) {

            $start = Carbon::parse($session->start_time);
            $end = Carbon::parse($session->end_time);

            while ($start < $end) {

                $slotValue = $start->format('H:i:s');

                $slotDisplay = $start->format('h:i A') . ' - ' .
                    $start->copy()->addMinutes($session->slot_duration_minutes)->format('h:i A') .
                    ($session->session_label ? " ({$session->session_label})" : '');

                $bookingCount = DoctorAppointment::where('doctor_id', $doctorId)
                    ->where('doctor_schedule_session_id', $session->id)
                    ->where('appointment_date', $date)
                    ->where('appointment_time', $slotValue)
                    ->count();

                if ($bookingCount < $session->max_patient) {

                    $availableSlots[] = [
                        'display' => $slotDisplay,
                        'value' => $slotValue,
                        'session_id' => $session->id,
                    ];
                }

                $start->addMinutes($session->slot_duration_minutes);
            }
        }

        return $availableSlots;
    }

    /**
     * Packed into at most 2 lines total (joined with <br>), regardless of
     * how many days the doctor works -- one-row-per-day meant a doctor
     * working 2 days produced a much shorter block than one working 6,
     * which pushed the "Book Appointment" button to a different height on
     * every card in the same row. Both doctor-card render sites insert
     * this value as raw HTML already (no escaping), and their wrapping <p>
     * reserves the same 2-line height regardless of how many lines are
     * actually used, so every card's button ends up at the same height.
     */
    private function formatScheduleSummary($sessions): string
    {
        $dayOrder = [
            'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
            'thursday' => 4, 'friday' => 5, 'saturday' => 6,
        ];

        $dayEntries = $sessions
            ->groupBy('day_of_week')
            ->sortBy(fn ($daySessions, $day) => $dayOrder[$day] ?? 7)
            ->map(function ($daySessions, $day) {

                $times = $daySessions
                    ->sortBy('start_time')
                    ->map(fn ($session) => $session->is_by_appointment
                        ? 'By Appointment'
                        : Carbon::parse($session->start_time)->format('h:i A'))
                    ->implode(', ');

                return '<b>' . ucfirst($day) . ':</b> ' . $times;
            })
            ->values();

        $perLine = (int) ceil($dayEntries->count() / 2);

        return $dayEntries
            ->chunk(max($perLine, 1))
            ->map(fn ($chunk) => $chunk->implode(' &nbsp;|&nbsp; '))
            ->implode('<br>');
    }
}
