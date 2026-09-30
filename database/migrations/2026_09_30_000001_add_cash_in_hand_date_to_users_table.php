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
        // Same NO_ZERO_DATE issue as the earlier cash_in_hand migration --
        // some pre-existing users rows carry date_of_birth = '0000-00-00',
        // which blocks any ALTER TABLE on this table under this
        // connection's default strict sql_mode. Relax just for this
        // session, not permanently and not touching the existing data.
        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode;

        DB::statement("SET SESSION sql_mode = ''");

        try {

            Schema::table('users', function (Blueprint $table) {

                // Tracks which day the current cash_in_hand balance belongs
                // to -- see CashInHandService::ensureFreshForToday(), which
                // resets the balance to 0 the moment it's touched or viewed
                // on a day after this date, instead of relying solely on
                // the scheduled cash-in-hand:reset command (which depends
                // on the host's cron actually being configured correctly).
                $table->date('cash_in_hand_date')->nullable()->after('cash_in_hand');
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
            $table->dropColumn('cash_in_hand_date');
        });
    }
};
