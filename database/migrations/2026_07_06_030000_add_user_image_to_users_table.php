<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('users', 'user_image')) {
            return;
        }

        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;

        DB::statement("SET SESSION sql_mode = ''");

        try {

            Schema::table('users', function (Blueprint $table) {

                $table->string('user_image', 191)->nullable()->after('avatar');
            });

        } finally {

            DB::statement("SET SESSION sql_mode = '{$originalSqlMode}'");
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            if (Schema::hasColumn('users', 'user_image')) {

                $table->dropColumn('user_image');
            }
        });
    }
};
