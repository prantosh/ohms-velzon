<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('test_result_entries', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->string('invoice_no', 30);

            $table->unsignedBigInteger('invoice_detail_id')->unique();

            $table->string('item_code', 30);

            $table->string('item_code_sub', 30);

            $table->string('item_description', 255)->nullable();

            $table->string('uom', 30)->nullable();

            $table->string('range_male', 50)->nullable();

            $table->string('range_female', 50)->nullable();

            $table->string('range_common', 50)->nullable();

            $table->string('method', 100)->nullable();

            $table->string('result_value', 191)->nullable();

            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->index('invoice_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_result_entries');
    }
};
