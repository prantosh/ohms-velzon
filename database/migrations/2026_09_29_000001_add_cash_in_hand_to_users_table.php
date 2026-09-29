<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Some pre-existing users rows carry a legacy date_of_birth of
        // '0000-00-00', which NO_ZERO_DATE (part of this connection's
        // default strict sql_mode) re-validates on any ALTER TABLE that
        // rebuilds the table -- unrelated to this column, but it blocks
        // adding it. Relax just for this session/connection, not
        // permanently and not touching the existing data itself.
        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode;

        DB::statement("SET SESSION sql_mode = ''");

        try {

            Schema::table('users', function (Blueprint $table) {

                // Running balance, maintained incrementally by
                // CashInHandService every time this user collects/refunds/
                // pays out cash -- never recomputed from daily_transactions
                // on read, so the Home dashboard can show it with a plain
                // column read.
                $table->decimal('cash_in_hand', 12, 2)->default(0)->after('remember_token');
            });

        } finally {
            DB::statement("SET SESSION sql_mode = '{$originalSqlMode}'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('cash_in_hand');
        });
    }
};
