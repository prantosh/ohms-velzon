<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('test_extra_field_types')->where('field_name', 'Finding')->exists()) {
            return;
        }

        $nextSortOrder = (int) DB::table('test_extra_field_types')->max('sort_order') + 1;

        DB::table('test_extra_field_types')->insert([
            'field_name' => 'Finding',
            'input_type' => 'SELECT',
            'source_master' => 'finding',
            'sort_order' => $nextSortOrder,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('test_extra_field_types')->where('field_name', 'Finding')->delete();
    }
};
