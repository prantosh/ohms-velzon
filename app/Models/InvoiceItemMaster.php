<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItemMaster extends Model
{
    protected $table = 'invoice_item_masters';

    public $timestamps = true;

    protected $fillable = [

        'item_code',

        'item_name',

        'item_type',

        'description',

        'status',

        'test_parameter_required',

        'created_by',

        'updated_by'

    ];

    protected $casts = [

        'status' => 'boolean',

        'created_at' => 'datetime',

        'updated_at' => 'datetime'

    ];

    public function details()
    {
        return $this->hasMany(

            InvoiceItemDetail::class,

            'item_code',

            'item_code'

        );
    }

    /**
     * Prevents the 'datetime' casts on created_at/updated_at from
     * serializing to UTC-shifted ISO timestamps instead of local time --
     * same defect fixed on MembershipFeeRate/DoctorTestPaymentMaster/
     * AuditLog/TestReportConfirmation.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
