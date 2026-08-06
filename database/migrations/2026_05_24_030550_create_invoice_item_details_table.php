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
        Schema::create(
            'invoice_item_details',

            function (Blueprint $table) {

                $table->id();

                /*
                |--------------------------------------------------------------------------
                | MASTER LINK
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'item_code',
                    30
                );

                /*
                |--------------------------------------------------------------------------
                | SUB ITEM
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'sub_item_code',
                    30
                )->unique();

                $table->string(
                    'sub_item_name'
                );

                /*
                |--------------------------------------------------------------------------
                | RATE
                |--------------------------------------------------------------------------
                */

                $table->decimal(
                    'rate',
                    10,
                    2
                )->default(0);

                $table->decimal(
                    'discount_percent',
                    5,
                    2
                )->default(0);

                $table->decimal(
                    'member_discount',
                    10,
                    2
                )->default(0);

                $table->decimal(
                    'other_lab_discount',
                    10,
                    2
                )->default(0);

                $table->decimal(
                    'final_rate',
                    10,
                    2
                )->default(0);

                /*
                |--------------------------------------------------------------------------
                | REPORT
                |--------------------------------------------------------------------------
                */

                $table->integer(
                    'report_days'
                )->default(0);

                $table->text(
                    'remarks'
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | STATUS
                |--------------------------------------------------------------------------
                */

                $table->boolean(
                    'status'
                )->default(1);

                $table->timestamps();

                /*
                |--------------------------------------------------------------------------
                | INDEXES
                |--------------------------------------------------------------------------
                */

                $table->index(
                    'item_code'
                );

                $table->index(
                    'sub_item_code'
                );

                $table->index(
                    'sub_item_name'
                );

                /*
                |--------------------------------------------------------------------------
                | FOREIGN KEY
                |--------------------------------------------------------------------------
                */

                $table->foreign(
                    'item_code'
                )

                    ->references(
                        'item_code'
                    )

                    ->on(
                        'invoice_item_masters'
                    )

                    ->onDelete('cascade');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'invoice_item_details'
        );
    }
};
