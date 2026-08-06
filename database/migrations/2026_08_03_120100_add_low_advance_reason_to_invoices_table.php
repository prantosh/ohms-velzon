<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            if (!Schema::hasColumn('invoices', 'low_advance_reason')) {
                $table->text('low_advance_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            if (Schema::hasColumn('invoices', 'low_advance_reason')) {
                $table->dropColumn('low_advance_reason');
            }
        });
    }
};
