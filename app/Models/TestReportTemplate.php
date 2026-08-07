<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestReportTemplate extends Model
{
    protected $fillable = [

        'title',

        'item_code_sub',

        'remarks',

        'status',

        'created_by',

        'updated_by',
    ];

    public function values()
    {
        return $this->hasMany(TestReportTemplateValue::class);
    }
}
