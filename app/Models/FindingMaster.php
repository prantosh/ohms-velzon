<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FindingMaster extends Model
{
    protected $table = 'finding_masters';

    protected $fillable = [

        'name',

        'status',

        'created_by',

        'updated_by',
    ];
}
