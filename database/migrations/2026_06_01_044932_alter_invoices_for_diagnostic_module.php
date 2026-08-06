<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            $table->enum('invoice_type', [
                'DOCTOR_VISIT',
                'DIAGNOSTIC'
            ])->default('DOCTOR_VISIT')
                ->after('invoice_no');

            $table->string('reference_no', 50)
                ->nullable()
                ->after('appointment_no');

            $table->unsignedBigInteger('referred_by')
                ->nullable()
                ->after('doctor_id');

            $table->string('referred_name')
                ->nullable()
                ->after('referred_by');

            $table->decimal('discount_percent', 5, 2)
                ->default(0)
                ->after('discount');

            $table->string('transaction_no', 100)
                ->nullable()
                ->after('payment_mode');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            $table->dropColumn([
                'invoice_type',
                'reference_no',
                'referred_by',
                'referred_name',
                'discount_percent',
                'transaction_no'
            ]);
        });
    }
};
