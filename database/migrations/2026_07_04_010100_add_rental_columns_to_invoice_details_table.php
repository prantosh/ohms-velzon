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
        Schema::table('invoice_details', function (Blueprint $table) {

            $table->unsignedBigInteger('equipment_category_id')
                ->nullable()
                ->after('item_description');

            $table->date('rental_return_date')
                ->nullable()
                ->after('equipment_category_id');

            $table->integer('rental_days')
                ->nullable()
                ->after('rental_return_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {

            $table->dropColumn([
                'equipment_category_id',
                'rental_return_date',
                'rental_days'
            ]);
        });
    }
};
