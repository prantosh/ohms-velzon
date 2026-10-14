<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The main heading printed on a report. NULL = the default (the test description as
     * saved on the invoice line); set = the heading the user typed on the report card.
     */
    private const TABLES = ['pathology_report_findings', 'usg_report_findings', 'non_pathology_report_findings'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {

            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'report_title')) {

                Schema::table($table, function (Blueprint $t) {
                    $t->string('report_title', 255)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {

            if (Schema::hasTable($table) && Schema::hasColumn($table, 'report_title')) {

                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('report_title');
                });
            }
        }
    }
};
