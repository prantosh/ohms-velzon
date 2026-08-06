<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Superseded by test_sub_groups/test_sub_group_members -- a single
     * free-text field per test couldn't represent "club several analyte
     * groups together" or "one test can have multiple sub-groups".
     */
    public function up(): void
    {
        Schema::table('invoice_item_details', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_item_details', 'sub_group')) {
                $table->dropColumn('sub_group');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_item_details', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_item_details', 'sub_group')) {
                $table->string('sub_group', 100)->nullable()->after('test_group_code');
            }
        });
    }
};
