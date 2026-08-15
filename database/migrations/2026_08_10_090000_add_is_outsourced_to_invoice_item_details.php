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
            if (!Schema::hasColumn('invoice_item_details', 'is_outsourced')) {
                $table->tinyInteger('is_outsourced')->default(0)->after('is_package');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_item_details', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_item_details', 'is_outsourced')) {
                $table->dropColumn('is_outsourced');
            }
        });
    }
};
