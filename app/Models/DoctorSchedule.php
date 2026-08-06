<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorSchedule extends Model
{
    use HasFactory;

    protected $fillable = [

        'doctor_id',

        'monday_start',
        'monday_end',

        'tuesday_start',
        'tuesday_end',

        'wednesday_start',
        'wednesday_end',

        'thursday_start',
        'thursday_end',

        'friday_start',
        'friday_end',

        'saturday_start',
        'saturday_end',

        'sunday_start',
        'sunday_end',

        'max_patient',

        'status',

        'created_by',
        'updated_by'
    ];
    protected $casts = [

        'status' => 'integer'
    ];
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function sessions()
    {
        return $this->hasMany(DoctorScheduleSession::class);
    }
}
