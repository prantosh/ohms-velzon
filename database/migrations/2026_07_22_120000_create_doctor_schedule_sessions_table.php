<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_schedule_sessions', function (Blueprint $table) {
            $table->id();

            // Plain indexed column, not a real FK constraint: doctor_schedules is
            // MyISAM, and InnoDB cannot declare a FK referencing a MyISAM table
            // (errors with "Failed to open the referenced table", not a silent
            // no-op). Cascade-delete is enforced at the application level instead
            // (DoctorScheduleController::destroy()).
            $table->unsignedBigInteger('doctor_schedule_id');

            $table->string('day_of_week', 10);
            $table->string('session_label', 50)->nullable();

            $table->time('start_time');
            $table->time('end_time');

            $table->unsignedSmallInteger('slot_duration_minutes')->default(120);
            $table->unsignedInteger('max_patient');

            $table->tinyInteger('status')->default(1);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->index(['doctor_schedule_id', 'day_of_week']);

            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_schedule_sessions');
    }
};
