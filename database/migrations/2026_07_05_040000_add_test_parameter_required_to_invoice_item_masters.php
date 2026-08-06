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
        Schema::table('invoice_item_masters', function (Blueprint $table) {

            $table->enum('test_parameter_required', ['YES', 'NO'])
                ->default('NO')
                ->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_item_masters', function (Blueprint $table) {

            $table->dropColumn('test_parameter_required');
        });
    }
};
