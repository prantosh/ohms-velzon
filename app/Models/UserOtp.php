<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserOtp extends Model
{
    protected $fillable = [
        'mobile_no',
        'otp_code_hash',
        'purpose',
        'verification_token',
        'expires_at',
        'verified_at',
        'consumed_at',
        'attempts',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'consumed_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function scopeActive(Builder $query, string $mobileNo, string $purpose = 'password_reset'): Builder
    {
        return $query->where('mobile_no', $mobileNo)
            ->where('purpose', $purpose)
            ->where('expires_at', '>', now())
            ->whereNull('consumed_at');
    }
}
