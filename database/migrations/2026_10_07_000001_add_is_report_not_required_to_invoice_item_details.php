<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_item_details', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_item_details', 'is_report_not_required')) {
                $table->tinyInteger('is_report_not_required')->default(0)->after('is_outsourced');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_item_details', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_item_details', 'is_report_not_required')) {
                $table->dropColumn('is_report_not_required');
            }
        });
    }
};