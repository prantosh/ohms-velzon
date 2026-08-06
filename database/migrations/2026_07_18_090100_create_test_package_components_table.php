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
        Schema::create('test_package_components', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->unsignedBigInteger('package_invoice_item_detail_id');

            $table->unsignedBigInteger('component_invoice_item_detail_id');

            $table->integer('sort_order')->default(0);

            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');

            $table->unsignedBigInteger('created_by')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->index('package_invoice_item_detail_id');
            $table->index('component_invoice_item_detail_id');

            $table->unique(
                ['package_invoice_item_detail_id', 'component_invoice_item_detail_id'],
                'test_package_components_unique_pair'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_package_components');
    }
};
