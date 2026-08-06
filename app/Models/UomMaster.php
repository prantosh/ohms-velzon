<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UomMaster extends Model
{
    protected $table = 'uom_masters';

    protected $fillable = [

        'name',

        'status',

        'created_by',

        'updated_by',
    ];
}
