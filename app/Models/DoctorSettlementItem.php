<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorSettlementItem extends Model
{
    use HasFactory;

    protected $table = 'doctor_settlement_items';

    protected $fillable = [

        'settlement_id',

        'payable_id',

        'invoice_id',

        'invoice_no',

        'invoice_type',

        'transaction_type',

        'patient_id',

        'patient_name',

        'item_code',

        'item_description',

        'payable_amount',

        'previous_paid_amount',

        'balance_before_payment',

        'settlement_amount',

        'balance_after_payment',

        'remarks',

        'created_by',

        'updated_by'
    ];

    protected $casts = [

        'payable_amount' => 'decimal:2',

        'previous_paid_amount' => 'decimal:2',

        'balance_before_payment' => 'decimal:2',

        'settlement_amount' => 'decimal:2',

        'balance_after_payment' => 'decimal:2'

    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function settlement()
    {
        return $this->belongsTo(
            DoctorSettlement::class,
            'settlement_id'
        );
    }

    public function payable()
    {
        return $this->belongsTo(
            DoctorPayable::class,
            'payable_id'
        );
    }

    public function invoice()
    {
        return $this->belongsTo(
            Invoice::class,
            'invoice_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getFormattedSettlementAmountAttribute()
    {
        return number_format(
            $this->settlement_amount,
            2
        );
    }

    public function getFormattedBalanceAttribute()
    {
        return number_format(
            $this->balance_after_payment,
            2
        );
    }
}
