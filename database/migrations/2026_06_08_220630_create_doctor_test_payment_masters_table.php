<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('doctor_test_payment_masters', function (Blueprint $table) {

            $table->id();

            $table->string('doctor_code', 20);

            $table->string('item_code', 20);

            $table->string('item_code_sub', 20);

            $table->enum(
                'payment_type',
                ['FIXED', 'PERCENTAGE']
            );

            $table->decimal(
                'payment_value',
                10,
                2
            );

            $table->date('effective_from');

            $table->date('effective_to')
                ->nullable();

            $table->char('status', 1)
                ->default('A');

            $table->unsignedBigInteger('created_by')
                ->nullable();

            $table->unsignedBigInteger('updated_by')
                ->nullable();

            $table->timestamps();

            $table->index('doctor_code');
            $table->index('item_code');
            $table->index('item_code_sub');

            $table->unique([
                'doctor_code',
                'item_code_sub',
                'effective_from'
            ], 'uk_doctor_test_payment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'doctor_test_payment_masters'
        );
    }
};
