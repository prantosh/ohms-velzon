<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_cancellation_permissions', function (Blueprint $table) {

            $table->engine = 'InnoDB';
            $table->id();

            $table->unsignedBigInteger('invoice_id')->unique();
            $table->string('invoice_no', 30);

            $table->unsignedBigInteger('granted_by');
            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_cancellation_permissions');
    }
};
