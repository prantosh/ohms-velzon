<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KitMaster extends Model
{
    protected $table = 'kit_masters';

    protected $fillable = [

        'name',

        'status',

        'created_by',

        'updated_by',
    ];
}
