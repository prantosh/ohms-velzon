<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('pathology_report_finding_items', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            // No DB-level FK -- see pathology_report_templates.
            $table->unsignedBigInteger('pathology_report_finding_id');

            // invoice_details is MyISAM on production -- no FK (see
            // usg_report_findings.invoice_detail_id, the proven pattern).
            // Unique: each billed line belongs to at most one report, which
            // is what makes partial/staggered completion work -- an item
            // not yet claimed by any finding stays selectable for a later
            // template pick.
            $table->unsignedBigInteger('invoice_detail_id')->unique();

            $table->string('item_code_sub', 30);

            $table->timestamps();

            $table->index('pathology_report_finding_id', 'prfi_finding_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pathology_report_finding_items');
    }
};
