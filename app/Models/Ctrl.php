<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ctrl extends Model
{
    protected $table = 'ctrl';

    public $timestamps = false;

    protected $fillable = [

        'oxygen_min_advance',

        'oxygen_rate_per_day',

        'oxygen_rate_per_unit',

        'concentrator_min_advance',

        'concentrator_rate_per_day',

        'fin_yr'
    ];

    public static function current(): self
    {
        return static::query()->firstOrFail();
    }
}
