<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        // See create_usg_report_findings_table for why sql_mode is relaxed
        // here -- unrelated legacy data elsewhere makes strict mode reject
        // this CREATE otherwise. No data is touched.
        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode;

        DB::statement("SET SESSION sql_mode = ''");

        try {

            Schema::create('cardiology_report_findings', function (Blueprint $table) {

                $table->id();

                // One report per billed Echo line (not per invoice -- an
                // invoice with multiple Echo studies gets a separate report
                // per study, mirroring usg_report_findings). No DB-level FK
                // to invoice_details -- that table is MyISAM in production
                // and cannot support one (usg_report_findings, its working
                // precedent, is a plain unique column for the same reason).
                $table->unsignedBigInteger('invoice_detail_id')->unique();

                $table->string('invoice_no');
                $table->string('item_code_sub');
                $table->string('item_description')->nullable();

                $table->text('heading')->nullable();
                $table->text('m_mode_data')->nullable();
                $table->text('doppler_data')->nullable();
                $table->text('left_ventricle')->nullable();
                $table->text('left_atrium')->nullable();
                $table->text('right_ventricle_atrium')->nullable();
                $table->text('mitral_valve')->nullable();
                $table->text('aortic_valve')->nullable();
                $table->text('tricuspid_valve')->nullable();
                $table->text('pulmonary_valve')->nullable();
                $table->text('inter_ventricular_septum')->nullable();
                $table->text('inter_atrial_septum')->nullable();
                $table->text('pericardium')->nullable();
                $table->text('others')->nullable();
                $table->longText('conclusion')->nullable();
                $table->longText('remarks')->nullable();

                $table->unsignedBigInteger('confirmed_by')->nullable();
                $table->timestamp('confirmed_at')->nullable();

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();

                $table->timestamps();

                $table->index('invoice_no');
            });

        } finally {

            DB::statement("SET SESSION sql_mode = '{$originalSqlMode}'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cardiology_report_findings');
    }
};
