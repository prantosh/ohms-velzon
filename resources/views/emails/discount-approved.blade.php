<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
</head>

<body style="font-family: Arial, sans-serif; font-size: 14px; color: #212529;">

    <p>Dear {{ $approverName }},</p>

    <p>
        This is to confirm that a discount has been recorded under your authorisation on the
        invoice below.
    </p>

    <table style="border-collapse: collapse; margin: 15px 0;">

        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold;">Invoice No</td>
            <td style="padding: 4px 0;">{{ $invoiceNo }}</td>
        </tr>

        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold;">Patient Name</td>
            <td style="padding: 4px 0;">{{ $patientName }}</td>
        </tr>

        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold;">Discount Amount</td>
            <td style="padding: 4px 0;">Rs. {{ number_format($discountAmount, 2) }}</td>
        </tr>

        @if($givenByName)
        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold;">Discount Given By</td>
            <td style="padding: 4px 0;">{{ $givenByName }}</td>
        </tr>
        @endif

        @if($remarks)
        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold; vertical-align: top;">Remarks</td>
            <td style="padding: 4px 0;">{{ $remarks }}</td>
        </tr>
        @endif

    </table>

    <p>
        If you did not authorise this discount, please contact the administration immediately.
    </p>

    <p>Regards,<br>ABSSRK IT Team</p>

</body>

</html>
