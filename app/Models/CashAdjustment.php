<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashAdjustment extends Model
{
    protected $table = 'cash_adjustments';

    protected $fillable = [
        'adjustment_no',
        'user_id',
        'adjustment_date',
        'amount',
        'source',
        'reference_date',
        'reference_note',
        'remarks',
        'status',
        'created_by',
        'created_by_name',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = [
        'adjustment_date' => 'date:Y-m-d',
        'reference_date' => 'date:Y-m-d',
        'amount' => 'float',
    ];
}
