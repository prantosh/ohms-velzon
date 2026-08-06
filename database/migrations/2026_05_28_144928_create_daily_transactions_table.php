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
        Schema::create('daily_transactions', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | TRANSACTION INFO
            |--------------------------------------------------------------------------
            */

            $table->date('transaction_date');

            $table->string('transaction_no')->unique();

            $table->enum(
                'transaction_type',
                [
                    'RECEIVED',
                    'REFUND',
                    'PAYMENT',
                    'EXPENSE'
                ]
            )->default('RECEIVED');

            /*
            |--------------------------------------------------------------------------
            | AMOUNT DETAILS
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'received_amount',
                12,
                2
            )->default(0);

            $table->decimal(
                'refund_amount',
                12,
                2
            )->default(0);

            $table->decimal(
                'payment_amount',
                12,
                2
            )->default(0);

            

            /*
            |--------------------------------------------------------------------------
            | REFERENCES
            |--------------------------------------------------------------------------
            */

            $table->string('invoice_reference')
                ->nullable();

            $table->string('item_reference')
                ->nullable();

            $table->string('appointment_reference')
                ->nullable();

            $table->string('patient_id')
                ->nullable();

            $table->string('patient_name')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | OPERATOR / USER
            |--------------------------------------------------------------------------
            */

            $table->unsignedBigInteger('operator_id')
                ->nullable();

            $table->string('operator_name')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | PAYMENT DETAILS
            |--------------------------------------------------------------------------
            */

            $table->enum(
                'payment_mode',
                [
                    'Cash',
                    'Card',
                    'UPI',
                    'Bank',
                    'Cheque'
                ]
            )->default('Cash');

            $table->string('reference_no')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | REMARKS
            |--------------------------------------------------------------------------
            */

            $table->text('remarks')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            $table->enum(
                'status',
                [
                    'ACTIVE',
                    'CANCELLED',
                    'REFUNDED'
                ]
            )->default('ACTIVE');

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
        Schema::dropIfExists('daily_transactions');
    }
};
