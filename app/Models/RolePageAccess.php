<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePageAccess extends Model
{
    protected $table = 'role_page_access';

    protected $fillable = [

        'role',

        'page_access',

        'created_by',

        'updated_by'
    ];

    protected $casts = [
        'page_access' => 'array'
    ];
}
