<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsgReportTemplate extends Model
{
    protected $fillable = [

        'title',
        'item_code_sub',

        'clinical_history',
        'findings',
        'impression',

        'status',

        'created_by',
        'updated_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
