< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Doctor Payable Dashboard (By User)
                </title>

@php

function fmtMoney5($v)
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

                    .balance {
                        color: #000;
                        font-weight: bold;
                    }

                    .paid {
                        color: #000;
                        font-weight: bold;
                    }
                </style>

        </head>

        <body>

@include('partials.pdf-header', ['reportTitle' => 'DOCTOR PAYABLE DASHBOARD (BY USER)'])

<!-- =======================================================
     INFO / SUMMARY
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">User</td>
    <td>{{ $userLabel }}</td>

    <td width="25%" class="label-blue">Settled Range</td>
    <td>{{ $rangeLabel }}</td>
</tr>

<tr>
    <td class="label-blue">Printed By</td>
    <td>{{ $printedBy }}</td>

    <td class="label-blue">Printed On</td>
    <td>{{ now()->format('d-m-Y h:i A') }}</td>
</tr>

<tr>
    <td class="label-blue">Pending Payables</td>
    <td>{{ $summary['pending_count'] }} (Balance Rs. {{ fmtMoney5($summary['pending_balance_amount']) }})</td>

    <td class="label-blue">Settled Payables</td>
    <td>{{ $summary['settled_count'] }} (Amount Rs. {{ fmtMoney5($summary['settled_amount']) }})</td>
</tr>

</table>

<!-- =======================================================
     PENDING
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="{{ $isAllUsers ? 11 : 10 }}" style="text-align:left; background-color:#e6e6e6;">
        Pending Doctor Payables &mdash; All Time
    </th>
</tr>
<tr>
    <th>Payable No</th>
    <th>Invoice No</th>
    <th>Category</th>
    @if($isAllUsers)
    <th>User</th>
    @endif
    <th>Doctor</th>
    <th>Patient</th>
    <th>Item</th>
    <th width="8%">Payable</th>
    <th width="8%">Paid</th>
    <th width="8%">Balance</th>
    <th width="7%">Status</th>
</tr>
</thead>

<tbody>

@forelse($pending as $row)
<tr>
    <td>{{ $row->payable_no }}</td>
    <td>{{ $row->invoice_no }}</td>
    <td>{{ $row->category }}</td>
    @if($isAllUsers)
    <td>{{ $row->user_name }}</td>
    @endif
    <td>{{ $row->doctor_name }}</td>
    <td>{{ $row->patient_name }}</td>
    <td>{{ $row->item_description }}</td>
    <td class="text-end">{{ fmtMoney5($row->payable_amount) }}</td>
    <td class="text-end">{{ fmtMoney5($row->paid_amount) }}</td>
    <td class="text-end balance">{{ fmtMoney5($row->balance_amount) }}</td>
    <td class="text-center">{{ $row->payment_status }}</td>
</tr>
@empty
<tr>
    <td colspan="{{ $isAllUsers ? 11 : 10 }}" class="text-center">No pending doctor payables found.</td>
</tr>
@endforelse

</tbody>

</table>

</body>

</html>
