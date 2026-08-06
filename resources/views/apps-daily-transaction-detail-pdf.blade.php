< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Daily Transaction Detail
                </title>

@php

function fmtMoney8($v)
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

@include('partials.pdf-header', ['reportTitle' => 'DAILY TRANSACTION DETAIL'])

<!-- =======================================================
     DATE / TOTALS
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">Date</td>
    <td>{{ $date->format('d-m-Y (l)') }}</td>

    <td width="25%" class="label-blue">Records</td>
    <td>{{ $summary['total_records'] }}</td>
</tr>

<tr>
    <td class="label-blue">Payment Mode Filter</td>
    <td colspan="3">{{ $paymentModeFilter === 'ALL' ? 'All (Cash + Non-Cash)' : ucwords(strtolower(str_replace('-', ' ', $paymentModeFilter))) }}</td>
</tr>

<tr>
    <td class="label-blue">Total Collection</td>
    <td>Rs. {{ fmtMoney8($summary['total_collection']) }}</td>

    <td class="label-blue">Total Refund</td>
    <td>Rs. {{ fmtMoney8($summary['total_refund']) }}</td>
</tr>

<tr>
    <td class="label-blue">Total Payment</td>
    <td>Rs. {{ fmtMoney8($summary['total_payment']) }}</td>

    <td class="label-blue">Printed By</td>
    <td>{{ $printedBy }}</td>
</tr>

<tr>
    <td class="label-blue">Net</td>
    <td colspan="3"><b>Rs. {{ fmtMoney8($summary['net_total']) }}</b></td>
</tr>

</table>

<!-- =======================================================
     DETAIL
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="14" style="text-align:left; background-color:#e6e6e6;">
        Transaction Detail for {{ $date->format('d-m-Y (l)') }}
    </th>
</tr>
<tr>
    <th width="5%">Time</th>
    <th width="8%">Transaction No</th>
    <th width="6%">Type</th>
    <th>Patient Name</th>
    <th>Doctor Name</th>
    <th width="8%">Invoice Ref</th>
    <th width="6%">Rate</th>
    <th width="6%">Discount</th>
    <th width="7%">Billed Amt</th>
    <th width="7%">Received Amt</th>
    <th width="6%">Status</th>
    <th width="5%">Mode</th>
    <th>Operator</th>
    <th>Remarks</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row['time_fmt'] }}</td>
    <td>{{ $row['transaction_no'] }}</td>
    <td class="text-center">{{ $row['type_label'] }}</td>
    <td>{{ $row['patient_name'] ?: '-' }}</td>
    <td>{{ $row['doctor_name'] ?: '-' }}</td>
    <td>{{ $row['invoice_reference'] ?: '-' }}</td>
    <td class="text-end">{{ $row['rate'] !== null ? fmtMoney8($row['rate']) : '-' }}</td>
    <td class="text-end">{{ $row['discount'] !== null ? fmtMoney8($row['discount']) : '-' }}</td>
    <td class="text-end">{{ $row['billed_amount'] !== null ? fmtMoney8($row['billed_amount']) : '-' }}</td>
    <td class="text-end">{{ fmtMoney8($row['received_total']) }}</td>
    <td class="text-center">{{ $row['payment_status'] }}</td>
    <td class="text-center">{{ $row['payment_mode'] }}</td>
    <td>{{ $row['operator_name'] ?: '-' }}</td>
    <td>{{ $row['remarks'] ?: '-' }}</td>
</tr>
@empty
<tr>
    <td colspan="14" class="text-center">No transactions found for this date.</td>
</tr>
@endforelse

<tr style="background-color:#f0f0f0; font-weight:bold;">
    <td colspan="9" class="text-end">Net Total</td>
    <td class="text-end">{{ fmtMoney8($summary['net_total']) }}</td>
    <td colspan="4"></td>
</tr>

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
