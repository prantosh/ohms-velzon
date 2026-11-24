<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partial invoice cancellation: one billed line is cancelled, the original
 * invoice is superseded, and a "Modified Invoice" is issued for the rest.
 * No foreign keys on purpose (invoice_details is MyISAM on production).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('parent_invoice_no', 30)->nullable()->index();
            $table->string('superseded_by_invoice_no', 30)->nullable();
            $table->boolean('is_modified_invoice')->default(false);
        });

        Schema::create('invoice_partial_cancellations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('original_invoice_no', 30)->index();
            $table->string('new_invoice_no', 30)->index();
            $table->unsignedBigInteger('invoice_detail_id');
            $table->string('item_code', 50)->nullable();
            $table->string('item_code_sub', 50)->nullable();
            $table->string('item_description', 255)->nullable();
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);       // net value of the cancelled line
            $table->decimal('refund_amount', 12, 2)->default(0); // cash actually refunded for the whole action (same on each row)
            $table->string('remarks', 500)->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_partial_cancellations');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['parent_invoice_no', 'superseded_by_invoice_no', 'is_modified_invoice']);
        });
    }
};
