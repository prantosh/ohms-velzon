<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Brings an expenditure_transactions table that still has the OLD layout in line with
     * what the Expenditure Entry screen expects.
     *
     * Old layout: no expenditure_agency_id / bill_number / updated_by / updated_at, and the
     * column called expenditure_by actually held the PAID TO agency id (expenditure_agencies.id).
     * Narrow columns too: amount int, voucher_number varchar(15), remarks varchar(30) not null...
     *
     * New layout (what the code reads and writes): Paid To in expenditure_agency_id,
     * expenditure_by = the USER who spent the money, decimal amount, wide text columns.
     *
     * Old rows keep their Paid To (copied into expenditure_agency_id); their old
     * expenditure_by value is kept in legacy_expenditure_by and the column itself is cleared,
     * since it never was a user. A table that already has expenditure_agency_id is left alone.
     */
    public function up(): void
    {
        $t = 'expenditure_transactions';

        if (!Schema::hasTable($t) || Schema::hasColumn($t, 'expenditure_agency_id')) {
            return;
        }

        // legacy rows carry zero dates ('0000-00-00'), which a strict ALTER refuses
        $oldMode = DB::selectOne('select @@SESSION.sql_mode as m')->m;
        DB::statement("SET SESSION sql_mode=''");

        try {

            DB::statement("ALTER TABLE `$t`
                ADD COLUMN `expenditure_agency_id` BIGINT UNSIGNED NULL AFTER `expenditure_category_id`,
                ADD COLUMN `bill_number` VARCHAR(50) NULL,
                ADD COLUMN `updated_by` BIGINT UNSIGNED NULL,
                ADD COLUMN `updated_at` TIMESTAMP NULL,
                ADD COLUMN `legacy_expenditure_by` INT NULL,
                ADD INDEX `exp_tx_agency_idx` (`expenditure_agency_id`)");

            DB::statement("ALTER TABLE `$t`
                MODIFY `amount` DECIMAL(12,2) NOT NULL,
                MODIFY `remarks` TEXT NULL,
                MODIFY `voucher_number` VARCHAR(40) NULL,
                MODIFY `payment_mode` VARCHAR(20) NULL,
                MODIFY `cheque_number` VARCHAR(50) NULL,
                MODIFY `bank_name` VARCHAR(150) NULL,
                MODIFY `expenditure_by` BIGINT UNSIGNED NULL,
                MODIFY `created_by` BIGINT UNSIGNED NULL");

            // keep the old value, then give Paid To its proper column
            DB::statement("UPDATE `$t` SET `legacy_expenditure_by` = `expenditure_by`");

            DB::statement("UPDATE `$t` x
                JOIN `expenditure_agencies` a ON a.`id` = x.`legacy_expenditure_by`
                SET x.`expenditure_agency_id` = a.`id`");

            // it was never a user: clear it (the screen now stores the real spender there)
            DB::statement("UPDATE `$t` SET `expenditure_by` = NULL");

            // modes were stored upper case; the screen's list is Cash / Card / UPI / Bank / Cheque
            DB::statement("UPDATE `$t` SET `payment_mode` = 'Cash' WHERE UPPER(`payment_mode`) = 'CASH'");
            DB::statement("UPDATE `$t` SET `payment_mode` = 'Cheque' WHERE UPPER(`payment_mode`) = 'CHEQUE'");

            DB::statement("UPDATE `$t` SET `cheque_date` = NULL WHERE `cheque_date` = '0000-00-00'");

        } finally {

            DB::statement('SET SESSION sql_mode = ?', [$oldMode]);
        }
    }

    public function down(): void
    {
        $t = 'expenditure_transactions';

        if (!Schema::hasTable($t) || !Schema::hasColumn($t, 'legacy_expenditure_by')) {
            return;
        }

        $oldMode = DB::selectOne('select @@SESSION.sql_mode as m')->m;
        DB::statement("SET SESSION sql_mode=''");

        try {

            DB::statement("UPDATE `$t` SET `expenditure_by` = `legacy_expenditure_by`");

            DB::statement("ALTER TABLE `$t`
                DROP INDEX `exp_tx_agency_idx`,
                DROP COLUMN `expenditure_agency_id`,
                DROP COLUMN `bill_number`,
                DROP COLUMN `updated_by`,
                DROP COLUMN `updated_at`,
                DROP COLUMN `legacy_expenditure_by`");

        } finally {

            DB::statement('SET SESSION sql_mode = ?', [$oldMode]);
        }
    }
};
