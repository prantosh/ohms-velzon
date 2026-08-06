<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {

            if (!Schema::hasColumn('invoice_details', 'doctor_payment_waived')) {
                $table->boolean('doctor_payment_waived')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {

            if (Schema::hasColumn('invoice_details', 'doctor_payment_waived')) {
                $table->dropColumn('doctor_payment_waived');
            }
        });
    }
};
