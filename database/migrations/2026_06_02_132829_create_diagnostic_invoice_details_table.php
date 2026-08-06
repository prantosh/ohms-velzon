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
        if (!Schema::hasTable('diagnostic_invoice_details')) {

            Schema::create(
                'diagnostic_invoice_details',
                function (Blueprint $table) {

                    $table->id();

                    /*
                    |--------------------------------------------------------------------------
                    | HEADER REFERENCE
                    |--------------------------------------------------------------------------
                    */

                    $table->unsignedBigInteger('invoice_id');

                    /*
                    |--------------------------------------------------------------------------
                    | TEST INFORMATION
                    |--------------------------------------------------------------------------
                    */

                    $table->string(
                        'item_code',
                        30
                    );

                    $table->string(
                        'item_code_sub',
                        30
                    );

                    $table->string(
                        'test_name',
                        255
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | BILLING
                    |--------------------------------------------------------------------------
                    */

                    $table->decimal(
                        'rate',
                        12,
                        2
                    )->default(0);

                    $table->decimal(
                        'discount',
                        12,
                        2
                    )->default(0);

                    $table->decimal(
                        'amount',
                        12,
                        2
                    )->default(0);

                    /*
                    |--------------------------------------------------------------------------
                    | OPTIONAL
                    |--------------------------------------------------------------------------
                    */

                    $table->text(
                        'remarks'
                    )->nullable();

                    /*
                    |--------------------------------------------------------------------------
                    | AUDIT
                    |--------------------------------------------------------------------------
                    */

                    $table->unsignedBigInteger(
                        'created_by'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'updated_by'
                    )->nullable();

                    $table->timestamps();

                    /*
                    |--------------------------------------------------------------------------
                    | INDEXES
                    |--------------------------------------------------------------------------
                    */

                    $table->index(
                        'invoice_id',
                        'idx_diagnostic_invoice_id'
                    );

                    $table->index(
                        'item_code',
                        'idx_diagnostic_item_code'
                    );

                    $table->index(
                        'item_code_sub',
                        'idx_diagnostic_item_code_sub'
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | FOREIGN KEY
                    |--------------------------------------------------------------------------
                    */

                    $table->foreign(
                        'invoice_id'
                    )

                        ->references('id')

                        ->on('invoices')

                        ->onDelete('cascade');
                }
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (
            Schema::hasTable(
                'diagnostic_invoice_details'
            )
        ) {

            Schema::dropIfExists(
                'diagnostic_invoice_details'
            );
        }
    }
};
