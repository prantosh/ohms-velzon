<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorScheduleSession extends Model
{
    protected $fillable = [

        'doctor_schedule_id',
        'day_of_week',
        'session_label',

        'start_time',
        'end_time',
        'is_by_appointment',

        'slot_duration_minutes',
        'max_patient',

        'status',

        'created_by',
        'updated_by',
    ];

    protected $casts = [

        'status' => 'integer',
        'is_by_appointment' => 'boolean',
        'slot_duration_minutes' => 'integer',
        'max_patient' => 'integer',
    ];

    public function schedule()
    {
        return $this->belongsTo(DoctorSchedule::class, 'doctor_schedule_id');
    }
}
