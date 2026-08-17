< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    WhatsApp Message Detail
                </title>

@php
function whatsappSuccessRate2($sent, $failed)
{
    $attempted = $sent + $failed;
    if (!$attempted) return '0%';
    return round(($sent / $attempted) * 100) . '%';
}
@endphp

                <style>

                    @page {
                            margin-top: 1in;
                            margin-right: 40px;
                            margin-bottom: 40px;
                            margin-left: 40px;
                        }

                    @page :first {
                            margin-top: 0.4in;
                        }

                    body {
                        font-family: DejaVu Sans, sans-serif;
                        font-size: 8.5px;
                        color: #000;
                        margin: 0;
                        padding: 0;
                    }

                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 8px;
                    }

                        table th,
                        table td {
                            border: 1px solid #000;
                            padding: 2.5px 4px;
                            vertical-align: top;
                            word-break: break-word;
                        }

                    .summary-table th,
                    .summary-table td {
                        border: 1px solid #ccc;
                    }

                    .text-end {
                        text-align: right;
                    }

                    .text-center {
                        text-align: center;
                    }

                    .no-border,
                    .no-border td {
                        border: none;
                    }

                    .label-blue {
                        color: #000;
                        font-weight: bold;
                    }

                    table thead th {
                        color: #000;
                        font-weight: bold;
                        text-align: center;
                    }

                    .signature-section {
                        margin-top: 60px;
                    }
                </style>

        </head>

        <body>

@include('partials.pdf-header', ['reportTitle' => 'WHATSAPP MESSAGE DETAIL'])

<!-- =======================================================
     DATE / TOTALS
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">Date</td>
    <td>{{ $date->format('d-m-Y (l)') }}</td>

    <td width="25%" class="label-blue">Total Messages</td>
    <td>{{ $summary['total_count'] }}</td>
</tr>

<tr>
    <td class="label-blue">Sent</td>
    <td>{{ $summary['sent_count'] }}</td>

    <td class="label-blue">Failed</td>
    <td>{{ $summary['failed_count'] }}</td>
</tr>

<tr>
    <td class="label-blue">Skipped (Auto-Send Off)</td>
    <td>{{ $summary['skipped_count'] }}</td>

    <td class="label-blue">Success Rate</td>
    <td><b>{{ whatsappSuccessRate2($summary['sent_count'], $summary['failed_count']) }}</b></td>
</tr>

<tr>
    <td class="label-blue">Printed By</td>
    <td colspan="3">{{ $printedBy }}</td>
</tr>

</table>

<!-- =======================================================
     MESSAGE DETAIL
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="7" style="text-align:left; background-color:#e6e6e6;">
        Messages Sent On {{ $date->format('d-m-Y') }}
    </th>
</tr>
<tr>
    <th width="7%">Time</th>
    <th width="12%">Invoice / Appt No</th>
    <th width="14%">Patient / User</th>
    <th width="11%">Mobile No</th>
    <th width="11%">Type</th>
    <th width="7%">Status</th>
    <th>Message ID / Error</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row['time_fmt'] }}</td>
    <td>{{ $row['invoice_no'] }}</td>
    <td>{{ $row['patient_name'] ?? '-' }}</td>
    <td>{{ $row['mobile_no'] }}</td>
    <td>{{ $row['type_label'] }}</td>
    <td class="text-center">{{ $row['status'] }}</td>
    <td>{{ $row['status'] === 'SENT' ? ($row['message_id'] ?? '-') : ($row['response_preview'] ?? '-') }}</td>
</tr>
@empty
<tr>
    <td colspan="7" class="text-center">No WhatsApp messages found for this date.</td>
</tr>
@endforelse

</tbody>

</table>

<div class="signature-section">

<table border="0" style="border:none; margin:0;">

<tr>

<td class="no-border text-center">

_____________________

<br>

Prepared By ({{ $printedBy }})

</td>

</tr>

</table>

</div>

</body>

</html>
