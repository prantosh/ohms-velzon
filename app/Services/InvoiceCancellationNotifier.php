<?php

namespace App\Services;

use App\Mail\InvoiceCancelledMail;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails every Admin/Supervisor ("authorised persons") whenever any invoice
 * is cancelled, from any of the cancellation entry points in the app (see
 * InvoiceCancellationApproval, which every one of those now shares). Mirrors
 * DiscountApprovedMail's existing pattern: sent synchronously, one email per
 * recipient so each gets addressed by name, and failure is logged rather
 * than allowed to fail the cancellation itself.
 */
class InvoiceCancellationNotifier
{
    private const RECIPIENT_ROLES = ['Admin', 'Supervisor'];

    private const INVOICE_TYPE_LABELS = [
        'DOCTOR_VISIT' => 'Doctor Visit',
        'DIAGNOSTIC' => 'Diagnostic',
        'OXYGEN_RENT' => 'Oxygen Concentrator/Cylinder Rental',
        'CONCENTRATOR_RENT' => 'Concentrator Rental',
        'AMBULANCE_RENT' => 'Ambulance Rental',
        'MEMBERSHIP_FEE' => 'Membership Fee',
        'OTHER_INCOME' => 'Income from Other Source',
    ];

    public function notify(
        Invoice $invoice,
        float $refundAmount,
        ?string $reason,
        int $cancelledByUserId,
        ?int $approvedByUserId
    ): void {

        try {

            $recipients = DB::table('users')
                ->whereIn('role', self::RECIPIENT_ROLES)
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->get(['name', 'email']);

            if ($recipients->isEmpty()) {
                return;
            }

            $cancelledByName = DB::table('users')->where('id', $cancelledByUserId)->value('name') ?? 'Unknown';

            $approvedByName = $approvedByUserId
                ? DB::table('users')->where('id', $approvedByUserId)->value('name')
                : null;

            $invoiceTypeLabel = self::INVOICE_TYPE_LABELS[$invoice->invoice_type] ?? $invoice->invoice_type;

            $invoiceDateFmt = $invoice->invoice_date
                ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y')
                : null;

            foreach ($recipients as $recipient) {

                Mail::to($recipient->email)->send(new InvoiceCancelledMail(
                    $recipient->name,
                    $invoice->invoice_no,
                    $invoiceTypeLabel,
                    $invoiceDateFmt,
                    $invoice->patient_name,
                    $refundAmount,
                    $cancelledByName,
                    $reason,
                    $approvedByName
                ));
            }

        } catch (\Throwable $e) {

            Log::error('Failed to send invoice cancellation email: ' . $e->getMessage());
        }
    }
}
