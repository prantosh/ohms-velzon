<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Doctor;
class DoctorTestPaymentMaster extends Model
{
    use HasFactory;

    protected $table =
        'doctor_test_payment_masters';

    protected $fillable = [

        'doctor_id',

        'item_code',

        'item_code_sub',

        'item_description_sub',

        'payment_type',

        'payment_value',

        'effective_from',

        'effective_to',

        'status',

        'created_by',

        'updated_by'
    ];

    protected $casts = [

        'payment_value' => 'decimal:2',

        'effective_from' => 'date',

        'effective_to' => 'date'
    ];

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where(
            'status',
            'A'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    public function getStatusTextAttribute()
    {
        return
            $this->status == 'A'
            ? 'Active'
            : 'Inactive';
    }

    public function getPaymentTypeTextAttribute()
    {
        return
            $this->payment_type == 'FIXED'
            ? 'Fixed'
            : 'Percentage';
    }

    /*
    |--------------------------------------------------------------------------
    | DOCTOR NAME HELPER
    |--------------------------------------------------------------------------
    */

    public function getDoctorDisplayAttribute()
    {
        return optional(
            $this->doctor
        )->doctor_name;
    }

    /*
    |--------------------------------------------------------------------------
    | TEST DESCRIPTION HELPER
    |--------------------------------------------------------------------------
    */

    public function getTestDisplayAttribute()
    {
        return
            $this->item_code_sub .
            ' - ' .
            $this->item_description_sub;
    }

    public function doctor()
    {
        return $this->belongsTo(
            Doctor::class,
            'doctor_id'
        );
    }

    /**
     * Without this, Eloquent's default JSON serialization converts the
     * 'date' casts on effective_from/effective_to to full UTC ISO-8601
     * timestamps (e.g. "2022-03-31T18:30:00.000000Z" for a stored value of
     * 2022-04-01, since the app runs +05:30) instead of a plain date --
     * same defect as MembershipFeeRate. This controller currently works
     * around it with a raw DATE_FORMAT() SQL column, but any future screen
     * that serializes these attributes directly would hit the same bug.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }
}
