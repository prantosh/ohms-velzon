<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'user_image',
        'authorised',
        'mobile_no',
        'role',
        'status',
        'date_of_birth',
        'date_of_joining',
        'gender',
        'aadhar_no',
        'pan_no',
        'qualification',
        'blood_group',
        'father_name',
        'guardian_name',
        'present_address',
        'permanent_address',
        'emergency_mobile_no',
        'bank_ac_no',
        'bank_name',
        'bank_branch',
        'family_member_1',
        'family_member_2',
        'family_member_3',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function roleAccess()
    {
        return $this->belongsTo(RolePageAccess::class, 'role', 'role');
    }

    /**
     * Prevents the 'datetime' cast on email_verified_at from serializing to
     * a UTC-shifted ISO timestamp instead of local time -- same defect
     * fixed on MembershipFeeRate/DoctorTestPaymentMaster/AuditLog/
     * TestReportConfirmation.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
