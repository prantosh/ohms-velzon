<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [

        'item_code',

        'item_name',

        'uom',

        'inventory_category_id',

        'opening_stock',

        'opening_value',

        'current_stock',

        'avg_rate',

        'status',

        'created_by',

        'updated_by',
    ];

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }
}
