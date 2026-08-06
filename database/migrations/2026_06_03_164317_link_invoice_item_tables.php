<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Drop existing FK if present

        try {

            DB::statement("
                ALTER TABLE invoice_item_details
                DROP FOREIGN KEY invoice_item_details_item_code_foreign
            ");

        } catch (\Exception $e) {
        }

        try {

            DB::statement("
                ALTER TABLE invoice_item_details
                DROP FOREIGN KEY fk_invoice_item_details_item_code
            ");

        } catch (\Exception $e) {
        }

        // Create FK

        DB::statement("
            ALTER TABLE invoice_item_details
            ADD CONSTRAINT fk_invoice_item_details_item_code
            FOREIGN KEY (item_code)
            REFERENCES invoice_item_masters(item_code)
            ON UPDATE CASCADE
            ON DELETE RESTRICT
        ");
    }

    public function down(): void
    {
        try {

            DB::statement("
                ALTER TABLE invoice_item_details
                DROP FOREIGN KEY fk_invoice_item_details_item_code
            ");

        } catch (\Exception $e) {
        }
    }
};
