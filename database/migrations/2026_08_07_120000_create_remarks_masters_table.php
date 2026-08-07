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
            Schema::create('remarks_masters', function (Blueprint $table) {

                $table->engine = 'InnoDB';

                $table->id();

                $table->longText('name');

                $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');

                $table->unsignedBigInteger('created_by')->nullable();

                $table->unsignedBigInteger('updated_by')->nullable();

                $table->timestamps();
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
        Schema::dropIfExists('remarks_masters');
    }
};
