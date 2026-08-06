<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientCard extends Model
{
    protected $fillable = [

        'card_no',

        'mobile_no',

        'patient_name1',

        'patient_name2',

        'patient_name3',

        'patient_name4',

        'patient_name5',

        'active',

        'remarks',

        'created_by',

        'updated_by',
    ];
}
