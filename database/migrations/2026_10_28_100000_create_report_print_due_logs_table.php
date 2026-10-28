<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_print_due_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('invoice_no', 50)->index();
            $table->string('patient_name')->nullable();
            $table->string('patient_mobile_no', 20)->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->text('test_items')->nullable();
            $table->string('print_type', 30)->nullable();
            $table->unsignedBigInteger('printed_by')->nullable()->index();
            $table->string('printed_by_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('printed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_print_due_logs');
    }
};
