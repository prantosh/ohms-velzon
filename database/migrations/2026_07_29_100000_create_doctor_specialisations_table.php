<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Lets a doctor be assigned more than one specialisation. doctors.specialisation
 * / specialisation_code stay as-is and continue to drive existing single-value
 * screens (public booking search, schedule calendar filter, payment master,
 * history report) -- they represent the doctor's PRIMARY specialisation. This
 * pivot is additive: every doctor's existing specialisation_code is backfilled
 * here as their first entry, so nothing existing changes behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_specialisations', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('specialisation_id');

            $table->timestamps();

            $table->unique(['doctor_id', 'specialisation_id']);

            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('cascade');
            $table->foreign('specialisation_id')->references('id')->on('specialisations')->onDelete('cascade');
        });

        DB::table('doctors')
            ->whereNotNull('specialisation_code')
            ->select('id', 'specialisation_code')
            ->orderBy('id')
            ->each(function ($doctor) {

                DB::table('doctor_specialisations')->insertOrIgnore([
                    'doctor_id' => $doctor->id,
                    'specialisation_id' => $doctor->specialisation_code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_specialisations');
    }
};
