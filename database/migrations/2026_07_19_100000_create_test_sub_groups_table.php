<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * One test (invoice_item_details row) can define several sub-groups,
     * each clubbing together a subset of that same test's existing analyte
     * group names (test_analytes.group_name) -- e.g. Sub Group "Liver Panel"
     * = analyte groups A + B.
     */
    public function up(): void
    {
        Schema::create('test_sub_groups', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->unsignedBigInteger('invoice_item_detail_id');

            $table->string('name', 150);

            $table->integer('sort_order')->default(0);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->index('invoice_item_detail_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_sub_groups');
    }
};
