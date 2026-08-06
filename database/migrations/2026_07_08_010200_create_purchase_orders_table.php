<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->string('po_no')->unique();

            $table->date('po_date');

            $table->string('requisition_ref')->nullable();

            $table->date('requisition_date')->nullable();

            $table->string('vendor_name');

            $table->string('payment_term')->nullable();

            $table->decimal('total_value', 12, 2)->default(0);

            $table->text('note')->nullable();

            $table->enum('status', ['OPEN', 'CLOSED', 'CANCELLED'])->default('OPEN');

            $table->unsignedBigInteger('created_by')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
