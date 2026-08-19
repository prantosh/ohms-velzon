<?php

namespace App\Support;

/**
 * Single source of truth for the structured 2D-Echo report layout --
 * referenced by both Cardiology controllers, both Cardiology views/JS, the
 * PDF template, and NonPathologyReportController (to exclude these sub-items
 * from the generic narrative path). Field order here is the field order
 * everywhere else (form layout, PDF section order).
 */
class CardiologyReportFields
{
    // The only Cardiology (CRD001) sub-items that are actual echocardiography
    // studies -- the rest (ECG, Holter, ABPM, Sleep Study) don't fit this
    // chamber/valve structure at all and stay on the generic Non-Pathology
    // narrative system.
    public const ITEM_CODE_SUBS = ['CRD009', 'CRD010', 'CRD012', 'CRD017', 'CRD018', 'CRD019'];

    public const FIELDS = [
        'heading' => 'Heading',
        'm_mode_data' => 'M-Mode Data',
        'doppler_data' => 'PW / CW / Color Doppler Data',
        'left_ventricle' => '1. Left Ventricle',
        'left_atrium' => '2. Left Atrium',
        'right_ventricle_atrium' => '3. Right Ventricle & Right Atrium',
        'mitral_valve' => '4. Mitral Valve',
        'aortic_valve' => '5. Aortic Valve',
        'tricuspid_valve' => '6. Tricuspid Valve',
        'pulmonary_valve' => '7. Pulmonary Valve',
        'inter_ventricular_septum' => '8. Inter Ventricular Septum',
        'inter_atrial_septum' => '9. Interatrial Septum',
        'pericardium' => '10. Pericardium',
        'others' => '11. Others',
        'conclusion' => 'Conclusion',
        'remarks' => 'Remarks',
    ];

    public static function validationRules(): array
    {
        // No length cap -- matches UsgReportTemplateController's own fields
        // (clinical_history/findings/impression, also 'nullable|string'). A
        // fixed char limit made sense for short freeform notes, but these
        // fields can now hold a CKEditor table (an M-Mode or Doppler grid
        // easily runs several thousand characters once bordered/styled), so
        // the DB column's own text/longtext ceiling is the real limit.
        $rules = [];
        foreach (self::FIELDS as $key => $label) {
            $rules[$key] = 'nullable|string';
        }
        return $rules;
    }
}
