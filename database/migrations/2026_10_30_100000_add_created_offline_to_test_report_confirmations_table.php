<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('test_report_confirmations', function (Blueprint $table) {

            // The report was written on paper/offline (system or internet
            // trouble) -- the row still reads as Complete + Confirmed, but
            // there is nothing in the system to print or WhatsApp.
            $table->boolean('created_offline')->default(false)->after('confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('test_report_confirmations', function (Blueprint $table) {
            $table->dropColumn('created_offline');
        });
    }
};
