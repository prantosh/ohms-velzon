<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockIssueItem extends Model
{
    protected $fillable = [

        'stock_issue_id',

        'inventory_item_id',

        'uom',

        'issue_qty',

        'unit_rate',

        'amount',
    ];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
