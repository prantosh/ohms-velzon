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
        Schema::create('specialisations', function (Blueprint $table) {

            $table->id();

            $table->string('category', 30);

            $table->dateTime('created_dt')
                ->useCurrent();

            $table->integer('created_by')
                ->nullable();

            $table->dateTime('update_dt')
                ->nullable()
                ->useCurrentOnUpdate();

            $table->integer('update_by')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specialisations');
    }
};
