<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsgReportTemplate extends Model
{
    protected $fillable = [

        'title',
        'item_code_sub',

        'clinical_history',
        'findings',
        'impression',

        'status',

        'created_by',
        'updated_by',
    ];
}
