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
        Schema::table('invoice_item_details', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_item_details', 'is_package')) {
                $table->tinyInteger('is_package')->default(0)->after('item_description_sub');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_item_details', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_item_details', 'is_package')) {
                $table->dropColumn('is_package');
            }
        });
    }
};
