<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * A test date for each billed test line. Until now there was only the
     * invoice's own test_date, so two tests on one invoice (e.g. FBS and PPBS)
     * could not carry different dates on their reports.
     *
     * NULL means "use the invoice's test_date", so no existing invoice or
     * printed report changes. Set at invoice creation (defaults to the
     * invoice's test date, editable per test) and editable on the report
     * screens until the report is confirmed.
     */
    public function up(): void
    {
        if (Schema::hasColumn('invoice_details', 'test_date')) {
            return;
        }

        // invoice_details carries unrelated legacy data (e.g. '0000-00-00'
        // dates) that MySQL strict mode revalidates during ANY ALTER TABLE of
        // it -- same workaround as the package_parent_item_code_sub migration:
        // relaxed only for this statement, restored right after; no data is
        // touched.
        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode;

        DB::statement("SET SESSION sql_mode = ''");

        try {

            Schema::table('invoice_details', function (Blueprint $table) {
                $table->date('test_date')->nullable();
            });

        } finally {

            DB::statement("SET SESSION sql_mode = '{$originalSqlMode}'");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoice_details', 'test_date')) {

            Schema::table('invoice_details', function (Blueprint $table) {
                $table->dropColumn('test_date');
            });
        }
    }
};
