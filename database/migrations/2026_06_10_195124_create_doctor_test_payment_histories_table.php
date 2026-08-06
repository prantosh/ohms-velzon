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
        Schema::create('doctor_test_payment_histories', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger(
                'doctor_test_payment_master_id'
            );

            $table->string(
                'doctor_code',
                20
            );

            $table->string(
                'item_code',
                20
            );

            $table->string(
                'item_code_sub',
                20
            );

            $table->string(
                'item_description_sub',
                255
            )->nullable();

            $table->string(
                'payment_type',
                20
            );

            $table->decimal(
                'old_value',
                10,
                2
            );

            $table->decimal(
                'new_value',
                10,
                2
            );

            $table->dateTime(
                'changed_on'
            );

            $table->unsignedBigInteger(
                'changed_by'
            );

            $table->timestamps();

            $table->index(
                'doctor_test_payment_master_id',
                'idx_payment_history_master'
            );

            $table->index(
                'doctor_code',
                'idx_payment_history_doctor'
            );

            $table->index(
                'item_code_sub',
                'idx_payment_history_test'
            );

            $table->index(
                'changed_on',
                'idx_payment_history_changed_on'
            );

            $table->foreign(
                'doctor_test_payment_master_id',
                'fk_payment_history_master'
            )
                ->references('id')
                ->on('doctor_test_payment_masters')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'doctor_test_payment_histories'
        );
    }
};
