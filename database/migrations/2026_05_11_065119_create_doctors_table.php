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
        Schema::create('doctors', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | BASIC INFORMATION
            |--------------------------------------------------------------------------
            */

            $table->string('doctor_code')
                ->unique();

            $table->string('doctor_name');

            $table->enum('gender', ['M', 'F', 'O'])
                ->nullable();

            $table->date('date_of_birth')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | CONTACT INFORMATION
            |--------------------------------------------------------------------------
            */

            $table->string('mobile_no', 15)
                ->nullable()
                ->unique();

            $table->string('alternate_mobile_no', 15)
                ->nullable();

            $table->string('email')
                ->nullable()
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | PROFESSIONAL DETAILS
            |--------------------------------------------------------------------------
            */

            $table->string('specialisation')
                ->nullable();

            $table->string('qualification')
                ->nullable();

            $table->integer('experience_years')
                ->nullable();

            $table->string('registration_no')
                ->nullable();

            $table->date('joining_date')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | FEES
            |--------------------------------------------------------------------------
            */

            $table->decimal('consultation_fee_total', 10, 2)
                ->default(0);

            $table->decimal('consultation_fee_doctor', 10, 2)
                ->default(0);

            $table->decimal('consultation_fee_doctor_discounted_patient', 10, 2)
                ->default(0);

            $table->decimal('discount', 10, 2)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | IMAGE
            |--------------------------------------------------------------------------
            */

            $table->string('doctor_image')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */
            $table->unique('mobile_no');
            $table->enum('status', ['Active', 'Inactive'])
                ->default('Active');

            /*
            |--------------------------------------------------------------------------
            | REMARKS
            |--------------------------------------------------------------------------
            */

            $table->text('remarks')
                ->nullable();


            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctors');
       
    }
};
