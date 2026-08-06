<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('test_extra_field_types')
            ->where('field_name', 'Note')
            ->update(['input_type' => 'SELECT', 'source_master' => 'note']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('test_extra_field_types')
            ->where('field_name', 'Note')
            ->update(['input_type' => 'TEXTAREA', 'source_master' => null]);
    }
};
