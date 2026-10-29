<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TC DC (PAT323) as tracked key parameters, same flow as Blood RE: TOTAL
 * LEUCOCYTE COUNT under the TOTAL COUNT section and Neutrophil, Eosinophil,
 * Basophil, Lymphocyte, Monocyte under DLC (DC), via
 * key_parameters.section_label. The items already exist in the item master
 * with their unit and reference range (they are also tracked under CBC and
 * Blood RE, which allow_key_parameter_in_multiple_groups permits).
 *
 * The TC DC template is stripped down to its heading, the auto-table
 * placeholder and the instrument notes; its previous content is written to
 * laravel.log.
 */
return new class extends Migration
{
    private const GROUP_ITEM_CODE_SUB = 'PAT323';

    private const NEW_TEMPLATE_CONTENT = '<p style="text-align:center;"><span style="color:#1F4E79;"><strong>REPORT OF HEMATOLOGY</strong></span></p>'
        . '<div data-auto-key-param-table="1"></div>'
        . '<p style="margin-left:0in;"><span class="text-small" style="color:#1F4E78;"><strong>INSTRUMENT USED:</strong></span></p>'
        . '<p style="margin-left:5.75pt;"><span class="text-small" style="color:#5B6570;">• CBC–360 neo Hematology Analyzer.</span></p>'
        . '<p style="margin-left:5.75pt;"><span class="text-small" style="color:#5B6570;">• ACCUREX</span></p>';

    /**
     * [item_code_sub, section]
     */
    private const PARAMETERS = [
        ['PAT394', 'TOTAL COUNT'],   // TOTAL LEUCOCYTE COUNT
        ['PAT395', 'DLC (DC)'],      // NEUTROPHIL
        ['PAT396', 'DLC (DC)'],      // EOSINOPHIL
        ['PAT397', 'DLC (DC)'],      // BASOPHIL
        ['PAT398', 'DLC (DC)'],      // LYMPHOCYTE
        ['PAT399', 'DLC (DC)'],      // MONOCYTE
    ];

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

            foreach (self::PARAMETERS as $order => [$itemCodeSub, $section]) {

                $item = DB::table('invoice_item_details')->where('item_code_sub', $itemCodeSub)->first();

                if (!$item) {
                    continue;
                }

                DB::table('key_parameters')->updateOrInsert(
                    ['item_code_sub' => $itemCodeSub, 'group_item_code_sub' => self::GROUP_ITEM_CODE_SUB],
                    [
                        'parameter_name' => $item->item_description_sub,
                        'group_label' => $group->item_description_sub,
                        'section_label' => $section,
                        'uom' => $item->uom ?: null,
                        'default_value' => null,
                        'trigger_item_code_subs' => json_encode([self::GROUP_ITEM_CODE_SUB]),
                        'sort_order' => $order,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            $templates = DB::table('pathology_report_templates')
                ->whereIn('id', DB::table('pathology_report_template_items')
                    ->where('item_code_sub', self::GROUP_ITEM_CODE_SUB)
                    ->pluck('pathology_report_template_id'))
                ->where('content', 'not like', '%data-auto-key-param-table%')
                ->get(['id', 'title', 'content']);

            foreach ($templates as $template) {

                Log::info('TC DC template converted to auto key-parameter table; previous content follows', [
                    'template_id' => $template->id,
                    'title' => $template->title,
                    'content' => $template->content,
                ]);

                DB::table('pathology_report_templates')
                    ->where('id', $template->id)
                    ->update(['content' => self::NEW_TEMPLATE_CONTENT, 'updated_at' => $now]);
            }
        });
    }

    public function down(): void
    {
        DB::table('key_parameters')
            ->where('group_item_code_sub', self::GROUP_ITEM_CODE_SUB)
            ->whereIn('item_code_sub', array_column(self::PARAMETERS, 0))
            ->delete();

        // The template's previous content is in laravel.log if needed.
    }
};
