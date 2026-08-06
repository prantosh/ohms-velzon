<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorSettlement extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Status Constants
    |--------------------------------------------------------------------------
    */

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_CANCELLED = 'CANCELLED';

    /*
    |--------------------------------------------------------------------------
    | Payment Modes
    |--------------------------------------------------------------------------
    */

    public const PAYMENT_CASH = 'CASH';

    public const PAYMENT_BANK = 'BANK';

    public const PAYMENT_CHEQUE = 'CHEQUE';

    public const PAYMENT_UPI = 'UPI';
    use HasFactory;

    protected $table = 'doctor_settlements';

    protected $fillable = [

        'settlement_no',
        'voucher_no',
        'settlement_date',

        'doctor_id',
        'doctor_code',
        'doctor_name',

        'payment_mode',
        'bank_name',
        'cheque_no',
        'utr_no',

        'gross_amount',
        'deduction_amount',
        'net_amount',

        'remarks',
        'internal_notes',

        'status',

        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',

        'created_by',
        'updated_by'
    ];

    protected $casts = [

        'settlement_date' => 'date',

        'cancelled_at' => 'datetime',

        'gross_amount' => 'decimal:2',

        'deduction_amount' => 'decimal:2',

        'net_amount' => 'decimal:2'

    ];

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

    public function items()
    {
        return $this->hasMany(
            DoctorSettlementItem::class,
            'settlement_id'
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

    public function canceller()
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getFormattedGrossAmountAttribute()
    {
        return number_format(
            $this->gross_amount,
            2
        );
    }

    public function getFormattedNetAmountAttribute()
    {
        return number_format(
            $this->net_amount,
            2
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeCompleted($query)
    {
        return $query->where(
            'status',
            'COMPLETED'
        );
    }

    public function scopeCancelled($query)
    {
        return $query->where(
            'status',
            'CANCELLED'
        );
    }

    public function scopeDraft($query)
    {
        return $query->where(
            'status',
            'DRAFT'
        );
    }

    public static function paymentModes()
    {
        return [
            self::PAYMENT_CASH => 'Cash',
            self::PAYMENT_BANK => 'Bank Transfer',
            self::PAYMENT_CHEQUE => 'Cheque',
            self::PAYMENT_UPI => 'UPI',
        ];
    }

    /**
     * Prevents the 'date'/'datetime' casts (settlement_date, cancelled_at)
     * from serializing to UTC-shifted ISO timestamps instead of local time
     * -- same defect fixed on MembershipFeeRate/DoctorTestPaymentMaster/
     * AuditLog/TestReportConfirmation.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
