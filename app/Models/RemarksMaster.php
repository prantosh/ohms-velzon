<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemarksMaster extends Model
{
    protected $table = 'remarks_masters';

    protected $fillable = [

        'name',

        'status',

        'created_by',

        'updated_by',
    ];
}
