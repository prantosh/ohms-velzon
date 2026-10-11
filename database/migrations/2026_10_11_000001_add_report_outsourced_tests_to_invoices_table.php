<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Speeds up the Test Report Dashboard / Delivery Log.
     *
     * report_outsourced_tests: how many outsourced (outside-lab) report lines
     * the invoice has -- stored beside report_total_tests (the in-house count)
     * by InvoiceReportStatusRecorder, so "does this invoice still owe a report
     * delivery" is a plain column test instead of a stack of nested EXISTS
     * subqueries over invoice_details.
     *
     * Index on (invoice_type, invoice_date): the list's date-range filter.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            if (!Schema::hasColumn('invoices', 'report_outsourced_tests')) {
                $table->unsignedSmallInteger('report_outsourced_tests')->nullable();
            }
        });

        $hasIndex = collect(DB::select("SHOW INDEX FROM invoices WHERE Key_name = 'idx_invoices_type_date'"))->isNotEmpty();

        if (!$hasIndex) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->index(['invoice_type', 'invoice_date'], 'idx_invoices_type_date');
            });
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_type_date');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('report_outsourced_tests');
        });
    }
};
