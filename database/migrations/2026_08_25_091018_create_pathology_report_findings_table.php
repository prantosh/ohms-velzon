<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('pathology_report_findings', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->string('invoice_no', 30);

            // No DB-level FK -- see pathology_report_templates. Nullable:
            // staff can start a report from a blank document without
            // picking a template.
            $table->unsignedBigInteger('pathology_report_template_id')->nullable();

            $table->longText('content')->nullable();

            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->index('invoice_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pathology_report_findings');
    }
};
