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
        DB::statement(
            "ALTER TABLE test_extra_field_types MODIFY input_type ENUM('TEXT', 'TEXTAREA', 'SELECT') DEFAULT 'TEXT'"
        );

        Schema::table('test_extra_field_types', function (Blueprint $table) {
            $table->string('source_master', 30)->nullable()->after('input_type');
        });

        DB::table('test_extra_field_types')
            ->where('field_name', 'Instrument Used')
            ->update(['input_type' => 'SELECT', 'source_master' => 'instrument']);

        DB::table('test_extra_field_types')
            ->where('field_name', 'Kit Used')
            ->update(['input_type' => 'SELECT', 'source_master' => 'kit']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_extra_field_types', function (Blueprint $table) {
            $table->dropColumn('source_master');
        });

        DB::statement(
            "ALTER TABLE test_extra_field_types MODIFY input_type ENUM('TEXT', 'TEXTAREA') DEFAULT 'TEXT'"
        );
    }
};
