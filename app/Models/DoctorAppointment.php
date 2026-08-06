<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorAppointment extends Model
{
    use HasFactory;

    protected $fillable = [

        'appointment_no',

        'doctor_id',

        'doctor_schedule_session_id',

        'patient_id',

        'patient_name',

        'patient_mobile_no',

        'patient_age',

        'patient_gender',

        'appointment_date',

        'appointment_time',

        'token_no',

        'consultation_fee',

        'appointment_status',

        'remarks',

        'created_by',

        'updated_by'
    ];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function scheduleSession()
    {
        return $this->belongsTo(DoctorScheduleSession::class, 'doctor_schedule_session_id');
    }

}
