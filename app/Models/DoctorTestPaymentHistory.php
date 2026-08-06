<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorTestPaymentHistory extends Model
{
    protected $table =
        'doctor_test_payment_histories';

    protected $fillable = [

        'doctor_test_payment_master_id',

        'doctor_id',

        'item_code',

        'item_code_sub',

        'item_description_sub',

        'payment_type',

        'old_value',

        'new_value',

        'changed_on',

        'changed_by',
    ];

    public function doctor()
    {
        return $this->belongsTo(
            Doctor::class,
            'doctor_id'
        );
    }
}
