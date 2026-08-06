<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestPackageComponent extends Model
{
    protected $table = 'test_package_components';

    protected $fillable = [

        'package_invoice_item_detail_id',

        'component_invoice_item_detail_id',

        'sort_order',

        'status',

        'created_by',

        'updated_by',
    ];

    public function component()
    {
        return $this->belongsTo(InvoiceItemDetail::class, 'component_invoice_item_detail_id');
    }

    public function package()
    {
        return $this->belongsTo(InvoiceItemDetail::class, 'package_invoice_item_detail_id');
    }
}
