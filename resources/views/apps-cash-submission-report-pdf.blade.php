< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Cash Submission Slip
                </title>

@php

function fmtMoney4($v)
{
    return number_format((float) ($v ?? 0), 2);
}

$netCashFormatter = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
$netCashInWords = ucwords($netCashFormatter->format((float) ($summary['net_cash_to_deposit'] ?? 0)));
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
                        margin-top: 6px;
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

                    .amt-positive {
                        color: #000;
                        font-weight: bold;
                    }

                    .amt-negative {
                        color: #000;
                        font-weight: bold;
                    }

                    .signature-section {
                        margin-top: 35px;
                    }
                </style>

        </head>

        <body>

@include('partials.pdf-header', ['reportTitle' => 'DAILY CASH SUBMISSION SLIP'])

<!-- =======================================================
     USER / DATE / SUMMARY
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">User</td>
    <td>{{ $user->name }} ({{ $user->role }})</td>

    <td width="25%" class="label-blue">Date</td>
    <td>{{ $date->format('d-m-Y (l)') }}</td>
</tr>

<tr>
    <td class="label-blue">Cash Collected</td>
    <td>Rs. {{ fmtMoney4($summary['cash_collected']) }}</td>

    <td class="label-blue">Non-Cash Collected</td>
    <td>Rs. {{ fmtMoney4($summary['non_cash_collected']) }}</td>
</tr>

<tr>
    <td class="label-blue">Cash Refunded</td>
    <td>Rs. {{ fmtMoney4($summary['cash_refunded']) }}</td>

    <td class="label-blue">Cash Paid To Doctors</td>
    <td>Rs. {{ fmtMoney4($summary['cash_paid_to_doctors']) }}</td>
</tr>

<tr>
    <td class="label-blue">Net Cash To Deposit</td>
    <td><b>Rs. {{ fmtMoney4($summary['net_cash_to_deposit']) }}</b> (Rupees {{ $netCashInWords }} Only)</td>

    <td class="label-blue">Print Date/Time</td>
    <td>{{ now()->format('d-m-Y h:i A') }}</td>
</tr>

</table>

<!-- =======================================================
     DOCTOR PAYMENTS (CASH) MADE BY THIS USER
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="5" style="text-align:left; background-color:#e6e6e6;">
        Doctor Payments Made (Cash) By {{ $user->name }} On {{ $date->format('d-m-Y') }}
    </th>
</tr>
<tr>
    <th>Invoice No</th>
    <th>Doctor</th>
    <th>Settlement No</th>
    <th width="12%">Time</th>
    <th width="15%">Amount</th>
</tr>
</thead>

<tbody>

@forelse($doctorPayments as $row)
<tr>
    <td>{{ $row['invoice_no'] }}</td>
    <td>{{ $row['doctor_name'] }}</td>
    <td>{{ $row['settlement_no'] }}</td>
    <td>{{ $row['settlement_time'] }}</td>
    <td class="text-end amt-negative">{{ fmtMoney4($row['amount']) }}</td>
</tr>
@empty
<tr>
    <td colspan="5" class="text-center">No cash payments made to doctors by this user on this date.</td>
</tr>
@endforelse

</tbody>

</table>

<!-- =======================================================
     GROUP-WISE CASH DEPOSIT BREAKDOWN
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="4" style="text-align:left; background-color:#e6e6e6;">
        Group-Wise Cash Deposit Breakdown
    </th>
</tr>
<tr>
    <th>Reporting Group</th>
    <th width="20%">Amount To Deposit</th>
    <th>Reporting Group</th>
    <th width="20%">Amount To Deposit</th>
</tr>
</thead>

<tbody>

@php
$groupPairs = collect($groupBreakdown)->chunk(2);
@endphp

@forelse($groupPairs as $pair)
<tr>
    @foreach($pair as $row)
    <td>{{ $row['group_name'] }}</td>
    <td class="text-end {{ $row['amount'] >= 0 ? 'amt-positive' : 'amt-negative' }}">{{ fmtMoney4($row['amount']) }}</td>
    @endforeach
    @if($pair->count() === 1)
    <td colspan="2"></td>
    @endif
</tr>
@empty
<tr>
    <td colspan="4" class="text-center">No group breakdown available.</td>
</tr>
@endforelse

<tr>
    <td class="label-blue">Total</td>
    <td class="text-end" colspan="3"><b>Rs. {{ fmtMoney4($summary['net_cash_to_deposit']) }}</b></td>
</tr>

</tbody>

</table>

<!-- =======================================================
     LEDGER
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="8" style="text-align:left; background-color:#e6e6e6;">
        {{ $user->name }} &mdash; Daily Cash Submission Ledger for {{ $date->format('d-m-Y (l)') }}
    </th>
</tr>
<tr>
    <th width="12%">Invoice No</th>
    <th width="9%">Payment Status</th>
    <th width="14%">Category</th>
    <th width="9%">Txn No</th>
    <th width="22%">Txn From/To</th>
    <th width="8%">Time</th>
    <th width="9%">Amount</th>
    <th width="17%">Type</th>
</tr>
</thead>

<tbody>

@forelse($ledger as $row)
<tr>
    <td>{{ $row['invoice_no'] }}</td>
    <td class="text-center">{{ $row['payment_status'] }}</td>
    <td>{{ $row['category'] }}</td>
    <td>{{ $row['transaction_no'] }}</td>
    <td>{{ $row['transaction_to'] }}</td>
    <td>{{ $row['time_fmt'] }}</td>
    <td class="text-end {{ $row['amount'] >= 0 ? 'amt-positive' : 'amt-negative' }}">{{ fmtMoney4($row['amount']) }}</td>
    <td>{{ $row['type'] }}</td>
</tr>
@empty
<tr>
    <td colspan="8" class="text-center">No cash transactions found for this date.</td>
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

Submitted By ({{ $user->name }})

</td>

<td class="no-border" style="width:10%;"></td>

<td class="no-border text-center" style="width:45%;">

_____________________

<br>

Received By (Accounts)

</td>

</tr>

</table>

</div>

</body>

</html>
