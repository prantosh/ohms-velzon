<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice_cancellation_permissions', function (Blueprint $table) {

            // Who is asking to cancel, and why -- captured up front now
            // instead of a Supervisor/Admin proactively granting blind.
            $table->unsignedBigInteger('requested_by')->nullable()->after('invoice_no');
            $table->text('reason')->nullable()->after('requested_by');

            // PENDING until a Supervisor/Admin acts on it, then GRANTED.
            // granted_at is separate from created_at, which now means
            // "requested at".
            $table->string('status', 20)->default('PENDING')->after('reason');
            $table->timestamp('granted_at')->nullable()->after('granted_by');

            $table->index('status', 'idx_invoice_cancellation_permissions_status');
        });

        // Existing rows all predate this workflow -- they were granted
        // directly by a Supervisor/Admin with no requester, so treat them
        // as already-settled GRANTED rows rather than leaving them PENDING.
        DB::table('invoice_cancellation_permissions')->update([
            'status' => 'GRANTED',
            'granted_at' => DB::raw('created_at'),
        ]);

        Schema::table('invoice_cancellation_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('granted_by')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_cancellation_permissions', function (Blueprint $table) {
            $table->dropIndex('idx_invoice_cancellation_permissions_status');
            $table->dropColumn(['requested_by', 'reason', 'status', 'granted_at']);
        });
    }
};
