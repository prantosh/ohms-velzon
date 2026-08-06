<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MembershipFeeController and IncomeController used to hardcode their own
 * item codes (MEMFEE01, INCOTH01) instead of the MEM001/DON001 master
 * records that already existed for the same purpose (item_type MEMBERSHIP/
 * DONATION) but were never wired up -- so every membership fee and
 * donation transaction landed in "Unassigned" on cash reports, since
 * MEMFEE01/INCOTH01 had no reporting_group_items mapping.
 *
 * The controllers now write MEM001/DON001 going forward (see
 * MembershipFeeController::ITEM_CODE, IncomeController::ITEM_CODE). This
 * retroactively updates existing invoice_details rows to match, since the
 * project is still in testing and historical accuracy of these
 * particular rows isn't a concern. MEM001/DON001 already have real
 * invoice_item_masters rows and reporting_group_items mappings, so no
 * further master-data changes are needed.
 *
 * invoice_item_masters.item_code and reporting_group_items.item_code were
 * previously widened from varchar(6) to varchar(30) to fit MEMFEE01/
 * INCOTH01 (8 chars). That's no longer required now that nothing uses an
 * 8-character code, but left as-is deliberately -- narrowing back down is
 * an unnecessary, mildly risky operation (would fail on any future
 * longer code) for zero real benefit.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('invoice_details')->where('item_code', 'MEMFEE01')->update(['item_code' => 'MEM001']);
        DB::table('invoice_details')->where('item_code', 'INCOTH01')->update(['item_code' => 'DON001']);
    }

    public function down(): void
    {
        // Not reversible -- MEM001/DON001 rows created after this
        // migration runs can't be distinguished from the ones renamed
        // here. Acceptable given historical accuracy for these rows is
        // explicitly not a concern on this project.
    }
};
