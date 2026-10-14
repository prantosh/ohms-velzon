<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Two new fields on the structured 2D-Echo report (see
     * CardiologyReportFields): "11. Inferior Vena Cava" and the un-numbered
     * "IMPRESSION", on both the saved reports and the reusable templates.
     * Idempotent.
     */
    private const TABLES = ['cardiology_report_findings', 'cardiology_report_templates'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {

                if (!Schema::hasColumn($tableName, 'inferior_vena_cava')) {
                    $table->text('inferior_vena_cava')->nullable();
                }

                if (!Schema::hasColumn($tableName, 'impression')) {
                    $table->longText('impression')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {

                foreach (['inferior_vena_cava', 'impression'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
