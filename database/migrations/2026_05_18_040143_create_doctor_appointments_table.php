<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('doctor_appointments', function (Blueprint $table) {
            $table->id();

            $table->string('appointment_no')->unique();

            $table->unsignedBigInteger('doctor_id');

            $table->string('patient_name');
            $table->string('patient_mobile_no', 15);
            $table->integer('patient_age')->nullable();
            $table->enum('patient_gender', ['M', 'F', 'O'])->nullable();

            $table->date('appointment_date');
            $table->time('appointment_time');
            $table->integer('token_no');

            $table->decimal('consultation_fee', 10, 2)->default(0);

            $table->enum('appointment_status', [
                'Booked',
                'Completed',
                'Cancelled',
                'No Show'
            ])->default('Booked');

            $table->text('remarks')->nullable();

            $table->tinyInteger('status')->default(1);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->foreign('doctor_id')->references('id')->on('doctors');

            $table->index(['doctor_id', 'appointment_date']);
            $table->index(['appointment_date', 'appointment_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_appointments');
    }
};
