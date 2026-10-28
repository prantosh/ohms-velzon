<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\ReportPrintDueLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Records every report print done while the invoice still has a payment
 * due (the dashboard warns first, but staff may choose to print anyway).
 * Viewed by Admin only -- see ReportPrintDueLogController. A logging
 * failure must never stop the print, so it only writes to the app log.
 */
class ReportPrintDueLogger
{
    /**
     * @param iterable<string> $itemDescriptions billed test names printed
     */
    public static function record(Invoice $invoice, iterable $itemDescriptions, string $printType): void
    {
        if ((float) $invoice->due_amount <= 0) {
            return;
        }

        try {

            $user = Auth::user();

            ReportPrintDueLog::create([
                'invoice_id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'patient_name' => $invoice->patient_name,
                'patient_mobile_no' => $invoice->patient_mobile_no,
                'total_amount' => $invoice->total_amount,
                'paid_amount' => $invoice->paid_amount,
                'due_amount' => $invoice->due_amount,
                'test_items' => collect($itemDescriptions)->filter()->implode(', '),
                'print_type' => $printType,
                'printed_by' => $user?->id,
                'printed_by_name' => $user?->name,
                'ip_address' => request()->ip(),
                'printed_at' => now(),
            ]);

        } catch (\Throwable $e) {

            Log::error('Report print-with-due log failed for ' . $invoice->invoice_no . ': ' . $e->getMessage());
        }
    }
}
