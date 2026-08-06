<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [

        'po_no',

        'po_date',

        'requisition_ref',

        'requisition_date',

        'vendor_name',

        'payment_term',

        'total_value',

        'note',

        'status',

        'created_by',

        'updated_by',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
