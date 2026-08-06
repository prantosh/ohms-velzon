<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipFeeRate extends Model
{
    protected $table = 'membership_fee_rates';

    protected $fillable = [

        'effective_from',

        'monthly_rate',

        'remarks',

        'status',

        'created_by',

        'updated_by',

    ];

    protected $casts = [

        'effective_from' => 'date',

        'monthly_rate' => 'decimal:2',

    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Without this, Eloquent's default JSON serialization converts the
     * 'date' cast on effective_from to a full UTC ISO-8601 timestamp (e.g.
     * "2022-03-31T18:30:00.000000Z" for a stored value of 2022-04-01, since
     * the app runs +05:30) instead of a plain date -- which is what broke
     * both the list table display and the edit-modal month field.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    public static function rateForMonth(string $yearMonth): float
    {
        $rate = self::where('effective_from', '<=', $yearMonth . '-01')
            ->orderByDesc('effective_from')
            ->first();

        return $rate ? (float) $rate->monthly_rate : 0;
    }
}
