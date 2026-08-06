<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(
            'doctor_visit_invoices',
            function (Blueprint $table) {

                $table->boolean(
                    'is_card_holder'
                )->default(0);

                $table->string(
                    'card_number'
                )->nullable();
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_visit_invoices', function (Blueprint $table) {
            //
        });
    }
};
