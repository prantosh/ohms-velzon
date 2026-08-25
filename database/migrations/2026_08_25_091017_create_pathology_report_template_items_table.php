<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('pathology_report_template_items', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            // No DB-level FK -- see pathology_report_templates.
            $table->unsignedBigInteger('pathology_report_template_id');

            // invoice_item_details.item_code_sub. A single-item template
            // has one row here; a multi-item panel template has several --
            // this is what "the template covers these items" means.
            $table->string('item_code_sub', 30);

            $table->timestamps();

            $table->index('pathology_report_template_id', 'prti_template_id_idx');
            $table->index('item_code_sub');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pathology_report_template_items');
    }
};
