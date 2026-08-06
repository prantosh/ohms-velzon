<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    protected $fillable = [
        'doctor_code',
        'doctor_name',
        'gender',
        'date_of_birth',

        'mobile_no',
        'alternate_mobile_no',

        'email',

        'specialisation',
        'specialisation_code',

        'qualification',
        'experience_years',

        'registration_no',

        'joining_date',

        'consultation_fee_total',
        'consultation_fee_doctor',
        'consultation_fee_doctor_discounted_patient',

        'discount',

        'status',

        'remarks',

        'doctor_image',

        'created_by',
        'updated_by',
    ];

    public $timestamps = true;

    public function specialisation()
    {
        return $this->belongsTo(
            Specialisation::class,
            'specialisation_code',
            'id'
        );
    }

    /**
     * All specialisations assigned to this doctor (specialisation_code
     * above stays the PRIMARY one and continues to drive the existing
     * single-value screens -- this is the full set, for the doctor form
     * and anywhere that should show/edit all of them).
     */
    public function specialisations()
    {
        return $this->belongsToMany(
            Specialisation::class,
            'doctor_specialisations',
            'doctor_id',
            'specialisation_id'
        );
    }

    public function appointments()
    {
        return $this->hasMany(DoctorAppointment::class, 'doctor_id');
    }
}
