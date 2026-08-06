<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * doctor_code, doctor_name, specialisation, gender stay mandatory.
     * Every other column becomes nullable (defaults, where they existed,
     * are preserved).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE doctors MODIFY gender ENUM('M','F','O') NOT NULL");
        DB::statement("ALTER TABLE doctors MODIFY specialisation VARCHAR(191) NOT NULL");

        DB::statement("ALTER TABLE doctors MODIFY consultation_fee_total DECIMAL(10,2) NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE doctors MODIFY consultation_fee_doctor DECIMAL(10,2) NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE doctors MODIFY consultation_fee_doctor_discounted_patient DECIMAL(10,2) NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE doctors MODIFY discount DECIMAL(10,2) NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE doctors MODIFY status ENUM('Active','Inactive') NULL DEFAULT 'Active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE doctors MODIFY gender ENUM('M','F','O') NULL");
        DB::statement("ALTER TABLE doctors MODIFY specialisation VARCHAR(191) NULL");

        DB::statement("ALTER TABLE doctors MODIFY consultation_fee_total DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE doctors MODIFY consultation_fee_doctor DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE doctors MODIFY consultation_fee_doctor_discounted_patient DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE doctors MODIFY discount DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE doctors MODIFY status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active'");
    }
};
