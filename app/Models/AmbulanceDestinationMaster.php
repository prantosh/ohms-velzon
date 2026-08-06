<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmbulanceDestinationMaster extends Model
{
    protected $table = 'ambulance_destination_masters';

    protected $fillable = [

        'destination_code',

        'destination_name',

        'fare_ac',

        'fare_nonac',

        'remarks',

        'status',

        'created_by',

        'updated_by',

    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
