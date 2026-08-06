<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            if (!Schema::hasColumn('invoices', 'cancellation_approved_by')) {
                $table->unsignedBigInteger('cancellation_approved_by')->nullable()->after('cancelled_at');
            }

            if (!Schema::hasColumn('invoices', 'cancellation_remarks')) {
                $table->text('cancellation_remarks')->nullable()->after('cancellation_approved_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            $columns = ['cancellation_approved_by', 'cancellation_remarks'];

            foreach ($columns as $column) {

                if (Schema::hasColumn('invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
