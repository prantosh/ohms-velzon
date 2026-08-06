< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Employee Performance Statement
                </title>

@php
function fmtMoney6($v)
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
                </style>

        </head>

        <body>

@include('partials.pdf-header', ['reportTitle' => 'EMPLOYEE PERFORMANCE STATEMENT'])

<!-- =======================================================
     USER / PERIOD / SUMMARY
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">Employee</td>
    <td>{{ $user->name }} ({{ $user->role }})</td>

    <td width="25%" class="label-blue">Period</td>
    <td>{{ $fromDate->format('d-m-Y') }} to {{ $toDate->format('d-m-Y') }}</td>
</tr>

<tr>
    <td class="label-blue">Invoices</td>
    <td>{{ $summary['invoice_count'] }}</td>

    <td class="label-blue">Cash Collected</td>
    <td>Rs. {{ fmtMoney6($summary['cash_collected']) }}</td>
</tr>

<tr>
    <td class="label-blue">Non-Cash Collected</td>
    <td>Rs. {{ fmtMoney6($summary['non_cash_collected']) }}</td>

    <td class="label-blue">Cash Refunded</td>
    <td>Rs. {{ fmtMoney6($summary['cash_refunded']) }}</td>
</tr>

<tr>
    <td class="label-blue">Cash Paid To Doctors</td>
    <td>Rs. {{ fmtMoney6($summary['cash_paid_to_doctors']) }}</td>

    <td class="label-blue">Net Cash To Deposit</td>
    <td><b>Rs. {{ fmtMoney6($summary['net_cash_to_deposit']) }}</b></td>
</tr>

</table>

<!-- =======================================================
     DOCTOR PAYMENTS (CASH) MADE BY THIS EMPLOYEE
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="5" style="text-align:left; background-color:#e6e6e6;">
        Doctor Payments Made (Cash) By {{ $user->name }} In This Period
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
    <td class="text-end amt-negative">{{ fmtMoney6($row['amount']) }}</td>
</tr>
@empty
<tr>
    <td colspan="5" class="text-center">No cash payments made to doctors by this employee in this period.</td>
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
    <th colspan="2" style="text-align:left; background-color:#e6e6e6;">
        Group-Wise Cash Deposit Breakdown
    </th>
</tr>
<tr>
    <th>Reporting Group</th>
    <th width="20%">Amount</th>
</tr>
</thead>

<tbody>

@forelse($groupBreakdown as $row)
<tr>
    <td>
        {{ $row['group_name'] }}
        @if(!empty($row['items']))
        <br>
        <span style="color:#666; font-size:7.5px;">
            @foreach($row['items'] as $item)
            {{ $item['item_code'] ?? '-' }} {{ $item['item_description'] }} (Invoice {{ $item['invoice_no'] }})@if(!$loop->last)<br>@endif
            @endforeach
        </span>
        @endif
    </td>
    <td class="text-end {{ $row['amount'] >= 0 ? 'amt-positive' : 'amt-negative' }}">{{ fmtMoney6($row['amount']) }}</td>
</tr>
@empty
<tr>
    <td colspan="2" class="text-center">No group breakdown available.</td>
</tr>
@endforelse

<tr>
    <td class="label-blue">Total</td>
    <td class="text-end"><b>Rs. {{ fmtMoney6($summary['net_cash_to_deposit']) }}</b></td>
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
        {{ $user->name }} &mdash; Ledger for {{ $fromDate->format('d-m-Y') }} to {{ $toDate->format('d-m-Y') }}
    </th>
</tr>
<tr>
    <th>Invoice No</th>
    <th>Category</th>
    <th>Txn No</th>
    <th>Txn From/To</th>
    <th width="9%">Invoice Date</th>
    <th width="9%">Time</th>
    <th width="10%">Amount</th>
    <th width="9%">Type</th>
</tr>
</thead>

<tbody>

@forelse($ledger as $row)
<tr>
    <td>{{ $row['invoice_no'] }}</td>
    <td>{{ $row['category'] }}</td>
    <td>{{ $row['transaction_no'] }}</td>
    <td>{{ $row['transaction_to'] }}</td>
    <td>{{ $row['invoice_date_fmt'] }}</td>
    <td>{{ $row['time_fmt'] }}</td>
    <td class="text-end {{ $row['amount'] >= 0 ? 'amt-positive' : 'amt-negative' }}">{{ fmtMoney6($row['amount']) }}</td>
    <td>{{ $row['type'] }}</td>
</tr>
@empty
<tr>
    <td colspan="8" class="text-center">No transactions found for this period.</td>
</tr>
@endforelse

</tbody>

</table>

        </body>

    </html>
