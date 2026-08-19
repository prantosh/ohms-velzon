<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        // See create_usg_report_templates_table for why sql_mode is relaxed
        // here -- unrelated legacy data elsewhere makes strict mode reject
        // this CREATE otherwise. No data is touched.
        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode;

        DB::statement("SET SESSION sql_mode = ''");

        try {

            Schema::create('cardiology_report_templates', function (Blueprint $table) {

                $table->id();

                // Shown in the picker dropdown, e.g. "Normal Study".
                $table->string('title', 150);

                // Which Echo study this applies to (invoice_item_details.item_code_sub,
                // one of the CardiologyReportFields::ITEM_CODE_SUBS values).
                $table->string('item_code_sub', 30);

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

                $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();

                $table->timestamps();

                $table->index('item_code_sub');
            });

        } finally {

            DB::statement("SET SESSION sql_mode = '{$originalSqlMode}'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cardiology_report_templates');
    }
};
