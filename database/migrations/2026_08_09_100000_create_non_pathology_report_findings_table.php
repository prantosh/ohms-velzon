<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        // Unrelated pre-existing bad legacy data elsewhere in this database
        // has repeatedly made MySQL strict mode reject ALTER/CREATE
        // operations touching tables with a foreign key to them (strict
        // mode revalidates broadly during a rebuild). Relaxed only for this
        // statement, restored right after; no data is touched.
        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode;

        DB::statement("SET SESSION sql_mode = ''");

        try {

            Schema::create('non_pathology_report_findings', function (Blueprint $table) {

                $table->id();

                // One report per billed line (not per invoice -- an invoice
                // with multiple Non-Pathology lines gets a separate report
                // per line), mirroring usg_report_findings. No FK constraint
                // to invoice_details -- that table is MyISAM (a pre-existing
                // core table, unrelated to this feature) and MySQL cannot
                // create a cross-engine foreign key against it; usg_report_findings
                // has the exact same limitation today (its own `constrained()`
                // call is silently dropped since it also ends up MyISAM by
                // default). The unique index below still enforces one report
                // per line at the app layer.
                $table->unsignedBigInteger('invoice_detail_id')->unique();

                $table->string('invoice_no');
                $table->string('item_code');
                $table->string('item_code_sub');
                $table->string('item_description')->nullable();

                $table->text('clinical_history')->nullable();
                $table->text('findings')->nullable();
                $table->text('impression')->nullable();

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
        Schema::dropIfExists('non_pathology_report_findings');
    }
};
