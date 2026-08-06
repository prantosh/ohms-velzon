<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $fillable = [

        'patient_id',

        'patient_name',

        'mobile_no',

        'age',

        'gender',

        'status'
    ];
}
