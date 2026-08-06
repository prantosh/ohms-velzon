<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // range_male/range_female (varchar 100) and range_common (varchar 300)
        // were too narrow for the highly-descriptive range text now
        // selectable from Detail Range Master, causing a hard "1406 Data
        // too long" error under strict SQL mode. Widened to TEXT on both
        // the catalog table and the per-analyte table.
        //
        // MODIFY COLUMN rebuilds the table, which revalidates every row
        // against the current sql_mode -- including unrelated legacy
        // '0000-00-00 00:00:00' placeholder values in update_dt (already
        // treated as "empty" elsewhere in this codebase, e.g.
        // InvoiceItemDetailController::list()'s date formatting). Strict
        // date modes are relaxed for the duration of this migration only,
        // scoped to this session, so that pre-existing unrelated data
        // doesn't block widening these columns.
        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode;

        DB::statement("SET SESSION sql_mode = ''");

        try {

            if (Schema::hasTable('invoice_item_details')) {
                DB::statement('ALTER TABLE invoice_item_details MODIFY range_male TEXT NULL');
                DB::statement('ALTER TABLE invoice_item_details MODIFY range_female TEXT NULL');
                DB::statement('ALTER TABLE invoice_item_details MODIFY range_common TEXT NULL');
            }

            if (Schema::hasTable('test_analytes')) {
                DB::statement('ALTER TABLE test_analytes MODIFY range_male TEXT NULL');
                DB::statement('ALTER TABLE test_analytes MODIFY range_female TEXT NULL');
                DB::statement('ALTER TABLE test_analytes MODIFY range_common TEXT NULL');
            }

        } finally {

            DB::statement("SET SESSION sql_mode = '{$originalSqlMode}'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode;

        DB::statement("SET SESSION sql_mode = ''");

        try {

            if (Schema::hasTable('invoice_item_details')) {
                DB::statement('ALTER TABLE invoice_item_details MODIFY range_male VARCHAR(100) NULL');
                DB::statement('ALTER TABLE invoice_item_details MODIFY range_female VARCHAR(100) NULL');
                DB::statement('ALTER TABLE invoice_item_details MODIFY range_common VARCHAR(300) NULL');
            }

            if (Schema::hasTable('test_analytes')) {
                DB::statement('ALTER TABLE test_analytes MODIFY range_male VARCHAR(100) NULL');
                DB::statement('ALTER TABLE test_analytes MODIFY range_female VARCHAR(100) NULL');
                DB::statement('ALTER TABLE test_analytes MODIFY range_common VARCHAR(300) NULL');
            }

        } finally {

            DB::statement("SET SESSION sql_mode = '{$originalSqlMode}'");
        }
    }
};
