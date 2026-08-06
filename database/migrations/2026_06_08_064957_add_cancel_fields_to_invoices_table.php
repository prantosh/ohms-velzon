<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            $table->char('cancelled', 1)
                ->default('N')
                ->after('status');

            $table->unsignedBigInteger('cancelled_by')
                ->nullable()
                ->after('cancelled');

            $table->date('cancelled_date')
                ->nullable()
                ->after('cancelled_by');

            $table->decimal('refund_amount', 12, 2)
                ->default(0)
                ->after('due_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            //
        });
    }
};
