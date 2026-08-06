< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    User Invoice Reconciliation Report
                </title>

@php

function fmtMoney3($v)
{
    return number_format((float) ($v ?? 0), 2);
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
                        font-size: 11px;
                        color: #000;
                        margin: 0;
                        padding: 0;
                    }

                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 10px;
                    }

                        table th,
                        table td {
                            border: 1px solid #000;
                            padding: 5px;
                            vertical-align: top;
                        }

                    .text-end {
                        text-align: right;
                    }

                    .text-center {
                        text-align: center;
                    }

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

                    .settled {
                        color: #000;
                        font-weight: bold;
                    }

                    .not-settled {
                        color: #000;
                        font-style: italic;
                    }

                    .na {
                        color: #666;
                    }

                    .signature-section {
                        margin-top: 60px;
                    }
                </style>

        </head>

        <body>

@include('partials.pdf-header', ['reportTitle' => 'USER INVOICE RECONCILIATION REPORT'])

<!-- =======================================================
     USER / DATE / SUMMARY
======================================================= -->

<table>

<tr>
    <td width="25%" class="label-blue">User</td>
    <td>{{ $user->name }} ({{ $user->role }})</td>

    <td width="25%" class="label-blue">Date</td>
    <td>{{ $date->format('d-m-Y (l)') }}</td>
</tr>

<tr>
    <td class="label-blue">Total Invoices</td>
    <td>{{ $summary['total_invoices'] }}</td>

    <td class="label-blue">Total Collection</td>
    <td>Rs. {{ fmtMoney3($summary['total_amount']) }}</td>
</tr>

<tr>
    <td class="label-blue">Doctor Visit Invoices</td>
    <td>{{ $summary['doc_invoice_count'] }}</td>

    <td class="label-blue">DOC Settled / Not Settled</td>
    <td>{{ $summary['doc_settled_count'] }} / {{ $summary['doc_not_settled_count'] }}</td>
</tr>

<tr>
    <td class="label-blue">DOC Total Payable</td>
    <td>Rs. {{ fmtMoney3($summary['doc_total_payable']) }}</td>

    <td class="label-blue">DOC Settled / Balance</td>
    <td>Rs. {{ fmtMoney3($summary['doc_total_settled']) }} / Rs. {{ fmtMoney3($summary['doc_total_balance']) }}</td>
</tr>

</table>

<!-- =======================================================
     PAYMENT MODE BREAKDOWN
======================================================= -->

<table>

<thead>
<tr>
    @foreach($summary['payment_mode_breakdown'] as $mode => $amount)
    <th>{{ $mode }}</th>
    @endforeach
</tr>
</thead>

<tbody>
<tr>
    @foreach($summary['payment_mode_breakdown'] as $mode => $amount)
    <td class="text-center">Rs. {{ fmtMoney3($amount) }}</td>
    @endforeach
</tr>
</tbody>

</table>

<!-- =======================================================
     INVOICE LIST
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="9" style="text-align:left; background-color:#e6e6e6;">
        {{ $user->name }} &mdash; User Invoice Reconciliation for {{ $date->format('d-m-Y (l)') }}
    </th>
</tr>
<tr>
    <th>Invoice No</th>
    <th>Type</th>
    <th>Patient / Party</th>
    <th width="8%">Time</th>
    <th width="9%">Amount</th>
    <th width="8%">Mode</th>
    <th width="9%">Payable</th>
    <th width="9%">Settled</th>
    <th width="12%">Settlement No / Status</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row['invoice_no'] }}</td>
    <td>{{ $row['invoice_type_label'] }}</td>
    <td>{{ $row['patient_name'] }}</td>
    <td>{{ $row['time'] }}</td>
    <td class="text-end">{{ fmtMoney3($row['total_amount']) }}</td>
    <td>{{ $row['payment_mode'] }}</td>
    @if(!$row['is_doc'])
        <td class="text-center na">N/A</td>
        <td class="text-center na">N/A</td>
        <td class="na">Not Applicable</td>
    @elseif($row['is_settled'])
        <td class="text-end">{{ fmtMoney3($row['payable_amount']) }}</td>
        <td class="text-end">{{ fmtMoney3($row['settled_amount']) }}</td>
        <td class="settled">{{ $row['settlement_numbers'] }}</td>
    @else
        <td class="text-end">-</td>
        <td class="text-end">-</td>
        <td class="not-settled">Not Settled</td>
    @endif
</tr>
@empty
<tr>
    <td colspan="9" class="text-center">No invoices found for this date.</td>
</tr>
@endforelse

</tbody>

</table>

<div class="signature-section">

<table border="0" style="border:none; margin:0;">

<tr>

<td class="no-border text-center" style="width:45%;">

_____________________

<br>

Prepared By

</td>

<td class="no-border" style="width:10%;"></td>

<td class="no-border text-center" style="width:45%;">

_____________________

<br>

Verified By (Supervisor/Admin)

</td>

</tr>

</table>

</div>

</body>

</html>
