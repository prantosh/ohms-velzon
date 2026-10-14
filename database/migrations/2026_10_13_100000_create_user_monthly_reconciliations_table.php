<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One saved row per user per COMPLETED month (written on the 1st for the month
     * that just ended -- see ReconciliationService / reconciliation:snapshot).
     * "period_*" is that month alone, "cum_*" is everything since the system began
     * up to the end of that month. The current month is never stored: it is worked
     * out live as month-to-date.
     */
    public function up(): void
    {
        if (Schema::hasTable('user_monthly_reconciliations')) {
            return;
        }

        Schema::create('user_monthly_reconciliations', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->date('month_start');
            $table->date('month_end');

            // this month only
            $table->decimal('period_collection_cash', 14, 2)->default(0);
            $table->decimal('period_collection_non_cash', 14, 2)->default(0);
            $table->decimal('period_refund', 14, 2)->default(0);
            $table->decimal('period_doctor_payable', 14, 2)->default(0);
            $table->decimal('period_doctor_paid', 14, 2)->default(0);
            $table->decimal('period_net_cash', 14, 2)->default(0);

            // since the system began, up to month_end
            $table->decimal('cum_collection_cash', 14, 2)->default(0);
            $table->decimal('cum_collection_non_cash', 14, 2)->default(0);
            $table->decimal('cum_refund', 14, 2)->default(0);
            $table->decimal('cum_doctor_payable', 14, 2)->default(0);
            $table->decimal('cum_doctor_paid', 14, 2)->default(0);
            $table->decimal('cum_net_cash', 14, 2)->default(0);

            // doctor payment still pending at month_end on the invoices this user billed
            $table->decimal('doctor_pending', 14, 2)->default(0);

            // mode-wise and doctor-wise breakdown shown by "Show detail"
            $table->longText('details')->nullable();

            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'month_start'], 'umr_user_month_unique');
            $table->index('month_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_monthly_reconciliations');
    }
};
