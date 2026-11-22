<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DiscountApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Copied on every discount approval email. */
    public const COPY_TO = 'admin@abssrk.com';

    public function __construct(
        public string $approverName,
        public string $invoiceNo,
        public string $patientName,
        public float $discountAmount,
        public ?string $remarks,
        public ?string $givenByName = null
    ) {
    }

    public function build()
    {
        return $this->subject('Discount Approval Confirmation - ' . $this->invoiceNo)
            ->view('emails.discount-approved');
    }
}
