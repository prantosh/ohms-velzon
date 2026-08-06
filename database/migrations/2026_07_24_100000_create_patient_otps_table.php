<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_otps', function (Blueprint $table) {
            $table->id();

            $table->string('mobile_no', 15);
            $table->string('otp_code_hash', 64);
            $table->string('purpose', 50)->default('appointment_booking');
            $table->string('verification_token', 40)->nullable();

            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();

            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamps();

            $table->index(['mobile_no', 'purpose', 'created_at']);
            $table->index('verification_token');

            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_otps');
    }
};
