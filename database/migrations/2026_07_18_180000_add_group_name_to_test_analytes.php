<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('test_analytes', function (Blueprint $table) {
            if (!Schema::hasColumn('test_analytes', 'group_name')) {
                $table->string('group_name', 100)->nullable()->after('analyte_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_analytes', function (Blueprint $table) {
            if (Schema::hasColumn('test_analytes', 'group_name')) {
                $table->dropColumn('group_name');
            }
        });
    }
};
