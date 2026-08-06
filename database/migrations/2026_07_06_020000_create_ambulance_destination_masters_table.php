<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ambulance_destination_masters', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->string('destination_code', 20)->unique();

            $table->string('destination_name', 150)->unique();

            $table->decimal('fare_ac', 10, 2)->default(0);

            $table->decimal('fare_nonac', 10, 2)->default(0);

            $table->text('remarks')->nullable();

            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');

            $table->unsignedBigInteger('created_by')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambulance_destination_masters');
    }
};
