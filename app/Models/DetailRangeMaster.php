<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailRangeMaster extends Model
{
    protected $table = 'detail_range_masters';

    protected $fillable = [

        'name',

        'status',

        'created_by',

        'updated_by',
    ];
}
