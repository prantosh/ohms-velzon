<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReportEntryReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $data)
    {
    }

    public function build()
    {
        $verb = $this->data['action'] === 'CONFIRM' ? 'Confirmed' : 'Saved';

        return $this->subject(
            "Report {$verb} - {$this->data['kind_label']} - {$this->data['invoice_no']}"
        )->view('emails.report-entry-receipt');
    }
}
