<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportCorrectionNotice extends Model
{
    protected $fillable = [
        'invoice_detail_id',
        'previous_sent_at',
    ];

    protected $casts = [
        'previous_sent_at' => 'datetime',
    ];
}
