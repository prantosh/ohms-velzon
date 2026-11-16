<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accounts adjustments: a dated, approved entry that clears a user's
     * cash deficit (cash paid out -- e.g. a doctor settlement -- that
     * exceeded the cash they collected). Not revenue. Plain table, no
     * foreign keys.
     */
    public function up(): void
    {
        if (Schema::hasTable('cash_adjustments')) {
            return;
        }

        Schema::create('cash_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_no', 30)->nullable();
            $table->unsignedBigInteger('user_id');
            // The deficit day this entry clears (shown on that day's slip).
            $table->date('adjustment_date');
            $table->decimal('amount', 12, 2);
            // ACCOUNTS_CASH | BANK_WITHDRAWAL | OWN_POCKET_REIMBURSEMENT | OTHER
            $table->string('source', 40);
            // E.g. the earlier deposit slip date this is adjusted against.
            $table->date('reference_date')->nullable();
            $table->string('reference_note', 255)->nullable();
            $table->string('remarks', 500);
            $table->string('status', 15)->default('ACTIVE'); // ACTIVE | CANCELLED
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name', 100)->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'adjustment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_adjustments');
    }
};
