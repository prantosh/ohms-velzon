<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestResultEntry extends Model
{
    protected $table = 'test_result_entries';

    protected $fillable = [

        'invoice_no',

        'invoice_detail_id',

        'analyte_id',

        'item_code',

        'item_code_sub',

        'item_description',

        'uom',

        'range_male',

        'range_female',

        'range_common',

        'method',

        'result_value',

        'remarks',

        'created_by',

        'updated_by',

    ];

    public function analyte()
    {
        return $this->belongsTo(TestAnalyte::class, 'analyte_id');
    }
}
