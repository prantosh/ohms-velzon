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
        Schema::table('doctor_payables', function (Blueprint $table) {

            $table->unsignedBigInteger('settlement_id')
                ->nullable()
                ->after('payment_status');

            $table->date('first_settlement_date')
                ->nullable()
                ->after('settlement_id');

            $table->string('last_settlement_no', 30)
                ->nullable()
                ->after('first_settlement_date');

            $table->date('last_settlement_date')
                ->nullable()
                ->after('last_settlement_no');

            $table->unsignedInteger('settlement_count')
                ->default(0)
                ->after('last_settlement_date');

            // Indexes
            $table->index('settlement_id');
            $table->index('last_settlement_no');
            $table->index('last_settlement_date');

            // Foreign Key
            $table->foreign('settlement_id')
                ->references('id')
                ->on('doctor_settlements')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_payables', function (Blueprint $table) {

            $table->dropForeign(['settlement_id']);

            $table->dropIndex(['settlement_id']);
            $table->dropIndex(['last_settlement_no']);
            $table->dropIndex(['last_settlement_date']);

            $table->dropColumn([
                'settlement_id',
                'first_settlement_date',
                'last_settlement_no',
                'last_settlement_date',
                'settlement_count'
            ]);
        });
    }
};
