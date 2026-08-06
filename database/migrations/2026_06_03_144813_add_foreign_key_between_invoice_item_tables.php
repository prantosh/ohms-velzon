<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            ALTER TABLE invoice_item_masters
            ENGINE=InnoDB
        ");

        DB::statement("
            ALTER TABLE invoice_item_details
            ENGINE=InnoDB
        ");

        Schema::table('invoice_item_details', function (Blueprint $table) {

            $table->index(
                'item_code',
                'idx_invoice_item_details_item_code'
            );

            $table->foreign('item_code')
                ->references('item_code')
                ->on('invoice_item_masters')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_item_details', function (Blueprint $table) {

            $table->dropForeign([
                'item_code'
            ]);

            $table->dropIndex(
                'idx_invoice_item_details_item_code'
            );
        });
    }
};
