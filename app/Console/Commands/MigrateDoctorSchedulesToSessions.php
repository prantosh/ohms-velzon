<?php

namespace App\Console\Commands;

use App\Models\DoctorSchedule;
use App\Models\DoctorScheduleSession;
use Illuminate\Console\Command;

class MigrateDoctorSchedulesToSessions extends Command
{
    protected $signature = 'schedules:migrate-to-sessions';

    protected $description = 'One-off, idempotent: convert doctor_schedules wide day columns into doctor_schedule_sessions rows';

    private const DAYS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    public function handle(): int
    {
        $schedules = DoctorSchedule::all();

        $created = 0;
        $skippedExisting = 0;
        $skippedEmpty = 0;

        foreach ($schedules as $schedule) {

            foreach (self::DAYS as $day) {

                $startField = $day . '_start';
                $endField = $day . '_end';

                $start = $schedule->$startField;
                $end = $schedule->$endField;

                if (empty($start) || empty($end)) {
                    $skippedEmpty++;
                    continue;
                }

                $alreadyExists = DoctorScheduleSession::where('doctor_schedule_id', $schedule->id)
                    ->where('day_of_week', $day)
                    ->where('start_time', $start)
                    ->where('end_time', $end)
                    ->exists();

                if ($alreadyExists) {
                    $skippedExisting++;
                    continue;
                }

                DoctorScheduleSession::create([
                    'doctor_schedule_id' => $schedule->id,
                    'day_of_week' => $day,
                    'session_label' => null,
                    'start_time' => $start,
                    'end_time' => $end,
                    'slot_duration_minutes' => 120,
                    'max_patient' => $schedule->max_patient ?: 5,
                    'status' => $schedule->status,
                    'created_by' => $schedule->created_by,
                ]);

                $created++;
            }
        }

        $this->info("Doctor schedules scanned: {$schedules->count()}");
        $this->info("Sessions created: {$created}");
        $this->info("Skipped (already migrated): {$skippedExisting}");
        $this->info("Skipped (empty day): {$skippedEmpty}");

        return self::SUCCESS;
    }
}
