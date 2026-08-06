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
        Schema::table('test_result_entries', function (Blueprint $table) {

            $table->unsignedBigInteger('analyte_id')->default(0)->after('invoice_detail_id');
        });

        Schema::table('test_result_entries', function (Blueprint $table) {

            $table->dropUnique(['invoice_detail_id']);

            $table->unique(
                ['invoice_detail_id', 'analyte_id'],
                'test_result_entries_line_analyte_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_result_entries', function (Blueprint $table) {

            $table->dropUnique('test_result_entries_line_analyte_unique');

            $table->unique('invoice_detail_id');

            $table->dropColumn('analyte_id');
        });
    }
};
