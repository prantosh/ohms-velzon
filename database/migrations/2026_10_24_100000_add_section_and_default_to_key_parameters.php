<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * section_label: an optional sub-heading within a group (e.g. Urine RE's
 * PHYSICAL / CHEMICAL / MICROSCOPIC). It's not a parameter of its own --
 * KeyParameterService::buildAutoTable() prints it as a full-width heading
 * row wherever it changes between consecutive (sort_order) rows, and the
 * result-entry screen groups the inputs under it.
 *
 * default_value: pre-filled into an empty Key Parameters input on a new
 * report (e.g. "Nil" / "Negative"), so staff only change the abnormal ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('key_parameters', function (Blueprint $table) {
            $table->string('section_label', 60)->nullable()->after('group_item_code_sub');
            $table->string('default_value', 100)->nullable()->after('uom');
        });
    }

    public function down(): void
    {
        Schema::table('key_parameters', function (Blueprint $table) {
            $table->dropColumn(['section_label', 'default_value']);
        });
    }
};
