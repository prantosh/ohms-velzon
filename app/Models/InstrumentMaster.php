<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstrumentMaster extends Model
{
    protected $table = 'instrument_masters';

    protected $fillable = [

        'name',

        'status',

        'created_by',

        'updated_by',
    ];
}
