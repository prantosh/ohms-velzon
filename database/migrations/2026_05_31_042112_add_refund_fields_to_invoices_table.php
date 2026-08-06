<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            

           

           

            $table->decimal('refund_amount', 12, 2)
                ->default(0)
                ->after('cancelled_at');

            $table->date('refund_date')
                ->nullable()
                ->after('refund_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            $table->dropColumn([
                'cancelled',
                'cancelled_by',
                'cancelled_at',
                'refund_amount',
                'refund_date'
            ]);
        });
    }
};
