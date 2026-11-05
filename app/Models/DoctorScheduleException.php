<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorScheduleException extends Model
{
    protected $fillable = [
        'doctor_id',
        'exception_date',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'exception_date' => 'date:Y-m-d',
    ];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    /** The staff user who marked this date unavailable. */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
