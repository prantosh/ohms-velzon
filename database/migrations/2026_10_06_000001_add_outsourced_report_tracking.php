<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outsourced diagnostic tests (invoice_item_details.is_outsourced = 1) have
 * no in-house result-entry step at all -- they were previously just
 * excluded from result_status entirely, with invoices.report_delivered_at
 * covering only the in-house portion. On a mixed invoice (common: ~80% of
 * outsourced-containing invoices also carry in-house items), staff had no
 * way to track the outsourced report's progress separately from the
 * in-house one.
 *
 * Outsourced tracking is a 3-state workflow -- Pending -> Received (staff
 * physically have the report back from the outside lab) -> Delivered (handed
 * to the patient) -- kept as its own pair of timestamps on invoices,
 * parallel to report_delivered_at/report_delivered_by (which now reads as
 * "in-house delivered").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            $table->timestamp('outsourced_report_received_at')->nullable()->after('report_delivered_by');
            $table->unsignedBigInteger('outsourced_report_received_by')->nullable()->after('outsourced_report_received_at');

            $table->timestamp('outsourced_report_delivered_at')->nullable()->after('outsourced_report_received_by');
            $table->unsignedBigInteger('outsourced_report_delivered_by')->nullable()->after('outsourced_report_delivered_at');
        });

        // Append-only log parity with the existing in-house delivery log --
        // is_outsourced distinguishes an outsourced-stage row from a normal
        // in-house delivery row; stage records WHICH outsourced event this
        // row is (null for in-house rows, which only ever have one stage).
        Schema::table('test_report_deliveries', function (Blueprint $table) {

            $table->boolean('is_outsourced')->default(false)->after('invoice_no');
            $table->string('stage', 20)->nullable()->after('is_outsourced');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'outsourced_report_received_at',
                'outsourced_report_received_by',
                'outsourced_report_delivered_at',
                'outsourced_report_delivered_by',
            ]);
        });

        Schema::table('test_report_deliveries', function (Blueprint $table) {
            $table->dropColumn(['is_outsourced', 'stage']);
        });
    }
};
