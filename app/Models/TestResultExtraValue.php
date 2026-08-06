<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestResultExtraValue extends Model
{
    protected $table = 'test_result_extra_values';

    protected $fillable = [

        'invoice_detail_id',

        'test_extra_field_type_id',

        'value',

        'created_by',

        'updated_by',
    ];

    public function fieldType()
    {
        return $this->belongsTo(TestExtraFieldType::class, 'test_extra_field_type_id');
    }
}
