<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('report_delivered_at')->nullable()->after('whatsapp_error');
            $table->unsignedBigInteger('report_delivered_by')->nullable()->after('report_delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['report_delivered_at', 'report_delivered_by']);
        });
    }
};
