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
        Schema::create('patient_cards', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | CARD DETAILS
            |--------------------------------------------------------------------------
            */

            $table->string('card_no')
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | PATIENT NAMES
            |--------------------------------------------------------------------------
            */

            $table->string('patient_name1')
                ->nullable();

            $table->string('patient_name2')
                ->nullable();

            $table->string('patient_name3')
                ->nullable();

            $table->string('patient_name4')
                ->nullable();

            $table->string('patient_name5')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            $table->enum(
                'active',
                ['Yes', 'No']
            )->default('Yes');

            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            $table->unsignedBigInteger('created_by')
                ->nullable();

            $table->unsignedBigInteger('updated_by')
                ->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_cards');
    }
};
