<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('income_transactions', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->string('receipt_number')->unique();

            $table->foreignId('income_category_id')
                ->constrained('income_categories');

            $table->string('received_from')->nullable();

            $table->date('transaction_date');

            $table->decimal('amount', 12, 2);

            $table->string('payment_mode');

            $table->string('cheque_number')->nullable();

            $table->string('bank_name')->nullable();

            $table->date('cheque_date')->nullable();

            $table->string('reference_number')->nullable();

            $table->unsignedBigInteger('received_by');

            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_transactions');
    }
};
