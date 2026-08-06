<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Make both tables identical for FK
        |--------------------------------------------------------------------------
        */

        DB::statement("
            ALTER TABLE invoice_item_masters
            ENGINE=InnoDB
        ");

        DB::statement("
            ALTER TABLE invoice_item_details
            ENGINE=InnoDB
        ");

        DB::statement("
            ALTER TABLE invoice_item_masters
            MODIFY item_code VARCHAR(6)
            CHARACTER SET utf8mb4
            COLLATE utf8mb4_unicode_ci
            NOT NULL
        ");

        DB::statement("
            ALTER TABLE invoice_item_details
            MODIFY item_code VARCHAR(6)
            CHARACTER SET utf8mb4
            COLLATE utf8mb4_unicode_ci
            NOT NULL
        ");

        /*
        |--------------------------------------------------------------------------
        | Remove orphan child records if any
        |--------------------------------------------------------------------------
        */

        DB::statement("
            DELETE d
            FROM invoice_item_details d
            LEFT JOIN invoice_item_masters m
                ON d.item_code = m.item_code
            WHERE m.item_code IS NULL
        ");

        /*
        |--------------------------------------------------------------------------
        | Create Foreign Key
        |--------------------------------------------------------------------------
        */

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
        DB::statement("
            ALTER TABLE invoice_item_details
            DROP FOREIGN KEY fk_invoice_item_details_item_code
        ");
    }
};
