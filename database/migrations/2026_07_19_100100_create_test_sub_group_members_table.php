<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Each row maps one analyte group_name (a free-text value already used
     * on test_analytes.group_name) to the sub-group that clubs it.
     */
    public function up(): void
    {
        Schema::create('test_sub_group_members', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->unsignedBigInteger('sub_group_id');

            $table->string('group_name', 100);

            $table->timestamps();

            $table->index('sub_group_id');

            $table->unique(['sub_group_id', 'group_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_sub_group_members');
    }
};
