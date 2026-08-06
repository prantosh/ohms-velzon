<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockIssue extends Model
{
    protected $fillable = [

        'issue_no',

        'issue_date',

        'issued_to',

        'issued_to_name',

        'remarks',

        'created_by',

        'updated_by',
    ];

    public function items()
    {
        return $this->hasMany(StockIssueItem::class);
    }
}
