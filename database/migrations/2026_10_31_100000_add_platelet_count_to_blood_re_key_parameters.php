<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds PLATELET COUNT (PAT184) to Blood RE (PAT144) as the last row, outside
 * the TOTAL COUNT / DLC (DC) sections (like HB). PAT184 is already tracked
 * under CBC (PAT150), which allow_key_parameter_in_multiple_groups permits,
 * so a patient's platelet history is shared between CBC and Blood RE.
 */
return new class extends Migration
{
    private const GROUP_ITEM_CODE_SUB = 'PAT144';
    private const ITEM_CODE_SUB = 'PAT184';

    public function up(): void
    {
        $group = DB::table('invoice_item_details')->where('item_code_sub', self::GROUP_ITEM_CODE_SUB)->first();
        $item = DB::table('invoice_item_details')->where('item_code_sub', self::ITEM_CODE_SUB)->first();

        if (!$group || !$item) {
            return;
        }

        $now = now();

        $sortOrder = (int) DB::table('key_parameters')
            ->where('group_item_code_sub', self::GROUP_ITEM_CODE_SUB)
            ->max('sort_order') + 1;

        DB::table('key_parameters')->updateOrInsert(
            ['item_code_sub' => self::ITEM_CODE_SUB, 'group_item_code_sub' => self::GROUP_ITEM_CODE_SUB],
            [
                'parameter_name' => $item->item_description_sub,
                'group_label' => $group->item_description_sub,
                'section_label' => null,
                'uom' => $item->uom ?: null,
                'default_value' => null,
                'trigger_item_code_subs' => json_encode([self::GROUP_ITEM_CODE_SUB]),
                'sort_order' => $sortOrder,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('key_parameters')
            ->where('group_item_code_sub', self::GROUP_ITEM_CODE_SUB)
            ->where('item_code_sub', self::ITEM_CODE_SUB)
            ->delete();
    }
};
