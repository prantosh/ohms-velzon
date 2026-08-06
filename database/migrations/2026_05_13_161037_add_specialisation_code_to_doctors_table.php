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
        Schema::table('doctors', function (Blueprint $table) {

            $table->unsignedBigInteger('specialisation_code')
                ->nullable()
                ->after('specialisation');

            $table->foreign('specialisation_code')
                ->references('id')
                ->on('specialisations')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {

            $table->dropForeign(['specialisation_code']);

            $table->dropColumn('specialisation_code');
        });
    }
};
