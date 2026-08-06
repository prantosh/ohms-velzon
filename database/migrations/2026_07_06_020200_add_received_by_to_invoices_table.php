<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            if (!Schema::hasColumn('invoices', 'received_by')) {

                $table->unsignedBigInteger('received_by')->nullable()->after('paid_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            if (Schema::hasColumn('invoices', 'received_by')) {

                $table->dropColumn('received_by');
            }
        });
    }
};
