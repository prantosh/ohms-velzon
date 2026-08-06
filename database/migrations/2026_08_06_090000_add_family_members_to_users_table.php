<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        // Unrelated pre-existing data (at least one users row has
        // date_of_birth = '0000-00-00') makes MySQL's strict mode reject
        // ANY ALTER TABLE on this table, since it revalidates every column
        // on every row during the rebuild -- not just the ones being
        // added. Relaxed only for this single statement, restored right
        // after; no data is touched.
        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode;

        DB::statement("SET SESSION sql_mode = ''");

        try {

            Schema::table('users', function (Blueprint $table) {

                // Family members of a Member/Supervisor (the two roles
                // treated as the same "member" tier for diagnostic test
                // discount eligibility -- see
                // MembershipFeeController::searchMember()) are eligible for
                // the same discount. Plain name fields, not a separate
                // table -- at most 3, no other data needed about them.
                if (!Schema::hasColumn('users', 'family_member_1')) {
                    $table->string('family_member_1')->nullable();
                }

                if (!Schema::hasColumn('users', 'family_member_2')) {
                    $table->string('family_member_2')->nullable();
                }

                if (!Schema::hasColumn('users', 'family_member_3')) {
                    $table->string('family_member_3')->nullable();
                }
            });

        } finally {

            DB::statement("SET SESSION sql_mode = '{$originalSqlMode}'");
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            foreach (['family_member_1', 'family_member_2', 'family_member_3'] as $column) {

                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
