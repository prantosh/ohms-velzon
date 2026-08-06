<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('invoice_item_details', 'sample_master_id')) {

            Schema::table('invoice_item_details', function (Blueprint $table) {

                $table->unsignedBigInteger('sample_master_id')
                    ->nullable()
                    ->after('test_group_code');
            });
        }

        $foreignKeyExists = collect(Schema::getForeignKeys('invoice_item_details'))
            ->contains(fn ($fk) => $fk['name'] === 'invoice_item_details_sample_master_id_foreign');

        if (!$foreignKeyExists) {

            // ALTER TABLE ... ADD FOREIGN KEY rebuilds the table, which makes
            // MySQL re-validate existing columns under the active sql_mode.
            // Some legacy rows have '0000-00-00 00:00:00' in unrelated
            // datetime columns, which STRICT_TRANS_TABLES rejects. Relax
            // sql_mode for just this statement rather than touching that
            // existing data (same fix as
            // 2026_07_05_030000_replace_test_group_with_code_in_invoice_item_details.php).
            $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;

            DB::statement("SET SESSION sql_mode = ''");

            try {

                Schema::table('invoice_item_details', function (Blueprint $table) {

                    $table->foreign('sample_master_id')
                        ->references('id')
                        ->on('sample_masters')
                        ->onDelete('set null');
                });

            } finally {

                DB::statement("SET SESSION sql_mode = '{$originalSqlMode}'");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_item_details', function (Blueprint $table) {

            $table->dropForeign(['sample_master_id']);
            $table->dropColumn('sample_master_id');
        });
    }
};
