<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MicroscopyMaster extends Model
{
    protected $table = 'microscopy_masters';

    protected $fillable = [

        'name',

        'status',

        'created_by',

        'updated_by',
    ];
}
