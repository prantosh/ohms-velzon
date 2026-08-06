<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenditureTransaction extends Model
{
    protected $fillable = [

        'voucher_number',

        'expenditure_category_id',

        'expenditure_agency_id',

        'transaction_date',

        'amount',

        'payment_mode',

        'cheque_number',

        'bank_name',

        'bill_number',

        'cheque_date',

        'expenditure_by',

        'remarks',

        'created_by',

        'updated_by',
    ];

    public function category()
    {
        return $this->belongsTo(ExpenditureCategory::class, 'expenditure_category_id');
    }

    public function agency()
    {
        return $this->belongsTo(ExpenditureAgency::class, 'expenditure_agency_id');
    }

    public function expenditureByUser()
    {
        return $this->belongsTo(User::class, 'expenditure_by');
    }
}
