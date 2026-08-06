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
        Schema::create('doctor_settlements', function (Blueprint $table) {

            $table->id();

            $table->string('settlement_no', 30)->unique();

            $table->date('settlement_date');
            $table->string('voucher_no', 30)->nullable();

            $table->unsignedBigInteger('doctor_id');

            $table->string('doctor_code', 30);

            $table->string('doctor_name', 150);

            $table->string('payment_mode', 20);

            $table->string('bank_name', 100)->nullable();

            $table->string('cheque_no', 50)->nullable();

            $table->string('utr_no', 100)->nullable();

            $table->decimal('gross_amount', 12, 2)->default(0);

            $table->decimal('deduction_amount', 12, 2)->default(0);

            $table->decimal('net_amount', 12, 2)->default(0);

            $table->string('remarks', 255)->nullable();

            $table->text('internal_notes')->nullable();

            $table->enum('status', [
                'DRAFT',
                'COMPLETED',
                'CANCELLED'
            ])->default('DRAFT');

            $table->string('cancellation_reason', 255)->nullable();

            $table->unsignedBigInteger('cancelled_by')->nullable();

            $table->dateTime('cancelled_at')->nullable();

            $table->unsignedBigInteger('created_by');

            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */

            $table->index('settlement_date');

            $table->index('doctor_id');

            $table->index('status');

            /*
            |--------------------------------------------------------------------------
            | FOREIGN KEYS
            |--------------------------------------------------------------------------
            */

            $table->foreign('doctor_id')
                ->references('id')
                ->on('doctors')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_settlements');
    }
};
