<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestSubGroupMember extends Model
{
    protected $table = 'test_sub_group_members';

    protected $fillable = [

        'sub_group_id',

        'group_name',
    ];

    public function subGroup()
    {
        return $this->belongsTo(TestSubGroup::class, 'sub_group_id');
    }
}
