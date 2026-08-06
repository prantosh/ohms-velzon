<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImpressionMaster extends Model
{
    protected $table = 'impression_masters';

    protected $fillable = [

        'name',

        'status',

        'created_by',

        'updated_by',
    ];
}
