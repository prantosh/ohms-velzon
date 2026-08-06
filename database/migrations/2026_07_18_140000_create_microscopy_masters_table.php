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
        Schema::create('microscopy_masters', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            // LONGTEXT (up to 1000+ characters) -- no DB-level unique index
            // (InnoDB's max index key is 3072 bytes, i.e. ~768 characters in
            // utf8mb4); uniqueness is enforced at the application layer only
            // (see MicroscopyMasterController's 'unique:microscopy_masters,name'
            // validation), matching note_masters' established convention.
            $table->longText('name');

            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');

            $table->unsignedBigInteger('created_by')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('microscopy_masters');
    }
};
