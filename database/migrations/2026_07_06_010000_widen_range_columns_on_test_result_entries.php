<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE test_result_entries MODIFY range_male VARCHAR(100) NULL');

        DB::statement('ALTER TABLE test_result_entries MODIFY range_female VARCHAR(100) NULL');

        DB::statement('ALTER TABLE test_result_entries MODIFY range_common VARCHAR(300) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE test_result_entries MODIFY range_male VARCHAR(50) NULL');

        DB::statement('ALTER TABLE test_result_entries MODIFY range_female VARCHAR(50) NULL');

        DB::statement('ALTER TABLE test_result_entries MODIFY range_common VARCHAR(50) NULL');
    }
};
