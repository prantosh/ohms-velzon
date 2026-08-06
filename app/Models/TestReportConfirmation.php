<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestReportConfirmation extends Model
{
    protected $table = 'test_report_confirmations';

    protected $fillable = [

        'invoice_no',

        'confirmed_by',

        'confirmed_at',

    ];

    protected $casts = [

        'confirmed_at' => 'datetime',

    ];

    /**
     * Without this, Eloquent's default JSON/array serialization converts
     * the 'datetime' cast on confirmed_at to a full UTC ISO-8601 timestamp
     * (shifted by the app's +05:30 offset) instead of local time -- the
     * same defect fixed on MembershipFeeRate/DoctorTestPaymentMaster/
     * AuditLog. This one actually corrupted a real confirmed_at value via
     * toArray() during testing before being caught.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
