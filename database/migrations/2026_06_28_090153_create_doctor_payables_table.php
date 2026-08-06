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
        Schema::create('doctor_payables', function (Blueprint $table) {

            $table->id();

            //==========================================================
            // Payable Information
            //==========================================================

            $table->string('payable_no', 30)->unique();

            //==========================================================
            // Invoice Information
            //==========================================================

            $table->unsignedBigInteger('invoice_id')->nullable();

            $table->string('invoice_no', 30);

            $table->enum('invoice_type', [
                'DOCTOR_VISIT',
                'DIAGNOSTIC'
            ]);

            $table->enum('transaction_type', [
                'CONSULTATION',
                'DIAGNOSTIC_TEST'
            ]);

            //==========================================================
            // Doctor
            //==========================================================

            $table->unsignedBigInteger('doctor_id')->nullable();

            $table->string('doctor_code', 20)->nullable();

            $table->string('doctor_name', 150);

            //==========================================================
            // Patient
            //==========================================================

            $table->string('patient_id', 30)->nullable();

            $table->string('patient_name', 150);

            //==========================================================
            // Service/Test
            //==========================================================

            $table->string('item_code', 20)->nullable();

            $table->string('item_code_sub', 20)->nullable();

            $table->string('item_description', 255)->nullable();

            //==========================================================
            // Financial
            //==========================================================

            $table->decimal('gross_amount', 12, 2)->default(0);

            $table->string('payment_rule', 50)->nullable();

            $table->decimal('payment_rate', 12, 2)->default(0);

            $table->decimal('payable_amount', 12, 2)->default(0);

            //==========================================================
            // Settlement
            //==========================================================

            $table->enum('payment_status', [
                'PENDING',
                'APPROVED',
                'PAID',
                'CANCELLED'
            ])->default('PENDING');

            $table->string('payment_batch_no', 30)->nullable();

            $table->decimal('paid_amount', 12, 2)->default(0);

            $table->date('paid_date')->nullable();

            //==========================================================
            // Remarks
            //==========================================================

            $table->text('remarks')->nullable();

            //==========================================================
            // Audit
            //==========================================================

            $table->unsignedBigInteger('created_by')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            //==========================================================
            // Indexes
            //==========================================================

            $table->index('invoice_no');
            $table->index('doctor_id');
            $table->index('patient_id');
            $table->index('invoice_type');
            $table->index('transaction_type');
            $table->index('payment_status');
            $table->index('payment_batch_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_payables');
    }
};
