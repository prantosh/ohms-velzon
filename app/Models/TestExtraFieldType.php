<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestExtraFieldType extends Model
{
    protected $table = 'test_extra_field_types';

    protected $fillable = [

        'field_name',

        'input_type',

        'source_master',

        'sort_order',

        'status',

        'created_by',

        'updated_by',
    ];
}
