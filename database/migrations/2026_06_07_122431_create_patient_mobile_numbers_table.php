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
        Schema::create('patient_mobile_numbers', function (Blueprint $table) {

            $table->id();

            $table->string('patient_id', 25);

            $table->string('mobile_no', 15);

            $table->char('is_primary', 1)
                ->default('N');

            $table->string('remarks')
                ->nullable();

            $table->timestamps();

            $table->index('patient_id');
            $table->index('mobile_no');

            $table->foreign('patient_id')
                ->references('patient_id')
                ->on('patients')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_mobile_numbers');
    }
};
