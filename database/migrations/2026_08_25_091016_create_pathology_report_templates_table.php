<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('pathology_report_templates', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->string('title', 150);

            // invoice_item_details.test_group_code -- null means the
            // "Ungrouped/General" bucket (most Pathology sub-items don't
            // have a test group assigned yet). No DB-level FK: kept as a
            // plain indexed column for consistency with the rest of this
            // feature's tables, none of which use ->constrained().
            $table->unsignedBigInteger('test_group_code')->nullable();

            $table->longText('content')->nullable();

            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->index('test_group_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pathology_report_templates');
    }
};
