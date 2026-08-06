<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * is_by_appointment (added by the previous migration) turned out to be a
 * display/public-booking-eligibility flag only, not a "no time" flag --
 * every session, "By Appointment" or not, always carries a real time and
 * is always used for internal staff booking. Restoring NOT NULL here so
 * the schema actually enforces that, instead of relying on app-level
 * validation alone. Confirmed zero null-time rows before running this.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE doctor_schedule_sessions MODIFY start_time TIME NOT NULL');
        DB::statement('ALTER TABLE doctor_schedule_sessions MODIFY end_time TIME NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE doctor_schedule_sessions MODIFY start_time TIME NULL');
        DB::statement('ALTER TABLE doctor_schedule_sessions MODIFY end_time TIME NULL');
    }
};
