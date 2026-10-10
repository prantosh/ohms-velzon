<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasTable('xray_report_uploads')) {
            return;
        }

        Schema::create('xray_report_uploads', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            // One externally-generated X-Ray PDF per invoice (a re-upload
            // replaces it). No DB-level FK -- invoices/invoice_details are
            // not reliably InnoDB on production (see invoice_details).
            $table->string('invoice_no', 30)->unique();

            // File name inside public/invoices, e.g.
            // LAB-1026-096-0018-xray-report.pdf
            $table->string('file_name', 120);

            $table->string('original_name', 255)->nullable();

            $table->unsignedBigInteger('size_bytes')->nullable();

            $table->unsignedBigInteger('uploaded_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xray_report_uploads');
    }
};
