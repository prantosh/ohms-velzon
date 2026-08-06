<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItemDetail extends Model
{
    protected $table = 'invoice_item_details';

    public $timestamps = false;

    protected $fillable = [

        'item_code',
        'item_code_sub',
        'item_description_sub',
        'is_package',
        'rate',

        'discount_percent',
        'member_discount',
        'discount_other_lab',
        'discount_member_other_lab',

        'test_group_code',
        'sample_master_id',
        'uom',

        'range_male',
        'range_female',
        'range_common',

        'method',

        'report_days',

        'status',

        'created_by',
        'created_dt',
        'update_by',
        'update_dt'
    ];

    protected $casts = [

        'rate' => 'decimal:2',

        'discount_percent' => 'decimal:2',

        'member_discount' => 'decimal:2',

        'discount_other_lab' => 'decimal:2',

        'discount_member_other_lab' => 'decimal:2',

        'report_days' => 'integer'

    ];


    public function master()
    {
        return $this->belongsTo(

            InvoiceItemMaster::class,

            'item_code',

            'item_code'

        );
    }

    public function testGroup()
    {
        return $this->belongsTo(TestGroupMaster::class, 'test_group_code');
    }

    public function sample()
    {
        return $this->belongsTo(SampleMaster::class, 'sample_master_id');
    }

    public function analytes()
    {
        return $this->hasMany(TestAnalyte::class, 'invoice_item_detail_id')
            ->where('status', 'ACTIVE')
            ->orderBy('sort_order');
    }

    public function subGroups()
    {
        return $this->hasMany(TestSubGroup::class, 'invoice_item_detail_id')
            ->orderBy('sort_order')
            ->with('members');
    }

    public function packageComponents()
    {
        return $this->hasMany(TestPackageComponent::class, 'package_invoice_item_detail_id')
            ->where('status', 'ACTIVE')
            ->orderBy('sort_order')
            ->with('component');
    }
}
