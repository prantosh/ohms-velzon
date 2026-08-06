<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [

        'invoice_no',
        'invoice_type',   // ADD THIS
        'invoice_category',
        'reference_no',
        'appointment_id',
        'appointment_no',
        'doctor_id',

        'patient_id',
        'patient_name',
        'patient_mobile_no',
        'patient_age',
        'patient_gender',

        'invoice_date',

        'consultation_fee',

        'total_amount',
        'discount',
        'paid_amount',
        'due_amount',

        'payment_mode',
        'received_by',

        'remarks',
        'status',

        'is_card_holder',
        'card_number',

        'item_code',
        'item_code_sub',

        'created_by',
        'updated_by',

        'cancelled',
        'cancelled_by',
        'cancelled_at',
        'cancellation_approved_by',
        'cancellation_remarks',

        'refund_amount',
        'refund_date',
        'paid_amount_1',
        'paid_date_1',

        'paid_amount_2',
        'paid_date_2',
        'doctor_payment_amount',
        'doctor_amount_collected_by',
        'test_date',
        'referred_doctor',
        'payment_edit_count',

        'whatsapp_sent_at',
        'whatsapp_status',
        'whatsapp_send_count',
        'whatsapp_message_id',
        'whatsapp_error',

        'report_delivered_at',
        'report_delivered_by',

        'low_advance_reason',
    ];
}
