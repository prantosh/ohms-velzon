<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NonPathologyReportFinding extends Model
{
    protected $fillable = [

        'invoice_detail_id',

        'invoice_no',
        'item_code',
        'item_code_sub',
        'item_description',

        'clinical_history',
        'findings',
        'impression',

        'confirmed_by',
        'confirmed_at',

        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];
}
