<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoteMaster extends Model
{
    protected $table = 'note_masters';

    protected $fillable = [

        'name',

        'status',

        'created_by',

        'updated_by',
    ];
}
