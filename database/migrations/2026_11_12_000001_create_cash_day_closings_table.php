<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per user per day recording how that day's cash-in-hand ended:
     * a positive balance is treated as deposited to the accountant, a
     * negative balance (a deficit) is carried forward as the next day's
     * opening balance. Plain table, no foreign keys (see the MyISAM note in
     * the project memory -- keep new tables FK-free).
     */
    public function up(): void
    {
        if (Schema::hasTable('cash_day_closings')) {
            return;
        }

        Schema::create('cash_day_closings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('closing_date');
            $table->decimal('closing_balance', 12, 2)->default(0);
            $table->decimal('deposited_amount', 12, 2)->default(0);
            $table->decimal('carried_forward', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'closing_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_day_closings');
    }
};
