<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportingGroupItem extends Model
{
    protected $fillable = [

        'reporting_group_id',
        'item_code',

        'created_by',
    ];

    public $timestamps = true;

    public function group()
    {
        return $this->belongsTo(ReportingGroup::class, 'reporting_group_id');
    }

    public function itemMaster()
    {
        return $this->belongsTo(InvoiceItemMaster::class, 'item_code', 'item_code');
    }
}
