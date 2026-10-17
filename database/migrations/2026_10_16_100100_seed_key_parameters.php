<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Initial curated list of "key" pathology parameters to track history for,
 * mapped against the real test-master codes (item_code_sub) as they exist
 * in invoice_item_details today. See the create_key_parameter_history_tables
 * migration's docblock for what trigger_item_code_subs means.
 *
 * LFT (PAT176) and CBC (PAT150) are each billed as ONE line with their
 * sub-results typed together in one narrative -- not as separate
 * package-component lines the way Lipid Profile is -- so their individual
 * parameters trigger on the bundle's own code (plus, for Hb/RBC/Platelet,
 * their own code too, since those three are ALSO separately orderable).
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['item_code_sub' => 'PAT160', 'parameter_name' => 'FBS', 'group_label' => 'Blood Sugar', 'uom' => 'mg/dl', 'triggers' => ['PAT160'], 'sort_order' => 10],
            ['item_code_sub' => 'PAT185', 'parameter_name' => 'PPBS', 'group_label' => 'Blood Sugar', 'uom' => 'mg/dl', 'triggers' => ['PAT185'], 'sort_order' => 20],

            ['item_code_sub' => 'PAT151', 'parameter_name' => 'Cholesterol (Total)', 'group_label' => 'Lipid Profile', 'uom' => 'mg/dl', 'triggers' => ['PAT151'], 'sort_order' => 30],
            ['item_code_sub' => 'PAT168', 'parameter_name' => 'HDL Cholesterol', 'group_label' => 'Lipid Profile', 'uom' => 'mg/dl', 'triggers' => ['PAT168'], 'sort_order' => 31],
            ['item_code_sub' => 'PAT174', 'parameter_name' => 'LDL Cholesterol', 'group_label' => 'Lipid Profile', 'uom' => 'mg/dl', 'triggers' => ['PAT174'], 'sort_order' => 32],
            ['item_code_sub' => 'PAT213', 'parameter_name' => 'Triglyceride', 'group_label' => 'Lipid Profile', 'uom' => 'mg/dl', 'triggers' => ['PAT213'], 'sort_order' => 33],
            ['item_code_sub' => 'PAT381', 'parameter_name' => 'VLDL Cholesterol', 'group_label' => 'Lipid Profile', 'uom' => null, 'triggers' => ['PAT381'], 'sort_order' => 34],

            ['item_code_sub' => 'PAT198', 'parameter_name' => 'SGOT (AST)', 'group_label' => 'Liver Function Test (LFT)', 'uom' => 'U/L', 'triggers' => ['PAT176'], 'sort_order' => 40],
            ['item_code_sub' => 'PAT197', 'parameter_name' => 'SGPT (ALT)', 'group_label' => 'Liver Function Test (LFT)', 'uom' => 'U/L', 'triggers' => ['PAT176'], 'sort_order' => 41],
            ['item_code_sub' => 'PAT142', 'parameter_name' => 'Bilirubin (Total)', 'group_label' => 'Liver Function Test (LFT)', 'uom' => 'mg/dl', 'triggers' => ['PAT176'], 'sort_order' => 42],
            ['item_code_sub' => 'PAT138', 'parameter_name' => 'Alkaline Phosphatase', 'group_label' => 'Liver Function Test (LFT)', 'uom' => 'IU/L', 'triggers' => ['PAT176'], 'sort_order' => 43],
            ['item_code_sub' => 'PAT136', 'parameter_name' => 'Albumin', 'group_label' => 'Liver Function Test (LFT)', 'uom' => 'mg/dl', 'triggers' => ['PAT176'], 'sort_order' => 44],
            ['item_code_sub' => 'PAT212', 'parameter_name' => 'Total Protein', 'group_label' => 'Liver Function Test (LFT)', 'uom' => null, 'triggers' => ['PAT176'], 'sort_order' => 45],

            ['item_code_sub' => 'PAT153', 'parameter_name' => 'Creatinine', 'group_label' => 'Kidney Function', 'uom' => 'mg/dl', 'triggers' => ['PAT153'], 'sort_order' => 50],
            ['item_code_sub' => 'PAT216', 'parameter_name' => 'Uric Acid', 'group_label' => 'Kidney Function', 'uom' => 'mg/dl', 'triggers' => ['PAT216'], 'sort_order' => 51],

            ['item_code_sub' => 'PAT181', 'parameter_name' => 'Sodium (Na+)', 'group_label' => 'Electrolytes', 'uom' => 'mmol/l', 'triggers' => ['PAT181'], 'sort_order' => 60],
            ['item_code_sub' => 'PAT172', 'parameter_name' => 'Potassium (K+)', 'group_label' => 'Electrolytes', 'uom' => 'mmol/l', 'triggers' => ['PAT172'], 'sort_order' => 61],
            ['item_code_sub' => 'PAT147', 'parameter_name' => 'Calcium (Ca++)', 'group_label' => 'Electrolytes', 'uom' => null, 'triggers' => ['PAT147'], 'sort_order' => 62],
            ['item_code_sub' => 'PAT091', 'parameter_name' => 'Magnesium', 'group_label' => 'Electrolytes', 'uom' => null, 'triggers' => ['PAT091'], 'sort_order' => 63],

            ['item_code_sub' => 'PAT162', 'parameter_name' => 'Haemoglobin (Hb)', 'group_label' => 'Hemogram', 'uom' => 'gm/dl', 'triggers' => ['PAT162', 'PAT150'], 'sort_order' => 70],
            ['item_code_sub' => 'PAT191', 'parameter_name' => 'RBC', 'group_label' => 'Hemogram', 'uom' => 'Million/cmm', 'triggers' => ['PAT191', 'PAT150'], 'sort_order' => 71],
            ['item_code_sub' => 'PAT184', 'parameter_name' => 'Platelet Count', 'group_label' => 'Hemogram', 'uom' => 'Lakh/cmm', 'triggers' => ['PAT184', 'PAT150'], 'sort_order' => 72],

            ['item_code_sub' => 'PAT230', 'parameter_name' => 'Vitamin D (25-OH)', 'group_label' => 'Vitamin', 'uom' => null, 'triggers' => ['PAT230'], 'sort_order' => 80],
        ];

        $now = now();

        foreach ($rows as $row) {

            DB::table('key_parameters')->updateOrInsert(
                ['item_code_sub' => $row['item_code_sub']],
                [
                    'parameter_name' => $row['parameter_name'],
                    'group_label' => $row['group_label'],
                    'uom' => $row['uom'],
                    'trigger_item_code_subs' => json_encode($row['triggers']),
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('key_parameters')->whereIn('item_code_sub', [
            'PAT160', 'PAT185', 'PAT151', 'PAT168', 'PAT174', 'PAT213', 'PAT381',
            'PAT198', 'PAT197', 'PAT142', 'PAT138', 'PAT136', 'PAT212',
            'PAT153', 'PAT216', 'PAT181', 'PAT172', 'PAT147', 'PAT091',
            'PAT162', 'PAT191', 'PAT184', 'PAT230',
        ])->delete();
    }
};
