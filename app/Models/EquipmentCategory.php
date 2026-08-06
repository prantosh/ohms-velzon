<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentCategory extends Model
{
    protected $table = 'equipment_categories';

    protected $fillable = [

        'category_code',

        'category_name',

        'total_quantity',

        'available_quantity',

        'remarks',

        'status',

        'created_by',

        'updated_by'

    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
