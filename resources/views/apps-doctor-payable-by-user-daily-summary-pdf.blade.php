< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Doctor Payable Dashboard (By User) &mdash; Daily Summary
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

                    .paid {
                        color: #000;
                        font-weight: bold;
                    }
                </style>

        </head>

        <body>

@include('partials.pdf-header', ['reportTitle' => 'DOCTOR PAYABLE DASHBOARD (BY USER) -- DAILY SUMMARY'])

<!-- =======================================================
     INFO / SUMMARY
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">User</td>
    <td>{{ $userLabel }}</td>

    <td width="25%" class="label-blue">Range</td>
    <td>{{ $rangeLabel }}</td>
</tr>

<tr>
    <td class="label-blue">Printed By</td>
    <td>{{ $printedBy }}</td>

    <td class="label-blue">Printed On</td>
    <td>{{ now()->format('d-m-Y h:i A') }}</td>
</tr>

<tr>
    <td class="label-blue">Total Settled</td>
    <td>{{ $totals['settled_count'] }} (Rs. {{ fmtMoney6($totals['settled_amount']) }})</td>

    <td class="label-blue">Total Unsettled</td>
    <td>{{ $totals['unsettled_count'] }} (Rs. {{ fmtMoney6($totals['unsettled_amount']) }})</td>
</tr>

</table>

<!-- =======================================================
     DAILY SUMMARY
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="5" style="text-align:left; background-color:#e6e6e6;">
        Doctor Payables &mdash; Daily Summary
    </th>
</tr>
<tr>
    <th>Date</th>
    <th width="15%">Settled Payables</th>
    <th width="18%">Settled Amount</th>
    <th width="15%">Unsettled Payables</th>
    <th width="18%">Unsettled Amount</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row['date_fmt'] }}</td>
    <td class="text-center">{{ $row['settled_count'] }}</td>
    <td class="text-end paid">{{ fmtMoney6($row['settled_amount']) }}</td>
    <td class="text-center">{{ $row['unsettled_count'] }}</td>
    <td class="text-end">{{ fmtMoney6($row['unsettled_amount']) }}</td>
</tr>
@empty
<tr>
    <td colspan="5" class="text-center">No doctor payables found in this range.</td>
</tr>
@endforelse

</tbody>

</table>

</body>

</html>
