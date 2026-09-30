<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
</head>

<body style="font-family: Arial, sans-serif; font-size: 14px; color: #212529;">

    <p>Dear {{ $recipientName }},</p>

    <p>
        This is to inform you that the following invoice has been cancelled.
    </p>

    <table style="border-collapse: collapse; margin: 15px 0;">

        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold;">Invoice No</td>
            <td style="padding: 4px 0;">{{ $invoiceNo }}</td>
        </tr>

        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold;">Invoice Type</td>
            <td style="padding: 4px 0;">{{ $invoiceTypeLabel }}</td>
        </tr>

        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold;">Invoice Date</td>
            <td style="padding: 4px 0;">{{ $invoiceDateFmt ?? '-' }}</td>
        </tr>

        @if($patientName)
        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold;">Patient / Party Name</td>
            <td style="padding: 4px 0;">{{ $patientName }}</td>
        </tr>
        @endif

        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold;">Refund Amount</td>
            <td style="padding: 4px 0;">Rs. {{ number_format($refundAmount, 2) }}</td>
        </tr>

        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold;">Cancelled By</td>
            <td style="padding: 4px 0;">{{ $cancelledByName }}</td>
        </tr>

        @if($approvedByName)
        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold;">Approved By</td>
            <td style="padding: 4px 0;">{{ $approvedByName }}</td>
        </tr>
        @endif

        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold; vertical-align: top;">Reason for Cancellation</td>
            <td style="padding: 4px 0;">{{ $reason ?: 'Not specified' }}</td>
        </tr>

    </table>

    <p>
        If you were not expecting this cancellation, please verify it in the system.
    </p>

    <p>Regards,<br>ABSSRK IT Team</p>

</body>

</html>
