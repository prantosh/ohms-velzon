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
        // Same fix as 2026_07_18_160000_widen_range_columns_to_text -- missed
        // this table the first time. test_result_entries stores a snapshot
        // of the source range at the time a result is entered, so it needs
        // the same TEXT widening as invoice_item_details/test_analytes.
        if (!Schema::hasTable('test_result_entries')) {
            return;
        }

        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode;

        DB::statement("SET SESSION sql_mode = ''");

        try {

            DB::statement('ALTER TABLE test_result_entries MODIFY range_male TEXT NULL');
            DB::statement('ALTER TABLE test_result_entries MODIFY range_female TEXT NULL');
            DB::statement('ALTER TABLE test_result_entries MODIFY range_common TEXT NULL');

        } finally {

            DB::statement("SET SESSION sql_mode = '{$originalSqlMode}'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('test_result_entries')) {
            return;
        }

        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode;

        DB::statement("SET SESSION sql_mode = ''");

        try {

            DB::statement('ALTER TABLE test_result_entries MODIFY range_male VARCHAR(100) NULL');
            DB::statement('ALTER TABLE test_result_entries MODIFY range_female VARCHAR(100) NULL');
            DB::statement('ALTER TABLE test_result_entries MODIFY range_common VARCHAR(300) NULL');

        } finally {

            DB::statement("SET SESSION sql_mode = '{$originalSqlMode}'");
        }
    }
};
