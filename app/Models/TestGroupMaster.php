<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestGroupMaster extends Model
{
    protected $table = 'test_group_masters';

    protected $fillable = [

        'test_group_name',

        'status',

        'additional_content',

        'created_by',

        'updated_by'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
