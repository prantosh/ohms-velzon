<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('test_report_confirmations', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->string('invoice_no', 30)->unique();

            $table->unsignedBigInteger('confirmed_by')->nullable();

            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_report_confirmations');
    }
};
