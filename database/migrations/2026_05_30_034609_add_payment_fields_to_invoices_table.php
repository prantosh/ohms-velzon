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
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('paid_amount_1', 10, 2)
                ->nullable()
                ->after('paid_amount');

            $table->date('paid_date_1')
                ->nullable()
                ->after('paid_amount_1');

            $table->decimal('paid_amount_2', 10, 2)
                ->nullable()
                ->after('paid_date_1');

            $table->date('paid_date_2')
                ->nullable()
                ->after('paid_amount_2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'paid_amount_1',
                'paid_date_1',
                'paid_amount_2',
                'paid_date_2',
            ]);
        });
    }
};
