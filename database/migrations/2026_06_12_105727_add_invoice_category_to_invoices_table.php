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
        Schema::table('invoices', function (Blueprint $table) {

            $table->enum(
                'invoice_category',
                [
                    'PATHOLOGY',
                    'NON_PATHOLOGY'
                ]
            )
                ->nullable()
                ->after('invoice_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            $table->dropColumn(
                'invoice_category'
            );
        });
    }
};
