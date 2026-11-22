<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * invoices.payment_mode only ever holds the LATEST payment's mode, so a
 * Cash + UPI + Cash invoice loses the earlier modes. These columns keep the
 * mode of each payment next to its amount:
 *
 *   paid_amount (initial)  -> payment_mode_initial
 *   paid_amount_1          -> payment_mode_1
 *   paid_amount_2          -> payment_mode_2
 *
 * payment_mode itself stays as "latest mode" so existing readers keep working.
 * Backfilled from daily_transactions (the per-collection record).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'payment_mode_initial')) {
                $table->string('payment_mode_initial', 50)->nullable()->after('payment_mode');
            }
            if (!Schema::hasColumn('invoices', 'payment_mode_1')) {
                $table->string('payment_mode_1', 50)->nullable()->after('paid_date_1');
            }
            if (!Schema::hasColumn('invoices', 'payment_mode_2')) {
                $table->string('payment_mode_2', 50)->nullable()->after('paid_date_2');
            }
        });

        // Backfill from the ACTIVE RECEIVED rows of each diagnostic invoice.
        $base = fn () => DB::table('daily_transactions')
            ->where('transaction_type', 'RECEIVED')
            ->where('status', '!=', 'CANCELLED')
            ->whereNotNull('payment_mode');

        // Initial payment
        $base()->where('remarks', 'Diagnostic Invoice')
            ->orderBy('id')
            ->get(['invoice_reference', 'payment_mode'])
            ->each(function ($t) {
                DB::table('invoices')
                    ->where('invoice_no', $t->invoice_reference)
                    ->whereNull('payment_mode_initial')
                    ->update(['payment_mode_initial' => $t->payment_mode]);
            });

        foreach ([1, 2] as $phase) {
            $base()->where('remarks', 'like', "Diagnostic Due Payment - Phase {$phase}%")
                ->orderBy('id')
                ->get(['invoice_reference', 'payment_mode'])
                ->each(function ($t) use ($phase) {
                    DB::table('invoices')
                        ->where('invoice_no', $t->invoice_reference)
                        ->whereNotNull("paid_amount_{$phase}")
                        ->update(["payment_mode_{$phase}" => $t->payment_mode]);
                });
        }

        // Diagnostic invoices with no due phase and no matching initial row:
        // the single payment_mode IS the initial mode.
        DB::table('invoices')
            ->where('invoice_type', 'DIAGNOSTIC')
            ->whereNull('payment_mode_initial')
            ->whereNull('paid_amount_1')
            ->whereNull('paid_amount_2')
            ->whereNotNull('payment_mode')
            ->update(['payment_mode_initial' => DB::raw('payment_mode')]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['payment_mode_initial', 'payment_mode_1', 'payment_mode_2']);
        });
    }
};
