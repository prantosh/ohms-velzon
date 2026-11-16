<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A doctor-visit fee waived after the invoice was raised (doctor exempts
     * the fee, e.g. a poor patient). The invoice stays a real, non-cancelled
     * visit with paid_amount 0 and status 'Waived'; these columns record who
     * waived it, how much, why, and which doctor's exemption it was.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            if (!Schema::hasColumn('invoices', 'fee_waived')) {
                $table->boolean('fee_waived')->default(false);
            }

            if (!Schema::hasColumn('invoices', 'waived_amount')) {
                $table->decimal('waived_amount', 10, 2)->default(0);
            }

            if (!Schema::hasColumn('invoices', 'waived_by')) {
                $table->unsignedBigInteger('waived_by')->nullable();
            }

            if (!Schema::hasColumn('invoices', 'waived_at')) {
                $table->timestamp('waived_at')->nullable();
            }

            if (!Schema::hasColumn('invoices', 'waiver_approved_by_doctor_id')) {
                $table->unsignedBigInteger('waiver_approved_by_doctor_id')->nullable();
            }

            if (!Schema::hasColumn('invoices', 'waiver_reason')) {
                $table->string('waiver_reason', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            foreach ([
                'fee_waived', 'waived_amount', 'waived_by', 'waived_at',
                'waiver_approved_by_doctor_id', 'waiver_reason',
            ] as $col) {
                if (Schema::hasColumn('invoices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
