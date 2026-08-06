<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_appointments', function (Blueprint $table) {
            $table->foreignId('doctor_schedule_session_id')
                ->nullable()
                ->after('doctor_id')
                ->constrained('doctor_schedule_sessions')
                ->nullOnDelete();

            $table->index(['doctor_schedule_session_id', 'appointment_date'], 'doc_appts_session_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('doctor_appointments', function (Blueprint $table) {
            $table->dropForeign(['doctor_schedule_session_id']);
            $table->dropIndex('doc_appts_session_date_idx');
            $table->dropColumn('doctor_schedule_session_id');
        });
    }
};
