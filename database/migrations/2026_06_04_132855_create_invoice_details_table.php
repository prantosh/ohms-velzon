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
        Schema::create('invoice_details', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('invoice_id');

            $table->integer('line_no')
                ->default(1);

            $table->string('item_code', 30)
                ->nullable();

            $table->string('item_code_sub', 30)
                ->nullable();

            $table->string('item_description', 255)
                ->nullable();

            $table->decimal(
                'quantity',
                10,
                2
            )->default(1);

            $table->decimal(
                'rate',
                12,
                2
            )->default(0);

            $table->decimal(
                'standard_discount',
                12,
                2
            )->default(0);

            $table->decimal(
                'additional_discount_percent',
                5,
                2
            )->default(0);

            $table->decimal(
                'additional_discount_amount',
                12,
                2
            )->default(0);

            $table->unsignedBigInteger(
                'discount_approved_by'
            )->nullable();

            $table->string(
                'discount_approval_remarks',
                255
            )->nullable();

            $table->decimal(
                'amount',
                12,
                2
            )->default(0);

            $table->text('remarks')
                ->nullable();

            $table->unsignedBigInteger(
                'created_by'
            )->nullable();

            $table->unsignedBigInteger(
                'updated_by'
            )->nullable();

            $table->timestamps();

            // Indexes
            $table->index('invoice_id');
            $table->index('item_code');
            $table->index('item_code_sub');

            // Foreign Key
            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_details');
    }
};
