< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Test Report Delivery Log
                </title>

@php

function fmtMoney27($v)
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
                        font-size: 9px;
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
                            padding: 3px 5px;
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
                </style>

        </head>

        <body>

@include('partials.pdf-header', ['reportTitle' => 'TEST REPORT DELIVERY LOG'])

<!-- =======================================================
     INFO / SUMMARY
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">User</td>
    <td>{{ $userLabel }}</td>

    <td width="25%" class="label-blue">Search</td>
    <td>{{ $search ?: '-' }}</td>
</tr>

<tr>
    <td class="label-blue">From Date</td>
    <td>{{ $fromDateFmt ?? 'All' }}</td>

    <td class="label-blue">To Date</td>
    <td>{{ $toDateFmt ?? 'All' }}</td>
</tr>

<tr>
    <td class="label-blue">Printed By</td>
    <td>{{ $printedBy }}</td>

    <td class="label-blue">Printed On</td>
    <td>{{ now()->format('d-m-Y h:i A') }}</td>
</tr>

<tr>
    <td class="label-blue">Total Records</td>
    <td colspan="3">{{ $totalCount }}</td>
</tr>

</table>

<!-- =======================================================
     DELIVERY LOG
======================================================= -->

<table>

<thead>
<tr>
    <th>Invoice No</th>
    <th>Patient</th>
    <th>Delivered By</th>
    <th>Delivered At</th>
    <th>Payment Status</th>
    <th width="10%">Total</th>
    <th width="10%">Paid</th>
    <th width="10%">Due</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row['invoice_no'] }}</td>
    <td>{{ $row['patient_name'] }} {{ $row['patient_mobile_no'] ? '(' . $row['patient_mobile_no'] . ')' : '' }}</td>
    <td>{{ $row['delivered_by_name'] }}</td>
    <td>{{ $row['delivered_at_fmt'] }}</td>
    <td class="text-center">{{ $row['payment_status'] }}</td>
    <td class="text-end">{{ fmtMoney27($row['total_amount']) }}</td>
    <td class="text-end">{{ fmtMoney27($row['paid_amount']) }}</td>
    <td class="text-end">{{ fmtMoney27($row['due_amount']) }}</td>
</tr>
@empty
<tr>
    <td colspan="8" class="text-center">No delivery records found.</td>
</tr>
@endforelse

</tbody>

</table>

</body>

</html>
