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
        Schema::create('backup_logs', function (Blueprint $table) {

            $table->bigIncrements('id');

            $table->string('file_name', 191)->nullable();
            $table->string('host', 191)->nullable();
            $table->string('database_name', 191)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('status', 20)->comment('SUCCESS, FAILED');
            $table->text('message')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->index('status', 'idx_backup_logs_status');
            $table->index('created_by', 'idx_backup_logs_created_by');

            $table->engine = 'InnoDB';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
    }
};
