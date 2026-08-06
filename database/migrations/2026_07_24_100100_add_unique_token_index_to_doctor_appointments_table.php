<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Concurrency backstop: two simultaneous bookings for the same doctor+date
     * could otherwise both compute the same token_no from a read-count-then-insert
     * race. This makes the DB reject the second insert outright instead of
     * silently issuing a duplicate token/appointment_no.
     */
    public function up(): void
    {
        Schema::table('doctor_appointments', function (Blueprint $table) {
            $table->unique(['doctor_id', 'appointment_date', 'token_no'], 'doc_appts_doctor_date_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('doctor_appointments', function (Blueprint $table) {
            $table->dropUnique('doc_appts_doctor_date_token_unique');
        });
    }
};
