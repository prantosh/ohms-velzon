<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceCancellationPermission extends Model
{
    protected $fillable = [

        'invoice_id',
        'invoice_no',

        'granted_by',
        'remarks',

        'created_by',
    ];

    public function grantedByUser()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
