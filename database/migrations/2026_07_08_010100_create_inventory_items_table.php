<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->string('item_code')->unique();

            $table->string('item_name');

            $table->string('uom');

            $table->foreignId('inventory_category_id')
                ->nullable()
                ->constrained('inventory_categories')
                ->nullOnDelete();

            $table->decimal('opening_stock', 12, 2)->default(0);

            $table->decimal('opening_value', 12, 2)->default(0);

            $table->decimal('current_stock', 12, 2)->default(0);

            $table->decimal('avg_rate', 10, 2)->default(0);

            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');

            $table->unsignedBigInteger('created_by')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
