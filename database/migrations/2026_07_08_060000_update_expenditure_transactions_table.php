<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('expenditure_transactions', function (Blueprint $table) {

            $table->dropColumn('transaction_no');
            $table->dropColumn('expenditure_by');
            $table->dropColumn('voucher_number');
        });

        Schema::table('expenditure_transactions', function (Blueprint $table) {

            $table->string('voucher_number')->unique()->after('id');

            $table->string('bill_number')->nullable()->after('cheque_date');

            $table->unsignedBigInteger('expenditure_by')->after('bill_number');
        });
    }

    public function down(): void
    {
        Schema::table('expenditure_transactions', function (Blueprint $table) {

            $table->dropColumn(['voucher_number', 'bill_number', 'expenditure_by']);
        });

        Schema::table('expenditure_transactions', function (Blueprint $table) {

            $table->string('transaction_no')->unique();
            $table->string('expenditure_by');
            $table->string('voucher_number')->nullable();
        });
    }
};
