<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Lets a session represent "By Appointment" -- a doctor assigned to a day
 * with no fixed time slot. start_time/end_time become nullable (null =
 * by-appointment); doctrine/dbal isn't installed so the nullability change
 * uses a raw MODIFY statement instead of Schema::table()->change().
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE doctor_schedule_sessions MODIFY start_time TIME NULL');
        DB::statement('ALTER TABLE doctor_schedule_sessions MODIFY end_time TIME NULL');

        Schema::table('doctor_schedule_sessions', function ($table) {
            $table->boolean('is_by_appointment')->default(false)->after('end_time');
        });
    }

    public function down(): void
    {
        Schema::table('doctor_schedule_sessions', function ($table) {
            $table->dropColumn('is_by_appointment');
        });

        DB::statement('ALTER TABLE doctor_schedule_sessions MODIFY start_time TIME NOT NULL');
        DB::statement('ALTER TABLE doctor_schedule_sessions MODIFY end_time TIME NOT NULL');
    }
};
