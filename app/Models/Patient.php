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
}
