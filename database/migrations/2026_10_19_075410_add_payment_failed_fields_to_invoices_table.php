<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->text('payment_failed_reason')->nullable()->after('cancellation_remarks');
            $table->unsignedBigInteger('payment_failed_by')->nullable()->after('payment_failed_reason');
            $table->timestamp('payment_failed_at')->nullable()->after('payment_failed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['payment_failed_reason', 'payment_failed_by', 'payment_failed_at']);
        });
    }
};
