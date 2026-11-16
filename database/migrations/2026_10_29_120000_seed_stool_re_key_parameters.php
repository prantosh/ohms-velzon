<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Stool RE / ME (PAT205) as tracked key parameters, same report-creation
 * flow as Urine RE/ME: 18 zero-rate report items under PAT205, split into
 * PHYSICAL EXAMINATION / CHEMICAL EXAMINATION / MICROSCOPIC EXAMINATION via
 * key_parameters.section_label (no unit or range on any row, so the report
 * prints as a two-column table), plus a PAT205 report template that holds
 * just a heading and the auto-table placeholder. PAT205 had no template.
 *
 * Report items get fresh PAT codes (next free number under PAT001) rather
 * than reusing STOOL OBT (PAT203), which is billed and reported on its own.
 */
return new class extends Migration
{
    private const GROUP_ITEM_CODE_SUB = 'PAT205';
    private const ITEM_CODE = 'PAT001';
    private const TEMPLATE_TITLE = 'STOOL RE / ME';

    private const TEMPLATE_CONTENT = '<p style="text-align:center;"><strong><u>STOOL FOR ROUTINE EXAMINATION</u></strong></p><div data-auto-key-param-table="1"></div>';

    /**
     * [name, patient-friendly description, section, default value]
     */
    private function parameters(): array
    {
        return [
            ['Color', 'Stool Color', 'PHYSICAL EXAMINATION', null],
            ['Consistency', 'Stool Consistency', 'PHYSICAL EXAMINATION', null],
            ['Mucus', 'Stool Mucus', 'PHYSICAL EXAMINATION', null],
            ['Blood', 'Stool Blood', 'PHYSICAL EXAMINATION', null],
            ['Reaction', 'Stool Reaction', 'CHEMICAL EXAMINATION', null],
            ['Occult Blood Test', 'Stool Occult Blood Test', 'CHEMICAL EXAMINATION', null],
            ['Vegetable Cells', 'Stool Vegetable Cells', 'MICROSCOPIC EXAMINATION', null],
            ['Starch', 'Stool Starch', 'MICROSCOPIC EXAMINATION', null],
            ['Pus Cells', 'Stool Pus Cells', 'MICROSCOPIC EXAMINATION', null],
            ['RBC', 'Stool Red Blood Cells', 'MICROSCOPIC EXAMINATION', null],
            ['Ova', 'Stool Ova', 'MICROSCOPIC EXAMINATION', null],
            ['Cyst', 'Stool Cyst', 'MICROSCOPIC EXAMINATION', null],
            ['Trophozoites', 'Stool Trophozoites', 'MICROSCOPIC EXAMINATION', null],
            ['Larva', 'Stool Larva', 'MICROSCOPIC EXAMINATION', null],
            ['Fat Droplets', 'Stool Fat Droplets', 'MICROSCOPIC EXAMINATION', null],
            ['Macrophage Cells', 'Stool Macrophage Cells', 'MICROSCOPIC EXAMINATION', null],
            ['Crystals', 'Stool Crystals', 'MICROSCOPIC EXAMINATION', null],
            ['Others', 'Stool Others', 'MICROSCOPIC EXAMINATION', null],
        ];
    }

    public function up(): void
    {
        $group = DB::table('invoice_item_details')
            ->where('item_code_sub', self::GROUP_ITEM_CODE_SUB)
            ->first();

        if (!$group) {
            return;
        }

        $now = now();

        DB::transaction(function () use ($group, $now) {

            foreach ($this->parameters() as $order => [$name, $friendly, $section, $default]) {

                // Re-running finds the item this migration already created
                // (by its unique patient-friendly description) instead of
                // creating a duplicate.
                $itemCodeSub = DB::table('invoice_item_details')
                    ->where('item_code', self::ITEM_CODE)
                    ->where('patient_friendly_description', $friendly)
                    ->where('rate', 0)
                    ->value('item_code_sub');

                if (!$itemCodeSub) {
                    $itemCodeSub = $this->nextItemCodeSub();

                    DB::table('invoice_item_details')->insert([
                        'item_code_sub' => $itemCodeSub,
                        'item_code' => self::ITEM_CODE,
                        'item_description_sub' => $name,
                        'is_package' => 0,
                        'is_outsourced' => 0,
                        'is_report_not_required' => 0,
                        'rate' => 0,
                        'discount_percent' => 0,
                        'member_discount' => 0,
                        'discount_other_lab' => 0,
                        'discount_member_other_lab' => 0,
                        'discount_senior_citizen' => 0,
                        'test_group_code' => $group->test_group_code,
                        'sample_master_id' => $group->sample_master_id,
                        'patient_friendly_description' => $friendly,
                        'report_days' => 0,
                        'status' => 'Y',
                        'created_by' => 1,
                        'created_dt' => $now,
                    ]);
                }

                DB::table('key_parameters')->updateOrInsert(
                    ['item_code_sub' => $itemCodeSub],
                    [
                        'parameter_name' => $name,
                        'group_label' => $group->item_description_sub,
                        'group_item_code_sub' => self::GROUP_ITEM_CODE_SUB,
                        'section_label' => $section,
                        'uom' => null,
                        'default_value' => $default,
                        'trigger_item_code_subs' => json_encode([self::GROUP_ITEM_CODE_SUB]),
                        'sort_order' => $order,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            // A template already linked to PAT205 (made by hand since this
            // was written) is left exactly as it is.
            $linked = DB::table('pathology_report_template_items')
                ->where('item_code_sub', self::GROUP_ITEM_CODE_SUB)
                ->exists();

            if (!$linked) {

                $templateId = DB::table('pathology_report_templates')->insertGetId([
                    'title' => self::TEMPLATE_TITLE,
                    'test_group_code' => $group->test_group_code,
                    'content' => self::TEMPLATE_CONTENT,
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
        $friendlyNames = array_column($this->parameters(), 1);

        $itemCodeSubs = DB::table('invoice_item_details')
            ->where('item_code', self::ITEM_CODE)
            ->where('rate', 0)
            ->whereIn('patient_friendly_description', $friendlyNames)
            ->pluck('item_code_sub');

        DB::table('key_parameters')->whereIn('item_code_sub', $itemCodeSubs)->delete();

        // The items themselves stay if any invoice line references them.
        $usedCodes = DB::table('invoice_details')->whereIn('item_code_sub', $itemCodeSubs)->distinct()->pluck('item_code_sub');
        DB::table('invoice_item_details')
            ->whereIn('item_code_sub', $itemCodeSubs->diff($usedCodes))
            ->delete();

        // Only the template this migration created, and only if untouched.
        $templateIds = DB::table('pathology_report_templates')
            ->where('title', self::TEMPLATE_TITLE)
            ->where('content', self::TEMPLATE_CONTENT)
            ->pluck('id');

        DB::table('pathology_report_template_items')->whereIn('pathology_report_template_id', $templateIds)->delete();
        DB::table('pathology_report_templates')->whereIn('id', $templateIds)->delete();
    }

    // Same PAT### numbering as InvoiceItemDetailController::
    // generateSubItemCode(), but by numeric value -- not a string sort.
    private function nextItemCodeSub(): string
    {
        $max = DB::table('invoice_item_details')
            ->where('item_code_sub', 'like', 'PAT%')
            ->selectRaw('MAX(CAST(SUBSTRING(item_code_sub, 4) AS UNSIGNED)) as max_number')
            ->value('max_number');

        return 'PAT' . str_pad((int) $max + 1, 3, '0', STR_PAD_LEFT);
    }
};
