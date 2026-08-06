<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceTestDetail extends Model
{
    protected $table = 'invoice_test_details';

    protected $fillable = [

        'invoice_id',
        'item_code',
        'item_code_sub',
        'test_name',
        'rate',
        'discount',
        'amount',
        'report_days',
        'doctor_id',
        'payment_value',
        'remarks'
    ];
}
