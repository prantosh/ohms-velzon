<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $fillable = [

        'patient_id',

        'patient_name',

        'mobile_no',

        'age',

        'gender',

        'status'
    ];

    /**
     * The active patient already on this mobile number with this name (case
     * and spacing ignored; the oldest if several). A patient is identified
     * by mobile + name, so anywhere that would mint a new patient ID for a
     * mobile/name pair must reuse this one instead.
     */
    public static function findActiveByMobileAndName(?string $mobileNo, ?string $name): ?self
    {
        $normalise = fn (?string $s) => strtoupper(preg_replace('/\s+/', ' ', trim((string) $s)));

        $wanted = $normalise($name);

        if ($mobileNo === null || $mobileNo === '' || $wanted === '') {
            return null;
        }

        return static::where('mobile_no', $mobileNo)
            ->where('status', 'A')
            ->orderBy('id')
            ->get()
            ->first(fn ($p) => $normalise($p->patient_name) === $wanted);
    }
}
