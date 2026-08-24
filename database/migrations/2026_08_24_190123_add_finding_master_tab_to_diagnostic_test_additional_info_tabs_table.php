<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('diagnostic_test_additional_info_tabs')->where('route_name', 'finding-master.index')->exists()) {
            return;
        }

        $nextSortOrder = (int) DB::table('diagnostic_test_additional_info_tabs')->max('sort_order') + 1;

        DB::table('diagnostic_test_additional_info_tabs')->insert([
            'label' => 'Finding Master',
            'route_name' => 'finding-master.index',
            'page_access_key' => 'finding-master',
            'sort_order' => $nextSortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('diagnostic_test_additional_info_tabs')->where('route_name', 'finding-master.index')->delete();
    }
};
