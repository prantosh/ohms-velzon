<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('non_pathology_report_templates', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->string('title', 150);

            // invoice_item_details.item_code_sub -- single-item scoped,
            // mirrors usg_report_templates exactly (Non-Pathology's
            // findings table is already one row per billed line, so
            // multi-item bundling isn't needed here).
            $table->string('item_code_sub', 30);

            $table->text('clinical_history')->nullable();
            $table->longText('findings')->nullable();
            $table->longText('impression')->nullable();

            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->index('item_code_sub');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_pathology_report_templates');
    }
};
