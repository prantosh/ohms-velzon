<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceCancellationPermission extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_GRANTED = 'GRANTED';

    protected $fillable = [

        'invoice_id',
        'invoice_no',

        'requested_by',
        'reason',

        'status',

        'granted_by',
        'granted_at',
        'remarks',

        'created_by',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
    ];

    public function grantedByUser()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function requestedByUser()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
