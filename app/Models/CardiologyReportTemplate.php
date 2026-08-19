<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardiologyReportTemplate extends Model
{
    protected $fillable = [

        'title',
        'item_code_sub',

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

        'status',

        'created_by',
        'updated_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
