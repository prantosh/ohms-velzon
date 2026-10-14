<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * When an Admin deletes a CONFIRMED report that had already been sent to the
 * patient via WhatsApp, this records the fact (keyed by the billed line,
 * invoice_detail_id) so that whichever new report is next written for that
 * same test can carry a "please ignore the previous report sent at X"
 * footnote -- see ReportDeletionGuard::recordCorrection() and each report
 * controller's store(), which consumes the row once (one footnote, on the
 * one report that replaces the deleted one).
 */
return new class extends Migration
{
    public function up(): void
    {
        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode;

        DB::statement("SET SESSION sql_mode = ''");

        try {

            Schema::create('report_correction_notices', function (Blueprint $table) {

                $table->id();

                $table->unsignedBigInteger('invoice_detail_id');
                $table->timestamp('previous_sent_at');

                $table->timestamps();

                $table->index('invoice_detail_id');
            });

        } finally {

            DB::statement("SET SESSION sql_mode = '{$originalSqlMode}'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_correction_notices');
    }
};
