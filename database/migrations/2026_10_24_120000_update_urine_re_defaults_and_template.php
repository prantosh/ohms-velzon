<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Follow-up to seed_urine_re_key_parameters:
 *
 * - Revised default values for the Urine RE/ME report items (lab's own list).
 * - Converts the PAT219 template to the auto-table placeholder whatever its
 *   current content is. The seed migration only replaced content that still
 *   matched the original hand-typed table, which a database whose copy had
 *   been edited never did -- reports were then created without the
 *   placeholder, and the entry screen fell back to its own 4-column Key
 *   Parameters table. The replaced content is written to laravel.log.
 */
return new class extends Migration
{
    private const GROUP_ITEM_CODE_SUB = 'PAT219';

    private const NEW_TEMPLATE_CONTENT = '<p style="text-align:center;"><strong><u>URINE FOR ROUTINE EXAMINATION</u></strong></p><div data-auto-key-param-table="1"></div>';

    /**
     * patient-friendly description (set by the seed migration) => default
     */
    private const DEFAULTS = [
        'Urine Colour' => 'Straw',
        'Urine Volume' => null,
        'Urine Specific Gravity' => null,
        'Urine Sediment' => 'Present',
        'Urine Appearance' => null,
        'Urine Reaction (pH)' => null,
        'Urine Protein' => 'Nil',
        'Urine Sugar' => 'Present',
        'Urine Bile Salt' => 'Negative',
        'Urine Bile Pigment' => 'Negative',
        'Urine Blood' => 'Nil',
        'Urine Acetone' => 'Nil',
        'Urine Urobilinogen' => 'Normal',
        'Urine Red Blood Cells' => 'Nil',
        'Urine Epithelial Cells' => null,
        'Urine Pus Cells' => null,
        'Urine Crystals' => 'Nil',
        'Urine Casts' => 'Nil',
        'Urine Others' => 'Nil',
        'Urine Micro Organism' => null,
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::DEFAULTS as $friendly => $default) {

            $itemCodeSub = DB::table('invoice_item_details')
                ->where('patient_friendly_description', $friendly)
                ->where('rate', 0)
                ->value('item_code_sub');

            if (!$itemCodeSub) {
                continue;
            }

            DB::table('key_parameters')
                ->where('item_code_sub', $itemCodeSub)
                ->where('group_item_code_sub', self::GROUP_ITEM_CODE_SUB)
                ->update(['default_value' => $default, 'updated_at' => $now]);
        }

        $templates = DB::table('pathology_report_templates')
            ->whereIn('id', DB::table('pathology_report_template_items')
                ->where('item_code_sub', self::GROUP_ITEM_CODE_SUB)
                ->pluck('pathology_report_template_id'))
            ->where('content', 'not like', '%data-auto-key-param-table%')
            ->get(['id', 'title', 'content']);

        foreach ($templates as $template) {

            Log::info('Urine RE template converted to auto key-parameter table; previous content follows', [
                'template_id' => $template->id,
                'title' => $template->title,
                'content' => $template->content,
            ]);

            DB::table('pathology_report_templates')
                ->where('id', $template->id)
                ->update(['content' => self::NEW_TEMPLATE_CONTENT, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        // Previous defaults/template content are not restored -- the old
        // template content is in laravel.log if it is ever needed.
    }
};
