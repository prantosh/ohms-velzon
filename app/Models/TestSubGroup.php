<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestSubGroup extends Model
{
    protected $table = 'test_sub_groups';

    protected $fillable = [

        'invoice_item_detail_id',

        'name',

        'sort_order',

        'created_by',

        'updated_by',
    ];

    public function members()
    {
        return $this->hasMany(TestSubGroupMember::class, 'sub_group_id')
            ->orderBy('group_name');
    }

    public function invoiceItemDetail()
    {
        return $this->belongsTo(InvoiceItemDetail::class, 'invoice_item_detail_id');
    }
}
