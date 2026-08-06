<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

/**
 * Public "OPD Schedule" reference -- no auth, no PII beyond what's already
 * shown on the public booking page (doctor name/specialisation/timing).
 * Meant to be linked directly from the marketing website or shared as a
 * plain URL, same spirit as the public appointment booking page.
 */
class PublicDoctorScheduleController extends Controller
{
    private const DAY_ORDER = [
        'monday' => 0, 'tuesday' => 1, 'wednesday' => 2, 'thursday' => 3,
        'friday' => 4, 'saturday' => 5, 'sunday' => 6,
    ];

    public function pdf()
    {
        // NOTE: Doctor::specialisation() (the relation to the specialisations
        // master) is unreachable via ->specialisation -- doctors.specialisation
        // is itself a plain string column with the same name, and Eloquent's
        // magic accessor resolves the real column before the relation method.
        // Using that column directly is simpler and avoids the collision.
        $doctors = Doctor::where('status', 1)
            ->where('doctor_name', 'like', 'Dr.%')
            ->orderBy('doctor_name')
            ->get();

        $schedules = DoctorSchedule::with(['sessions' => function ($query) {
            $query->where('status', 1);
        }])
            ->whereIn('doctor_id', $doctors->pluck('id'))
            ->where('status', 1)
            ->get()
            ->keyBy('doctor_id');

        $doctors = $doctors
            ->map(function ($doctor) use ($schedules) {

                $doctor->sessions_by_day = optional($schedules->get($doctor->id))->sessions
                    ?? collect();

                return $doctor;
            })
            ->filter(fn ($doctor) => $doctor->sessions_by_day->isNotEmpty())
            // USG doctors are not shown on this public schedule.
            ->filter(fn ($doctor) => strtoupper(trim($doctor->specialisation ?? '')) !== 'USG')
            ->values();

        $groups = $doctors
            ->groupBy(fn ($doctor) => $doctor->specialisation ?: 'Other')
            ->sortKeys()
            ->map(function ($doctorsInGroup) {

                // One weekly calendar per specialisation: day => [entries].
                $days = array_fill_keys(array_keys(self::DAY_ORDER), []);

                foreach ($doctorsInGroup as $doctor) {

                    foreach ($doctor->sessions_by_day as $session) {

                        $day = strtolower($session->day_of_week);

                        if (!isset($days[$day])) {
                            continue;
                        }

                        $days[$day][] = [
                            'doctor_name' => $doctor->doctor_name,
                            // "By Appointment" is a display flag only -- the real time is
                            // always present (used for internal staff booking) but this
                            // public schedule deliberately shows the label instead of it.
                            'time' => $session->is_by_appointment
                                ? 'By Appointment'
                                : Carbon::parse($session->start_time)->format('h:i A') .
                                    ' - ' .
                                    Carbon::parse($session->end_time)->format('h:i A'),
                            'sort_key' => $session->start_time,
                        ];
                    }
                }

                foreach ($days as $day => $entries) {
                    usort($entries, fn ($a, $b) => $a['sort_key'] <=> $b['sort_key']);
                    $days[$day] = $entries;
                }

                return $days;
            });

        $pdf = Pdf::loadView(
            'apps-public-doctor-schedule-pdf',
            [
                'groups' => $groups,
                'dayOrder' => array_keys(self::DAY_ORDER),
                'generatedOn' => Carbon::now()->format('d-m-Y h:i A'),
            ]
        );

        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream('Doctor-Schedule.pdf');
    }
}
