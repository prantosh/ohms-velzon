<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'customers';

    protected $fillable = [
        'customer_name',
        'email',
        'image',
        'date',
        'phone',
        'status'
    ];

    public $timestamps = false;
}
