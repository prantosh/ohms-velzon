<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    protected $fillable = [

        'receipt_no',

        'receipt_date',

        'purchase_order_id',

        'ref_no',

        'vendor_name',

        'remarks',

        'created_by',

        'updated_by',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items()
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}
