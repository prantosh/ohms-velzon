<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['item_code_sub' => 'PAT331', 'parameter_name' => 'A:G Ratio', 'uom' => null, 'sort_order' => 46],
            ['item_code_sub' => 'PAT243', 'parameter_name' => 'Globulin', 'uom' => 'mg/dl', 'sort_order' => 47],
            ['item_code_sub' => 'PAT387', 'parameter_name' => 'Direct Bilirubin', 'uom' => 'mg/dl', 'sort_order' => 48],
            ['item_code_sub' => 'PAT388', 'parameter_name' => 'Indirect Bilirubin', 'uom' => 'mg/dl', 'sort_order' => 49],
        ];

        $now = now();

        foreach ($rows as $row) {
            $existing = DB::table('key_parameters')
                ->where('item_code_sub', $row['item_code_sub'])
                ->first();

            $triggers = json_decode($existing->trigger_item_code_subs ?? '[]', true) ?: [];
            $triggers = array_values(array_unique(array_merge(
                $triggers,
                [$row['item_code_sub'], 'PAT176']
            )));

            DB::table('key_parameters')->updateOrInsert(
                ['item_code_sub' => $row['item_code_sub']],
                [
                    'parameter_name' => $row['parameter_name'],
                    'group_label' => 'Liver Function Test (LFT)',
                    'uom' => $row['uom'],
                    'trigger_item_code_subs' => json_encode($triggers),
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $existing->created_at ?? $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('key_parameters')->whereIn('item_code_sub', [
            'PAT331',
            'PAT243',
            'PAT387',
            'PAT388',
        ])->delete();
    }
};
