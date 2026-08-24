<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestReportDelivery extends Model
{
    protected $table = 'test_report_deliveries';

    protected $fillable = [
        'invoice_id',
        'invoice_no',
        'patient_name',
        'patient_mobile_no',
        'total_amount',
        'paid_amount',
        'due_amount',
        'delivered_by',
        'delivered_at',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function deliveredByUser()
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    /**
     * Prevents the 'datetime' cast from serializing to UTC-shifted ISO
     * timestamps instead of local time -- same defect fixed on
     * MembershipFeeRate/DoctorTestPaymentMaster/AuditLog/DoctorSettlement.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
