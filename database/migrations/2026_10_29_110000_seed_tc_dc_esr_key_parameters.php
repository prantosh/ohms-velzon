<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TC DC ESR (PAT274) is TC DC (PAT323) plus ESR: the same TOTAL COUNT and
 * DLC (DC) key parameters, with the same report template content, plus
 * Erythrocyte Sedimentation Rate (PAT157) under its own ESR section, unit
 * "mm for 1st hour".
 *
 * ESR's reference range comes from the item master (PAT157, 06-20), shared
 * with CBC / Hemogram and standalone ESR; it is left as is.
 */
return new class extends Migration
{
    private const GROUP_ITEM_CODE_SUB = 'PAT274';

    private const SOURCE_GROUP_ITEM_CODE_SUB = 'PAT323';

    private const ESR_ITEM_CODE_SUB = 'PAT157';

    public function up(): void
    {
        $group = DB::table('invoice_item_details')->where('item_code_sub', self::GROUP_ITEM_CODE_SUB)->first();
        $esr = DB::table('invoice_item_details')->where('item_code_sub', self::ESR_ITEM_CODE_SUB)->first();

        if (!$group || !$esr) {
            return;
        }

        $now = now();

        DB::transaction(function () use ($group, $esr, $now) {

            // TC DC's key parameters, re-keyed to this group
            $source = DB::table('key_parameters')
                ->where('group_item_code_sub', self::SOURCE_GROUP_ITEM_CODE_SUB)
                ->orderBy('sort_order')
                ->get();

            $order = 0;

            foreach ($source as $row) {

                DB::table('key_parameters')->updateOrInsert(
                    ['item_code_sub' => $row->item_code_sub, 'group_item_code_sub' => self::GROUP_ITEM_CODE_SUB],
                    [
                        'parameter_name' => $row->parameter_name,
                        'group_label' => $group->item_description_sub,
                        'section_label' => $row->section_label,
                        'uom' => $row->uom,
                        'default_value' => null,
                        'trigger_item_code_subs' => json_encode([self::GROUP_ITEM_CODE_SUB]),
                        'sort_order' => $order++,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            DB::table('key_parameters')->updateOrInsert(
                ['item_code_sub' => self::ESR_ITEM_CODE_SUB, 'group_item_code_sub' => self::GROUP_ITEM_CODE_SUB],
                [
                    'parameter_name' => 'ERYTHROCYTE SEDIMENTATION RATE (ESR)',
                    'group_label' => $group->item_description_sub,
                    'section_label' => 'ESR',
                    'uom' => 'mm for 1st hour',
                    'default_value' => null,
                    'trigger_item_code_subs' => json_encode([self::GROUP_ITEM_CODE_SUB]),
                    'sort_order' => $order,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            // report template: a copy of TC DC's (already the auto-table form)
            $alreadyHasTemplate = DB::table('pathology_report_template_items')
                ->where('item_code_sub', self::GROUP_ITEM_CODE_SUB)
                ->exists();

            $sourceTemplate = DB::table('pathology_report_templates as t')
                ->join('pathology_report_template_items as i', 'i.pathology_report_template_id', '=', 't.id')
                ->where('i.item_code_sub', self::SOURCE_GROUP_ITEM_CODE_SUB)
                ->select('t.*')
                ->first();

            if (!$alreadyHasTemplate && $sourceTemplate) {

                $templateId = DB::table('pathology_report_templates')->insertGetId([
                    'title' => $group->item_description_sub,
                    'test_group_code' => $sourceTemplate->test_group_code,
                    'content' => $sourceTemplate->content,
                    'status' => 'ACTIVE',
                    'created_by' => 1,
                    'updated_by' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('pathology_report_template_items')->insert([
                    'pathology_report_template_id' => $templateId,
                    'item_code_sub' => self::GROUP_ITEM_CODE_SUB,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('key_parameters')->where('group_item_code_sub', self::GROUP_ITEM_CODE_SUB)->delete();

        $templateIds = DB::table('pathology_report_template_items')
            ->where('item_code_sub', self::GROUP_ITEM_CODE_SUB)
            ->pluck('pathology_report_template_id');

        DB::table('pathology_report_template_items')->whereIn('pathology_report_template_id', $templateIds)->delete();
        DB::table('pathology_report_templates')->whereIn('id', $templateIds)->delete();
    }
};
