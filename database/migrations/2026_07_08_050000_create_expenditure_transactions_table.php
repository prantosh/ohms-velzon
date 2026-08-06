<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('expenditure_transactions', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->string('transaction_no')->unique();

            $table->foreignId('expenditure_category_id')
                ->constrained('expenditure_categories');

            $table->foreignId('expenditure_agency_id')
                ->constrained('expenditure_agencies');

            $table->date('transaction_date');

            $table->decimal('amount', 12, 2);

            $table->string('payment_mode');

            $table->string('cheque_number')->nullable();

            $table->string('bank_name')->nullable();

            $table->string('voucher_number')->nullable();

            $table->date('cheque_date')->nullable();

            $table->string('expenditure_by');

            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenditure_transactions');
    }
};
