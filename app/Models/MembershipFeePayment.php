<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipFeePayment extends Model
{
    protected $table = 'membership_fee_payments';

    protected $fillable = [

        'user_id',

        'payment_month',

        'rate_applied',

        'amount',

        'payment_type',

        'invoice_no',

        'remarks',

        'created_by',

        'updated_by',

    ];

    protected $casts = [

        'payment_month' => 'date',

        'rate_applied' => 'decimal:2',

        'amount' => 'decimal:2',

    ];

    public function member()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Prevents the 'date' cast on payment_month from serializing to a
     * UTC-shifted ISO timestamp instead of a plain date -- same defect
     * fixed on MembershipFeeRate/DoctorTestPaymentMaster/AuditLog/
     * TestReportConfirmation.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }
}
