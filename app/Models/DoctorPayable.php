<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DoctorPayable extends Model
{
    use HasFactory;

    protected $table = 'doctor_payables';

    protected $fillable = [

        // Payable
        'payable_no',

        // Invoice
        'invoice_id',
        'invoice_no',
        'invoice_type',
        'transaction_type',

        // Doctor
        'doctor_id',
        'doctor_code',
        'doctor_name',

        // Patient
        'patient_id',
        'patient_name',

        // Service
        'item_code',
        'item_code_sub',
        'item_description',

        // Financial
        'gross_amount',
        'payment_rule',
        'payment_rate',
        'payable_amount',

        // Settlement
        'payment_status',
        'payment_batch_no',
        'paid_amount',
        'paid_date',

        // Remarks
        'remarks',

        // Audit
        'created_by',
        'updated_by',
    ];

    protected $casts = [

        'gross_amount' => 'decimal:2',
        'payment_rate' => 'decimal:2',
        'payable_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',

        'paid_date' => 'date',

        'created_at' => 'datetime',
        'updated_at' => 'datetime',

    ];

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_PAID = 'PAID';

    public const STATUS_CANCELLED = 'CANCELLED';

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function doctor()
    {
        return $this->belongsTo(
            Doctor::class,
            'doctor_id'
        );
    }

    public function invoice()
    {
        return $this->belongsTo(
            Invoice::class,
            'invoice_id'
        );
    }

    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updater()
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePending($query)
    {
        return $query->where(
            'payment_status',
            self::STATUS_PENDING
        );
    }

    public function scopeApproved($query)
    {
        return $query->where(
            'payment_status',
            self::STATUS_APPROVED
        );
    }

    public function scopePaid($query)
    {
        return $query->where(
            'payment_status',
            self::STATUS_PAID
        );
    }

    public function scopeDoctor($query, $doctorId)
    {
        return $query->where(
            'doctor_id',
            $doctorId
        );
    }

    public function scopeInvoiceType($query, $type)
    {
        return $query->where(
            'invoice_type',
            $type
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public function markApproved()
    {
        $this->update([

            'payment_status' => self::STATUS_APPROVED,

            'updated_by' => auth()->id(),

        ]);
    }

    public function markPaid(
        string $batchNo,
        float $amount
    ) {

        $this->update([

            'payment_status' => self::STATUS_PAID,

            'payment_batch_no' => $batchNo,

            'paid_amount' => $amount,

            'paid_date' => now(),

            'updated_by' => auth()->id(),

        ]);

    }

    public function cancel()
    {
        $this->update([

            'payment_status' => self::STATUS_CANCELLED,

            'updated_by' => auth()->id(),

        ]);
    }

    /**
     * Prevents the 'date'/'datetime' casts (paid_date, created_at,
     * updated_at) from serializing to UTC-shifted ISO timestamps instead
     * of local time -- same defect fixed on MembershipFeeRate/
     * DoctorTestPaymentMaster/AuditLog/TestReportConfirmation.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
