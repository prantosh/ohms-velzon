<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The same report item can now be tracked under more than one group --
 * e.g. HB / TLC / DLC under both CBC (PAT150) and Blood RE (PAT144), each
 * with its own order and section heading. Patient history stays keyed by
 * item_code_sub alone, so an HB result from either report is the same
 * history. Unique per (item, group) instead of per item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('key_parameters', function (Blueprint $table) {
            $table->dropUnique(['item_code_sub']);
            $table->unique(['item_code_sub', 'group_item_code_sub'], 'key_parameters_item_group_unique');
        });
    }

    public function down(): void
    {
        Schema::table('key_parameters', function (Blueprint $table) {
            $table->dropUnique('key_parameters_item_group_unique');
            $table->unique('item_code_sub');
        });
    }
};
