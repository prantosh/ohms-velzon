<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardiologyReportFinding extends Model
{
    protected $fillable = [

        'invoice_detail_id',

        'invoice_no',
        'item_code_sub',
        'item_description',

        'heading',
        'm_mode_data',
        'doppler_data',
        'left_ventricle',
        'left_atrium',
        'right_ventricle_atrium',
        'mitral_valve',
        'aortic_valve',
        'tricuspid_valve',
        'pulmonary_valve',
        'inter_ventricular_septum',
        'inter_atrial_septum',
        'pericardium',
        'others',
        'conclusion',
        'remarks',

        'confirmed_by',
        'confirmed_at',

        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];
}
