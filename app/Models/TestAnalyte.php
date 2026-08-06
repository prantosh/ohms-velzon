<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestAnalyte extends Model
{
    protected $table = 'test_analytes';

    protected $fillable = [

        'invoice_item_detail_id',

        'analyte_name',

        'group_name',

        'uom',

        'range_male',

        'range_female',

        'range_common',

        'method',

        'sort_order',

        'status',

        'created_by',

        'updated_by',
    ];

    public function invoiceItemDetail()
    {
        return $this->belongsTo(InvoiceItemDetail::class, 'invoice_item_detail_id');
    }
}
