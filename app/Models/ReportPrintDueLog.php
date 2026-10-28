<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportPrintDueLog extends Model
{
    protected $table = 'report_print_due_logs';

    protected $fillable = [
        'invoice_id',
        'invoice_no',
        'patient_name',
        'patient_mobile_no',
        'total_amount',
        'paid_amount',
        'due_amount',
        'test_items',
        'print_type',
        'printed_by',
        'printed_by_name',
        'ip_address',
        'printed_at',
    ];

    protected $casts = [
        'printed_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
    ];

    /**
     * Local time instead of a UTC-shifted ISO string -- same fix as
     * TestReportDelivery and the other logged models.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
