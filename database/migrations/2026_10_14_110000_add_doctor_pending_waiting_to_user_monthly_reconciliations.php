<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Part of a month's Doctor Payment Pending that sits on invoices the patient has NOT yet
     * paid in full: those payables cannot be settled (Doctor Settlement only offers an
     * invoice once nothing is due), so the dashboard says how much of the pending is waiting
     * for the invoice's final payment.
     */
    public function up(): void
    {
        if (Schema::hasTable('user_monthly_reconciliations') && !Schema::hasColumn('user_monthly_reconciliations', 'doctor_pending_waiting')) {

            Schema::table('user_monthly_reconciliations', function (Blueprint $table) {
                $table->decimal('doctor_pending_waiting', 14, 2)->default(0)->after('doctor_pending');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_monthly_reconciliations', 'doctor_pending_waiting')) {

            Schema::table('user_monthly_reconciliations', function (Blueprint $table) {
                $table->dropColumn('doctor_pending_waiting');
            });
        }
    }
};
