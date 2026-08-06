<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE invoices
            MODIFY invoice_type ENUM('DOCTOR_VISIT','DIAGNOSTIC','OXYGEN_RENT','CONCENTRATOR_RENT')
            NOT NULL DEFAULT 'DOCTOR_VISIT'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE invoices
            MODIFY invoice_type ENUM('DOCTOR_VISIT','DIAGNOSTIC')
            NOT NULL DEFAULT 'DOCTOR_VISIT'
        ");
    }
};
