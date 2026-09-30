<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $invoiceNo,
        public string $invoiceTypeLabel,
        public ?string $invoiceDateFmt,
        public ?string $patientName,
        public float $refundAmount,
        public string $cancelledByName,
        public ?string $reason,
        public ?string $approvedByName
    ) {
    }

    public function build()
    {
        return $this->subject('Invoice Cancelled - ' . $this->invoiceNo)
            ->view('emails.invoice-cancelled');
    }
}
