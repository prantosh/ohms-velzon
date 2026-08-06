<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reporting_group_items', function (Blueprint $table) {

            $table->engine = 'InnoDB';
            $table->id();

            $table->unsignedBigInteger('reporting_group_id');
            $table->string('item_code', 6)->unique();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('reporting_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_group_items');
    }
};
