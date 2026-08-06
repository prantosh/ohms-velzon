<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Some doctors have a normal recurring weekly schedule (day_of_week + time,
 * via doctor_schedule_sessions) but in practice only actually sit once or
 * twice a month. This table lets staff mark specific CALENDAR DATES as
 * unavailable for a doctor -- an exception that overrides the recurring
 * schedule for that date only, blocking new appointment bookings on it
 * (see DoctorScheduleQueryService::getAvailableSlots() and
 * AppointmentBookingService::book()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_schedule_exceptions', function (Blueprint $table) {

            $table->id();

            // Plain indexed column, not a real FK constraint: doctors is
            // MyISAM, and InnoDB cannot declare a FK referencing a MyISAM
            // table -- same reason doctor_schedule_sessions.doctor_schedule_id
            // isn't a real FK either. Cascade-delete (if ever needed) would
            // have to be enforced at the application level.
            $table->unsignedBigInteger('doctor_id');

            $table->date('exception_date');
            $table->string('reason', 255)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->unique(['doctor_id', 'exception_date']);
            $table->index('doctor_id');

            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_schedule_exceptions');
    }
};
