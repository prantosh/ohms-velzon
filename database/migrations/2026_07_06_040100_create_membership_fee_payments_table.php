<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('membership_fee_payments', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->unsignedBigInteger('user_id');

            $table->date('payment_month');

            $table->decimal('rate_applied', 10, 2);

            $table->decimal('amount', 10, 2);

            $table->enum('payment_type', ['PAID', 'ADJUSTED'])->default('PAID');

            $table->string('invoice_no', 30)->nullable();

            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'payment_month']);

            $table->index('invoice_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_fee_payments');
    }
};
