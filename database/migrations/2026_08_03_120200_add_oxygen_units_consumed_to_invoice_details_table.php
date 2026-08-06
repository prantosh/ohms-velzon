<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {

            if (!Schema::hasColumn('invoice_details', 'oxygen_units_consumed')) {
                $table->integer('oxygen_units_consumed')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {

            if (Schema::hasColumn('invoice_details', 'oxygen_units_consumed')) {
                $table->dropColumn('oxygen_units_consumed');
            }
        });
    }
};
