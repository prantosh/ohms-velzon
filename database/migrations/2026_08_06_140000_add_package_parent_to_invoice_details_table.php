<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        // Unrelated pre-existing bad legacy data on this table (e.g. a
        // '0000-00-00' date) makes MySQL strict mode reject ANY ALTER TABLE
        // here, since it revalidates every column on every row during the
        // rebuild -- not just the one being added. Relaxed only for this
        // statement, restored right after; no data is touched.
        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode;

        DB::statement("SET SESSION sql_mode = ''");

        try {

            Schema::table('invoice_details', function (Blueprint $table) {

                // Set on an auto-added, zero-rate package component row to
                // the item_code_sub of the package row that billed it on
                // the same invoice -- null for every other row (a real
                // billed test, or the package row itself). Lets invoice
                // print/PDF exclude components (the invoice should show
                // only the package name/amount) while keeping each
                // component's own invoice_details row intact for Test
                // Result Entry / test report generation, which needs them.
                if (!Schema::hasColumn('invoice_details', 'package_parent_item_code_sub')) {
                    $table->string('package_parent_item_code_sub')->nullable();
                }
            });

        } finally {

            DB::statement("SET SESSION sql_mode = '{$originalSqlMode}'");
        }
    }

    public function down(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {

            if (Schema::hasColumn('invoice_details', 'package_parent_item_code_sub')) {
                $table->dropColumn('package_parent_item_code_sub');
            }
        });
    }
};
