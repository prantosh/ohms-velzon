<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('doctor_test_payments', function (Blueprint $table) {

            $table->id();

            $table->string('invoice_no', 50);

            $table->string('patient_id', 50)
                ->nullable();

            $table->string('doctor_code', 20)
                ->nullable();

            $table->string('doctor_name', 150)
                ->nullable();

            $table->string('item_code_sub', 20)
                ->nullable();

            $table->string('test_name', 255)
                ->nullable();

            $table->decimal('test_amount', 10, 2)
                ->default(0);

            $table->string('payment_type', 20)
                ->nullable();

            $table->decimal('payment_rate', 10, 2)
                ->default(0);

            $table->decimal('payable_amount', 10, 2)
                ->default(0);

            $table->enum(
                'payment_status',
                ['PENDING', 'PAID']
            )->default('PENDING');

            $table->date('payment_date')
                ->nullable();

            $table->text('remarks')
                ->nullable();

            $table->unsignedBigInteger('created_by')
                ->nullable();

            $table->unsignedBigInteger('updated_by')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */

            $table->index('invoice_no');
            $table->index('patient_id');
            $table->index('doctor_code');
            $table->index('item_code_sub');
            $table->index('payment_status');
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'doctor_test_payments'
        );
    }
};
