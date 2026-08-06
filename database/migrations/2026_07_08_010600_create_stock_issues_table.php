<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_issues', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->string('issue_no')->unique();

            $table->date('issue_date');

            $table->unsignedBigInteger('issued_to')->nullable();

            $table->string('issued_to_name')->nullable();

            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_issues');
    }
};
