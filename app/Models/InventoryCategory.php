<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCategory extends Model
{
    protected $fillable = [

        'category_name',

        'status',

        'created_by',

        'updated_by',
    ];
}
