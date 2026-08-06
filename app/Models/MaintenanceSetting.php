<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MaintenanceSetting extends Model
{
    protected $fillable = [
        'is_enabled',
        'message',
        'enabled_by',
        'enabled_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'enabled_at' => 'datetime',
    ];

    public const CACHE_KEY = 'maintenance_setting';

    /**
     * This is a single-row settings table -- always read/write row #1,
     * creating it if a fresh migrate somehow skipped the seed insert.
     */
    public static function current(): self
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return self::firstOrCreate(['id' => 1], ['is_enabled' => false]);
        });
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Prevents the 'datetime' cast on enabled_at from serializing to a
     * UTC-shifted ISO timestamp instead of local time -- same defect fixed
     * on MembershipFeeRate/DoctorTestPaymentMaster/AuditLog/TestReportConfirmation.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
