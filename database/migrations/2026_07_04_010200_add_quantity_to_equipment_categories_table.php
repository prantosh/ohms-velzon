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
        Schema::table('equipment_categories', function (Blueprint $table) {

            $table->integer('total_quantity')
                ->default(0)
                ->after('category_name');

            $table->integer('available_quantity')
                ->default(0)
                ->after('total_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment_categories', function (Blueprint $table) {

            $table->dropColumn([
                'total_quantity',
                'available_quantity'
            ]);
        });
    }
};
