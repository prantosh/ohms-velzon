<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Nullable on every report-finding table -- set once, when a new report is
 * saved for a billed line that has a pending report_correction_notices row
 * (i.e. its previous report was deleted by an Admin after already being
 * WhatsApp'd to the patient). The PDF templates print a footnote whenever
 * this is non-null, for the whole lifetime of that one corrected report.
 */
return new class extends Migration
{
    private const TABLES = [
        'usg_report_findings',
        'cardiology_report_findings',
        'non_pathology_report_findings',
        'pathology_report_findings',
    ];

    public function up(): void
    {
        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode;

        DB::statement("SET SESSION sql_mode = ''");

        try {

            foreach (self::TABLES as $tableName) {

                Schema::table($tableName, function (Blueprint $table) {
                    $table->timestamp('corrected_from_sent_at')->nullable()->after('confirmed_at');
                });
            }

        } finally {

            DB::statement("SET SESSION sql_mode = '{$originalSqlMode}'");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('corrected_from_sent_at');
            });
        }
    }
};
