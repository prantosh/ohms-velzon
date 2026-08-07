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
            Schema::create('test_report_template_values', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->foreignId('test_report_template_id')->constrained('test_report_templates')->cascadeOnDelete();
                $table->foreignId('test_extra_field_type_id')->constrained('test_extra_field_types');
                $table->longText('value');
                $table->timestamps();

                $table->unique(
                    ['test_report_template_id', 'test_extra_field_type_id'],
                    'template_field_type_unique'
                );
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
        Schema::dropIfExists('test_report_template_values');
    }
};
