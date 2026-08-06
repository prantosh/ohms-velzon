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
            'invoice_item_masters',

            function (Blueprint $table) {

                $table->id();

                /*
                |--------------------------------------------------------------------------
                | ITEM MASTER
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'item_code',
                    30
                )->unique();

                $table->string(
                    'item_name'
                );

                $table->string(
                    'item_type',
                    50
                )->nullable();

                $table->text(
                    'description'
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
                | INDEX
                |--------------------------------------------------------------------------
                */

                $table->index(
                    'item_code'
                );

                $table->index(
                    'item_name'
                );
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'invoice_item_masters'
        );
    }
};
