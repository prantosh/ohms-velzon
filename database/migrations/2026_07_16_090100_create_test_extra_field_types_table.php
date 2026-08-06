<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('test_extra_field_types', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->string('field_name', 100)->unique();

            $table->enum('input_type', ['TEXT', 'TEXTAREA'])->default('TEXT');

            $table->integer('sort_order')->default(0);

            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');

            $table->unsignedBigInteger('created_by')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
        });

        $now = now();

        $seedFields = [
            ['field_name' => 'Instrument Used', 'input_type' => 'TEXT'],
            ['field_name' => 'Kit Used', 'input_type' => 'TEXT'],
            ['field_name' => 'Sensitivity', 'input_type' => 'TEXT'],
            ['field_name' => 'LOT No', 'input_type' => 'TEXT'],
            ['field_name' => 'Site', 'input_type' => 'TEXT'],
            ['field_name' => 'ASPIRATE', 'input_type' => 'TEXT'],
            ['field_name' => 'Specimen', 'input_type' => 'TEXT'],
            ['field_name' => 'Note', 'input_type' => 'TEXTAREA'],
            ['field_name' => 'Comment', 'input_type' => 'TEXTAREA'],
            ['field_name' => 'Interpretation', 'input_type' => 'TEXTAREA'],
            ['field_name' => 'Microscopy', 'input_type' => 'TEXTAREA'],
            ['field_name' => 'Impression', 'input_type' => 'TEXTAREA'],
        ];

        foreach ($seedFields as $index => $field) {

            DB::table('test_extra_field_types')->insert([

                'field_name' => $field['field_name'],
                'input_type' => $field['input_type'],
                'sort_order' => $index + 1,
                'status' => 'ACTIVE',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_extra_field_types');
    }
};
