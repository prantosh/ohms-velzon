<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role VARCHAR(50) NOT NULL DEFAULT 'Employee'");

        DB::statement("ALTER TABLE role_page_access MODIFY role VARCHAR(50) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('Admin','Member','Employee') NOT NULL DEFAULT 'Employee'");

        DB::statement("ALTER TABLE role_page_access MODIFY role ENUM('Admin','Member','Employee') NOT NULL");
    }
};
