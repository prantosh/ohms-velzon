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
        Schema::table('invoice_details', function (Blueprint $table) {

            $table->decimal(
                'initial_paid_amount',
                10,
                2
            )
                ->default(0)
                ->after('amount');

            $table->timestamp(
                'initial_paid_date'
            )
                ->nullable()
                ->after('initial_paid_amount');

            $table->decimal(
                'paid_amount_1',
                10,
                2
            )
                ->default(0)
                ->after('initial_paid_date');

            $table->timestamp(
                'paid_date_1'
            )
                ->nullable()
                ->after('paid_amount_1');

            $table->decimal(
                'paid_amount_2',
                10,
                2
            )
                ->default(0)
                ->after('paid_date_1');

            $table->timestamp(
                'paid_date_2'
            )
                ->nullable()
                ->after('paid_amount_2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {

            $table->dropColumn([
                'initial_paid_amount',
                'initial_paid_date',
                'paid_amount_1',
                'paid_date_1',
                'paid_amount_2',
                'paid_date_2',
            ]);
        });
    }
};
