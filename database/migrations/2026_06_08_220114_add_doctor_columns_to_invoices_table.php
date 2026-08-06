<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            $table->string('doctor_code', 20)
                ->nullable()
                ->after('doctor_id');

            $table->string('doctor_name', 150)
                ->nullable()
                ->after('doctor_code');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            $table->dropColumn([
                'doctor_code',
                'doctor_name'
            ]);
        });
    }
};
