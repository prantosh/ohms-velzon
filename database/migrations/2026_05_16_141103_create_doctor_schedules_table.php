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
        Schema::create('doctor_schedules', function (Blueprint $table) {

            $table->id();

            $table->foreignId('doctor_id')
                ->unique()
                ->constrained('doctors')
                ->onDelete('cascade');

            $table->time('monday_start')->nullable();
            $table->time('monday_end')->nullable();

            $table->time('tuesday_start')->nullable();
            $table->time('tuesday_end')->nullable();

            $table->time('wednesday_start')->nullable();
            $table->time('wednesday_end')->nullable();

            $table->time('thursday_start')->nullable();
            $table->time('thursday_end')->nullable();

            $table->time('friday_start')->nullable();
            $table->time('friday_end')->nullable();

            $table->time('saturday_start')->nullable();
            $table->time('saturday_end')->nullable();

            $table->time('sunday_start')->nullable();
            $table->time('sunday_end')->nullable();

            $table->tinyInteger('status')->default(1);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_schedules');
    }
};
