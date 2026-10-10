<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Nearly every report/dashboard query joins billed lines to their item
     * on (item_code, item_code_sub), but invoice_item_details was only
     * indexed on item_code, so each join scanned every sub-item of the
     * code (hundreds under PAT001). Idempotent.
     */
    public function up(): void
    {
        $exists = collect(DB::select("SHOW INDEX FROM invoice_item_details WHERE Key_name = 'idx_iid_item_code_sub'"))->isNotEmpty();

        if ($exists) {
            return;
        }

        Schema::table('invoice_item_details', function (Blueprint $table) {
            $table->index(['item_code', 'item_code_sub'], 'idx_iid_item_code_sub');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_item_details', function (Blueprint $table) {
            $table->dropIndex('idx_iid_item_code_sub');
        });
    }
};
