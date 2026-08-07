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
        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode;
        DB::statement("SET SESSION sql_mode = ''");

        try {
            Schema::table('test_report_templates', function (Blueprint $table) {
                $table->dropColumn(['microscopy', 'impression']);
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
        Schema::table('test_report_templates', function (Blueprint $table) {
            $table->longText('microscopy')->nullable();
            $table->longText('impression')->nullable();
        });
    }
};
