<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TOTAL BILIRUBIN (PACKAGE, PAT401) -- set up like LIPID PROFILE: a package
 * whose components (Direct PAT387, Indirect PAT388 and Total PAT142, the
 * latter being Direct + Indirect) are captured in the Key Parameters box and
 * printed through the auto key-parameter table.
 *
 * The package (PAT401), its components Direct/Indirect and its report
 * template (content = the auto-table placeholder) already exist; this adds
 * what was missing -- Total as a component and template item, plus the three
 * key_parameters rows grouped under PAT401.
 *
 * PAT142 is also the LFT Total Bilirubin row, which used to be triggered by
 * PAT142 being billed on its own. That trigger moves to the new row so that
 * a Total Bilirubin report never has two competing rows for PAT142; LFT
 * itself still triggers it through PAT176.
 */
return new class extends Migration
{
    private const PACKAGE = 'PAT401';

    public function up(): void
    {
        $now = now();

        $package = DB::table('invoice_item_details')->where('item_code_sub', self::PACKAGE)->first();
        $total = DB::table('invoice_item_details')->where('item_code_sub', 'PAT142')->first();

        if (!$package || !$total) {
            return;
        }

        // package component
        if (!DB::table('test_package_components')
            ->where('package_invoice_item_detail_id', $package->id)
            ->where('component_invoice_item_detail_id', $total->id)
            ->exists()) {

            DB::table('test_package_components')->insert([
                'package_invoice_item_detail_id' => $package->id,
                'component_invoice_item_detail_id' => $total->id,
                'sort_order' => 2,
                'status' => 'ACTIVE',
                'created_by' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // report template already covering the package's Direct/Indirect
        $templateId = DB::table('pathology_report_template_items as i')
            ->join('pathology_report_templates as t', 't.id', '=', 'i.pathology_report_template_id')
            ->where('i.item_code_sub', 'PAT387')
            ->where('t.title', 'like', 'TOTAL BILIRUBIN%')
            ->value('t.id');

        if ($templateId && !DB::table('pathology_report_template_items')
            ->where('pathology_report_template_id', $templateId)
            ->where('item_code_sub', 'PAT142')
            ->exists()) {

            DB::table('pathology_report_template_items')->insert([
                'pathology_report_template_id' => $templateId,
                'item_code_sub' => 'PAT142',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // PAT142's own trigger moves from the LFT row to the new Total row
        DB::table('key_parameters')
            ->where('item_code_sub', 'PAT142')
            ->where('group_item_code_sub', 'PAT176')
            ->update(['trigger_item_code_subs' => json_encode(['PAT176']), 'updated_at' => $now]);

        $rows = [
            ['PAT387', 'Direct Bilirubin', 0],
            ['PAT388', 'Indirect Bilirubin', 1],
            ['PAT142', 'Bilirubin (Total)', 2],
        ];

        foreach ($rows as [$code, $name, $order]) {

            DB::table('key_parameters')->updateOrInsert(
                ['item_code_sub' => $code, 'group_item_code_sub' => self::PACKAGE],
                [
                    'parameter_name' => $name,
                    'group_label' => 'Total Bilirubin',
                    'uom' => 'mg/dl',
                    'trigger_item_code_subs' => json_encode([$code]),
                    'sort_order' => $order,
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('key_parameters')->where('group_item_code_sub', self::PACKAGE)->delete();

        DB::table('key_parameters')
            ->where('item_code_sub', 'PAT142')
            ->where('group_item_code_sub', 'PAT176')
            ->update(['trigger_item_code_subs' => json_encode(['PAT142', 'PAT176'])]);

        $package = DB::table('invoice_item_details')->where('item_code_sub', self::PACKAGE)->first();
        $total = DB::table('invoice_item_details')->where('item_code_sub', 'PAT142')->first();

        if ($package && $total) {
            DB::table('test_package_components')
                ->where('package_invoice_item_detail_id', $package->id)
                ->where('component_invoice_item_detail_id', $total->id)
                ->delete();
        }
    }
};
