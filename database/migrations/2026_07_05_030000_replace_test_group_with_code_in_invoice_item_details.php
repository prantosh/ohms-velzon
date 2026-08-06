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
        if (!Schema::hasColumn('invoice_item_details', 'test_group_code')) {

            Schema::table('invoice_item_details', function (Blueprint $table) {

                $table->unsignedBigInteger('test_group_code')
                    ->nullable()
                    ->after('test_group');
            });
        }

        $foreignKeyExists = collect(Schema::getForeignKeys('invoice_item_details'))
            ->contains(fn ($fk) => $fk['name'] === 'invoice_item_details_test_group_code_foreign');

        if (!$foreignKeyExists) {

            // The ALTER TABLE below rebuilds the table, which makes MySQL
            // re-validate existing columns under the active sql_mode. Some
            // legacy rows have '0000-00-00 00:00:00' in unrelated datetime
            // columns, which STRICT_TRANS_TABLES rejects. Relax sql_mode for
            // just this statement rather than touching that existing data.
            $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;

            DB::statement("SET SESSION sql_mode = ''");

            try {

                Schema::table('invoice_item_details', function (Blueprint $table) {

                    $table->foreign('test_group_code')
                        ->references('id')
                        ->on('test_group_masters')
                        ->onDelete('set null');
                });

            } finally {

                DB::statement("SET SESSION sql_mode = '{$originalSqlMode}'");
            }
        }

        if (Schema::hasColumn('invoice_item_details', 'test_group')) {

            Schema::table('invoice_item_details', function (Blueprint $table) {

                $table->dropColumn('test_group');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_item_details', function (Blueprint $table) {

            $table->string('test_group', 20)->nullable();
        });

        Schema::table('invoice_item_details', function (Blueprint $table) {

            $table->dropForeign(['test_group_code']);
            $table->dropColumn('test_group_code');
        });
    }
};
