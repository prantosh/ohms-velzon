<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * For a diagnostic invoice with a doctor-payable portion, a partial
 * collection at invoice creation is only allowed up to (total_amount -
 * doctor_payment_amount) -- so the doctor's share only ever actually
 * ends up in-hand once the invoice's due_amount reaches 0, whether that
 * happens at creation (full payment) or later via a due-payment
 * collection. This column tags which staff user was holding the cash at
 * that exact moment, so the doctor settlement dashboard can show/filter
 * each user's own responsibility for reconciliation -- informational
 * scoping only, not an access restriction (staff rotate shifts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('doctor_amount_collected_by')->nullable()->after('doctor_payment_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('doctor_amount_collected_by');
        });
    }
};
