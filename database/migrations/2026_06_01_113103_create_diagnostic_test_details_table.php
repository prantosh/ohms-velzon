<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
{
    if (!Schema::hasTable('diagnostic_test_details')) {

        Schema::create('diagnostic_test_details', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('invoice_id');

            $table->string('item_code',30);

            $table->string('item_code_sub',30);

            $table->string('test_name');

            $table->decimal('rate',10,2)->default(0);

            $table->decimal('discount',10,2)->default(0);

            $table->decimal('amount',10,2)->default(0);

            $table->integer('report_days')->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_test_details');
    }
};
