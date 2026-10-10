<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Stored copy of a diagnostic invoice's report-completion status so the
     * Test Report Dashboard (and Delivery Log) read one column instead of
     * recomputing it from five report tables per invoice on every load. Kept
     * current by InvoiceReportStatusRecorder; NULL means "not computed yet /
     * invalidated" and is filled in on first read.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            if (!Schema::hasColumn('invoices', 'report_status')) {
                // N/A, Pending, Partial, Confirmation Pending, Complete
                $table->string('report_status', 30)->nullable();
            }

            if (!Schema::hasColumn('invoices', 'report_total_tests')) {
                $table->unsignedSmallInteger('report_total_tests')->nullable();
            }

            if (!Schema::hasColumn('invoices', 'report_prepared_count')) {
                $table->unsignedSmallInteger('report_prepared_count')->nullable();
            }

            if (!Schema::hasColumn('invoices', 'report_confirmed_count')) {
                $table->unsignedSmallInteger('report_confirmed_count')->nullable();
            }

            if (!Schema::hasColumn('invoices', 'report_status_updated_at')) {
                $table->timestamp('report_status_updated_at')->nullable();
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index('report_status', 'idx_invoices_report_status');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_report_status');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'report_status',
                'report_total_tests',
                'report_prepared_count',
                'report_confirmed_count',
                'report_status_updated_at',
            ]);
        });
    }
};
