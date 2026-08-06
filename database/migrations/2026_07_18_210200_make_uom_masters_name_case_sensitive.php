<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * UOM notation is case-sensitive by scientific convention (e.g. "mm"
     * vs "MM" are different units) -- but MySQL's default collation
     * (utf8mb4_unicode_ci) is case-insensitive, so the unique constraint
     * on name incorrectly treated those as duplicates. Switch to a
     * case-sensitive (binary) collation so distinct-case values can
     * coexist, matching how this column is actually used.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE uom_masters MODIFY name VARCHAR(30) COLLATE utf8mb4_bin NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE uom_masters MODIFY name VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL');
    }
};
