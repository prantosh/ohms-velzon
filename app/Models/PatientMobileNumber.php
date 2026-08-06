<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientMobileNumber extends Model
{
    use HasFactory;

    protected $table = 'patient_mobile_numbers';

    protected $fillable = [
        'patient_id',
        'mobile_no',
        'is_primary',
        'remarks'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function patient()
    {
        return $this->belongsTo(
            Patient::class,
            'patient_id',
            'patient_id'
        );
    }

    /**
     * Prevents the 'datetime' casts on created_at/updated_at from
     * serializing to UTC-shifted ISO timestamps instead of local time --
     * same defect fixed on MembershipFeeRate/DoctorTestPaymentMaster/
     * AuditLog/TestReportConfirmation.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
