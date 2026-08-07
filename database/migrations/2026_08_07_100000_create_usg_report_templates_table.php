<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        // Unrelated pre-existing bad legacy data elsewhere in this database
        // has repeatedly made MySQL strict mode reject CREATE/ALTER
        // operations on unrelated tables (strict mode revalidates broadly
        // during a rebuild). Relaxed only for this statement, restored
        // right after; no data is touched.
        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode;

        DB::statement("SET SESSION sql_mode = ''");

        try {

            Schema::create('usg_report_templates', function (Blueprint $table) {

                $table->id();

                // Shown in the picker dropdown, e.g. "Normal Study".
                $table->string('title', 150);

                // Which USG study this applies to (invoice_item_details.item_code_sub) --
                // required, not a generic/cross-study template.
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

        } finally {

            DB::statement("SET SESSION sql_mode = '{$originalSqlMode}'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usg_report_templates');
    }
};
