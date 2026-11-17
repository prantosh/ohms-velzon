<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        // Entered on the report card for the URINE C/S (NO GROWTH) template and
        // printed above the report content.
        Schema::table('pathology_report_findings', function (Blueprint $table) {
            $table->date('receipt_date')->nullable()->after('report_title');
            $table->date('report_date')->nullable()->after('receipt_date');
        });
    }

    public function down(): void
    {
        Schema::table('pathology_report_findings', function (Blueprint $table) {
            $table->dropColumn(['receipt_date', 'report_date']);
        });
    }
};
