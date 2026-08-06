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
        Schema::create('doctor_settlement_items', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | REFERENCES
            |--------------------------------------------------------------------------
            */

            $table->unsignedBigInteger('settlement_id');

            $table->unsignedBigInteger('payable_id');

            $table->unsignedBigInteger('invoice_id');

            /*
            |--------------------------------------------------------------------------
            | SNAPSHOT DATA
            |--------------------------------------------------------------------------
            */

            $table->string('invoice_no', 30);

            $table->string('invoice_type', 30);

            $table->string('transaction_type', 30)->nullable();

            $table->string('patient_id', 30)->nullable();

            $table->string('patient_name', 150);

            $table->string('item_code', 50)->nullable();

            $table->string('item_description', 255);

            /*
            |--------------------------------------------------------------------------
            | PAYMENT DETAILS
            |--------------------------------------------------------------------------
            */

            $table->decimal('payable_amount', 12, 2)->default(0);

            $table->decimal('previous_paid_amount', 12, 2)->default(0);

            $table->decimal('balance_before_payment', 12, 2)->default(0);

            $table->decimal('payment_amount', 12, 2)->default(0);

            $table->decimal('balance_after_payment', 12, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | REMARKS
            |--------------------------------------------------------------------------
            */

            $table->string('remarks', 255)->nullable();

            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            $table->unsignedBigInteger('created_by');

            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */

            $table->index('settlement_id');
            $table->index('payable_id');
            $table->index('invoice_id');
            $table->index('invoice_no');

            /*
            |--------------------------------------------------------------------------
            | FOREIGN KEYS
            |--------------------------------------------------------------------------
            */

            $table->foreign('settlement_id')
                ->references('id')
                ->on('doctor_settlements')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('payable_id')
                ->references('id')
                ->on('doctor_payables')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_settlement_items');
    }
};
