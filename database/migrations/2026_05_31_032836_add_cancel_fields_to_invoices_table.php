<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            $table->char('cancelled', 1)
                ->default('N')
                ->after('status');

            $table->unsignedBigInteger('cancelled_by')
                ->nullable()
                ->after('cancelled');

            $table->timestamp('cancelled_at')
                ->nullable()
                ->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            $table->dropColumn([
                'cancelled',
                'cancelled_by',
                'cancelled_at'
            ]);
        });
    }
};
