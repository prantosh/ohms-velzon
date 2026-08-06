<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenditureCategory extends Model
{
    protected $fillable = [

        'description',

        'created_by',

        'updated_by',
    ];
}
