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
            Schema::create('test_report_templates', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('title', 150);
                $table->string('item_code_sub', 30);
                $table->text('remarks')->nullable();
                $table->longText('microscopy')->nullable();
                $table->longText('impression')->nullable();
                $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->index('item_code_sub');
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
        Schema::dropIfExists('test_report_templates');
    }
};
