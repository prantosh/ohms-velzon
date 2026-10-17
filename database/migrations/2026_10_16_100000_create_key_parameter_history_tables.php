<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Patient-wise history of "key" pathology parameters (FBS, PPBS, Creatinine,
 * Lipid/LFT components, electrolytes, Hb/RBC/Platelet, Vitamin D, ...) so a
 * returning patient's past values can be shown/printed on their next report.
 *
 * key_parameters: the curated, admin-extensible list of which parameters are
 * tracked. Each row's own identity is `item_code_sub` (the stable test-master
 * code, e.g. PAT160 = FBS) -- but a parameter is only CAPTURABLE when one of
 * its `trigger_item_code_subs` is actually billed on the invoice. For most
 * parameters that's just itself (FBS triggers on FBS being billed). For a
 * single-line bundled panel like LFT (billed as ONE line, PAT176, with
 * SGOT/SGPT/Bilirubin/etc. all typed together in one narrative -- NOT billed
 * as separate package-component lines the way Lipid Profile is), the
 * individual component parameters (SGOT = PAT198, etc.) trigger on PAT176
 * instead of their own code. Same story for CBC bundling Hb/RBC/Platelet.
 *
 * patient_key_parameter_values: the actual captured history, one row per
 * value per confirmed report. Append-only -- display/print logic limits to
 * the most recent 3 results within the last 12 months (see
 * KeyParameterService), nothing here is ever auto-deleted for that policy.
 *
 * pathology_report_findings.key_parameter_values: a draft JSON blob (same
 * lifecycle as `content`) holding whatever's been typed into the Key
 * Parameters box so far -- only promoted into patient_key_parameter_values
 * once the report is actually confirmed (see
 * KeyParameterService::recordConfirmedValues()).
 */
return new class extends Migration
{
    public function up(): void
    {
        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode;

        DB::statement("SET SESSION sql_mode = ''");

        try {

            Schema::create('key_parameters', function (Blueprint $table) {

                // Explicit -- this server's default storage engine is
                // MyISAM (same as invoices/invoice_details), which has no
                // transaction/row-lock support. These are brand-new tables
                // with no legacy reason to inherit that; InnoDB so
                // DB::beginTransaction()/rollBack() actually work on them
                // and concurrent confirms don't take a whole-table lock.
                $table->engine = 'InnoDB';

                $table->id();

                $table->string('item_code_sub', 20)->unique();
                $table->string('parameter_name', 150);
                $table->string('group_label', 100)->nullable();
                $table->string('uom', 50)->nullable();

                // JSON array of item_code_sub values that make this
                // parameter capturable when billed (see class docblock).
                $table->json('trigger_item_code_subs');

                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);

                $table->timestamps();
            });

            Schema::create('patient_key_parameter_values', function (Blueprint $table) {

                $table->engine = 'InnoDB';

                $table->id();

                $table->string('patient_id', 25);
                $table->string('item_code_sub', 20);
                $table->string('parameter_name', 150);
                $table->string('result_value', 100);
                $table->string('uom', 50)->nullable();
                $table->string('reference_range', 100)->nullable();

                $table->string('invoice_no', 30);
                $table->unsignedBigInteger('pathology_report_finding_id')->nullable();
                $table->date('test_date');

                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['patient_id', 'item_code_sub', 'test_date'], 'pkpv_patient_param_date_idx');
            });

            Schema::table('pathology_report_findings', function (Blueprint $table) {
                $table->json('key_parameter_values')->nullable()->after('content');
            });

        } finally {

            DB::statement("SET SESSION sql_mode = '{$originalSqlMode}'");
        }
    }

    public function down(): void
    {
        Schema::table('pathology_report_findings', function (Blueprint $table) {
            $table->dropColumn('key_parameter_values');
        });

        Schema::dropIfExists('patient_key_parameter_values');
        Schema::dropIfExists('key_parameters');
    }
};
