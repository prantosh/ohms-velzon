<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('daily_transactions', function (Blueprint $table) {

            // 4-digit code staff key in for a Card/UPI payment (last 4
            // digits of a card, or the last 4 of a UPI ref no) -- not
            // required for Cash. Named distinctly from the existing
            // reference_no column (already used to link a settlement's
            // ledger row back to its settlement_no) to avoid colliding
            // with that unrelated meaning.
            $table->string('payment_reference', 10)->nullable()->after('payment_mode');
        });
    }

    public function down(): void
    {
        Schema::table('daily_transactions', function (Blueprint $table) {
            $table->dropColumn('payment_reference');
        });
    }
};
