<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(
            'doctor_visit_invoices',

            function (Blueprint $table) {

                $table->id();

                /*
                |--------------------------------------------------------------------------
                | BASIC DETAILS
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'invoice_no',
                    30
                )->unique();

                $table->unsignedBigInteger(
                    'appointment_id'
                );

                $table->string(
                    'appointment_no',
                    30
                )->nullable();

                $table->unsignedBigInteger(
                    'doctor_id'
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | PATIENT DETAILS
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'patient_id',
                    25
                )->nullable();

                $table->string(
                    'patient_name'
                )->nullable();

                $table->string(
                    'patient_mobile_no',
                    15
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | BILLING
                |--------------------------------------------------------------------------
                */

                $table->date(
                    'invoice_date'
                )->nullable();

                $table->decimal(
                    'consultation_fee',
                    10,
                    2
                )->default(0);

                $table->decimal(
                    'discount',
                    10,
                    2
                )->default(0);

                $table->decimal(
                    'total_amount',
                    10,
                    2
                )->default(0);

                $table->decimal(
                    'paid_amount',
                    10,
                    2
                )->default(0);

                $table->decimal(
                    'due_amount',
                    10,
                    2
                )->default(0);

                /*
                |--------------------------------------------------------------------------
                | PAYMENT
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'payment_mode',
                    50
                )->nullable();

                $table->text(
                    'remarks'
                )->nullable();

                $table->string(
                    'status',
                    20
                )->default('Paid');

                /*
                |--------------------------------------------------------------------------
                | AUDIT
                |--------------------------------------------------------------------------
                */

                $table->unsignedBigInteger(
                    'created_by'
                )->nullable();

                $table->unsignedBigInteger(
                    'updated_by'
                )->nullable();

                $table->timestamps();

                /*
                |--------------------------------------------------------------------------
                | INDEXES
                |--------------------------------------------------------------------------
                */

                $table->index(
                    'appointment_id'
                );

                $table->index(
                    'doctor_id'
                );

                $table->index(
                    'patient_mobile_no'
                );

                $table->index(
                    'invoice_date'
                );

                /*
                |--------------------------------------------------------------------------
                | FOREIGN KEYS
                |--------------------------------------------------------------------------
                */

                $table->foreign('appointment_id')
                    ->references('id')
                    ->on('doctor_appointments')
                    ->onDelete('cascade');

                $table->foreign('doctor_id')
                    ->references('id')
                    ->on('doctors')
                    ->onDelete('set null');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'doctor_visit_invoices'
        );
    }
};
