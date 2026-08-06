<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('test_analytes', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->unsignedBigInteger('invoice_item_detail_id');

            $table->string('analyte_name', 150);

            $table->string('uom', 30)->nullable();

            $table->string('range_male', 100)->nullable();

            $table->string('range_female', 100)->nullable();

            $table->string('range_common', 300)->nullable();

            $table->string('method', 100)->nullable();

            $table->integer('sort_order')->default(0);

            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');

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
        Schema::dropIfExists('test_analytes');
    }
};
