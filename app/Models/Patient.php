<?php

namespace App\Models;

use App\Support\PatientAge;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $fillable = [

        'patient_id',

        'patient_name',

        'mobile_no',

        'age',

        'age_months',

        'age_recorded_on',

        'gender',

        'status'
    ];

    protected $casts = [
        'age_recorded_on' => 'date',
    ];

    protected static function booted(): void
    {
        // Stamp when the age was entered whenever it changes, so the
        // current age can be rolled forward later.
        static::saving(function (Patient $patient) {
            if (
                $patient->isDirty(['age', 'age_months'])
                && !$patient->isDirty('age_recorded_on')
            ) {
                $patient->age_recorded_on = $patient->age === null ? null : now()->toDateString();
            }
        });
    }

    /** Age rolled forward to today: ['years' => ?int, 'months' => ?int]. */
    public function currentAge(): array
    {
        return PatientAge::current($this->age, $this->age_months, $this->age_recorded_on);
    }

    /** Adds age_display / age_years_now / age_months_now to JSON for forms. */
    public function exposeAge(): static
    {
        $a = $this->currentAge();

        $this->setAttribute('age_display', $this->display_age);
        $this->setAttribute('age_years_now', $a['years']);
        $this->setAttribute('age_months_now', $a['months'] ?? 0);

        return $this;
    }

    /** "7 mo", "3 y 2 mo" or "34" -- what forms and PDFs show. */
    public function getDisplayAgeAttribute(): ?string
    {
        $a = $this->currentAge();

        return PatientAge::format($a['years'], $a['months']);
    }

    /**
     * The active patient already on this mobile number with this name (case
     * and spacing ignored; the oldest if several). A patient is identified
     * by mobile + name, so anywhere that would mint a new patient ID for a
     * mobile/name pair must reuse this one instead.
     */
    /**
     * Next free patient ID for a mobile number: highest existing "-NN"
     * suffix + 1. Counting rows instead collides as soon as a patient has
     * been moved off the mobile (correction/merge) or the counts drift, e.g.
     * 22 rows but P...-23 already taken.
     */
    public static function nextPatientId(string $mobileNo): string
    {
        $prefix = \App\Support\OfflineMode::prefix('P') . $mobileNo . '-';

        $max = static::where('patient_id', 'like', $prefix . '%')
            ->pluck('patient_id')
            ->map(fn ($id) => (int) substr($id, strlen($prefix)))
            ->max() ?? 0;

        return $prefix . str_pad($max + 1, 2, '0', STR_PAD_LEFT);
    }

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
