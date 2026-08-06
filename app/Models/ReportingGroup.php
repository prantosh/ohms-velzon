<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportingGroup extends Model
{
    protected $fillable = [

        'group_name',
        'remarks',
        'status',

        'created_by',
        'updated_by',
    ];

    public function items()
    {
        return $this->hasMany(ReportingGroupItem::class, 'reporting_group_id');
    }
}
