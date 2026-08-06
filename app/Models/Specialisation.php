<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Specialisation extends Model
{
    protected $table = 'specialisations';

    protected $fillable = [
        'category',
        'created_dt',
        'created_by',
        'update_dt',
        'update_by',
    ];

    public $timestamps = false;

    public function doctors()
    {
        return $this->hasMany(
            Doctor::class,
            'specialisation_code',
            'id'
        );
    }
   
}
