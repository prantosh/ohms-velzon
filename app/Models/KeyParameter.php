<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeyParameter extends Model
{
    protected $table = 'key_parameters';

    protected $fillable = [
        'item_code_sub',
        'parameter_name',
        'group_label',
        'group_item_code_sub',
        'uom',
        'trigger_item_code_subs',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'trigger_item_code_subs' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];
}
