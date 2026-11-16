<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Urine for RE/ME (PAT219) as tracked key parameters: 20 zero-rate report
 * items under PAT219, split into PHYSICAL / CHEMICAL / MICROSCOPIC via
 * key_parameters.section_label, and the PAT219 report template stripped
 * down to the auto-table placeholder in place of its hand-typed table.
 * URINE RE & CS (PAT386) is a package containing PAT219, so it picks these
 * up through its component line without a trigger of its own.
 *
 * Report items get fresh PAT codes (next free number under PAT001) rather
 * than reusing the stand-alone URINE SUGAR / URINE ACETONE / URINE BILE
 * SALT tests, which are billed and reported on their own.
 */
return new class extends Migration
{
    private const GROUP_ITEM_CODE_SUB = 'PAT219';
    private const ITEM_CODE = 'PAT001';

    private const OLD_TEMPLATE_CONTENT = '<p><strong> </strong></p><p><strong> </strong></p><p style="text-align:center;"><strong><u>URINE FOR ROUTINE EXAMINATION</u></strong></p><table><tbody><tr><td colspan="2"><strong><u>PHYSICAL</u></strong></td></tr><tr><td>Colour                              </td><td>Straw</td></tr><tr><td>Volume                            </td><td>50ml</td></tr><tr><td>Specific Gravity</td><td>1.015    </td></tr><tr><td>Sediment</td><td>Present</td></tr><tr><td>Appearance                      </td><td>Slightly Hazy</td></tr><tr><td colspan="2"><strong><u>CHEMICAL</u></strong>                     </td></tr><tr><td>Reaction                            </td><td>Acidic-6.5</td></tr><tr><td>Protein                              </td><td>Nil</td></tr><tr><td>Sugar                                </td><td>Nil</td></tr><tr><td>B/S</td><td>Negative</td></tr><tr><td>B/P</td><td>Negative</td></tr><tr><td>Blood                                </td><td>Nil</td></tr><tr><td>Acetone                            </td><td>Nil</td></tr><tr><td>Urobilinogen                   </td><td>Normal</td></tr><tr><td colspan="2"><strong><u>MICROSCOPIC</u></strong></td></tr><tr><td>RBC                                 </td><td>Nil                          </td></tr><tr><td>E. Cell</td><td>4-6/hpf</td></tr><tr><td>P. Cell </td><td>2-4/hpf</td></tr><tr><td>Crystal                  </td><td>Nil      </td></tr><tr><td>Cast                                  </td><td>Nil      </td></tr><tr><td>Others                              </td><td>Nil      </td></tr><tr><td>Micro Organism</td><td>Present +</td></tr></tbody></table><p><strong> </strong></p>';

    private const NEW_TEMPLATE_CONTENT = '<p style="text-align:center;"><strong><u>URINE FOR ROUTINE EXAMINATION</u></strong></p><div data-auto-key-param-table="1"></div>';

    /**
     * [name, patient-friendly description, section, default value]
     */
    private function parameters(): array
    {
        return [
            ['Colour', 'Urine Colour', 'PHYSICAL', 'Straw'],
            ['Volume', 'Urine Volume', 'PHYSICAL', null],
            ['Specific Gravity', 'Urine Specific Gravity', 'PHYSICAL', null],
            ['Sediment', 'Urine Sediment', 'PHYSICAL', 'Present'],
            ['Appearance', 'Urine Appearance', 'PHYSICAL', null],
            ['Reaction', 'Urine Reaction (pH)', 'CHEMICAL', null],
            ['Protein', 'Urine Protein', 'CHEMICAL', 'Nil'],
            ['Sugar', 'Urine Sugar', 'CHEMICAL', 'Present'],
            ['B/S', 'Urine Bile Salt', 'CHEMICAL', 'Negative'],
            ['B/P', 'Urine Bile Pigment', 'CHEMICAL', 'Negative'],
            ['Blood', 'Urine Blood', 'CHEMICAL', 'Nil'],
            ['Acetone', 'Urine Acetone', 'CHEMICAL', 'Nil'],
            ['Urobilinogen', 'Urine Urobilinogen', 'CHEMICAL', 'Normal'],
            ['RBC', 'Urine Red Blood Cells', 'MICROSCOPIC', 'Nil'],
            ['E. Cell', 'Urine Epithelial Cells', 'MICROSCOPIC', null],
            ['P. Cell', 'Urine Pus Cells', 'MICROSCOPIC', null],
            ['Crystal', 'Urine Crystals', 'MICROSCOPIC', 'Nil'],
            ['Cast', 'Urine Casts', 'MICROSCOPIC', 'Nil'],
            ['Others', 'Urine Others', 'MICROSCOPIC', 'Nil'],
            ['Micro Organism', 'Urine Micro Organism', 'MICROSCOPIC', null],
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

            // Only the template(s) linked to PAT219 that still have the old
            // hand-typed table -- ids can differ between databases, and an
            // already-converted or locally-edited template is left alone.
            DB::table('pathology_report_templates')
                ->whereIn('id', $this->urineTemplateIds())
                ->where('content', 'like', '%<u>PHYSICAL</u>%')
                ->where('content', 'not like', '%data-auto-key-param-table%')
                ->update([
                    'content' => self::NEW_TEMPLATE_CONTENT,
                    'updated_at' => $now,
                ]);
        });
    }

    private function urineTemplateIds(): array
    {
        return DB::table('pathology_report_template_items')
            ->where('item_code_sub', self::GROUP_ITEM_CODE_SUB)
            ->pluck('pathology_report_template_id')
            ->all();
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

        DB::table('pathology_report_templates')
            ->whereIn('id', $this->urineTemplateIds())
            ->where('content', self::NEW_TEMPLATE_CONTENT)
            ->update(['content' => self::OLD_TEMPLATE_CONTENT]);
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
