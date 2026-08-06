< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Monthly Reconciliation Report
                </title>

@php

function fmtMoney11($v)
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
                        margin-top: 8px;
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

@include('partials.pdf-header', ['reportTitle' => 'MONTHLY RECONCILIATION REPORT'])

<!-- =======================================================
     PERIOD / TOTALS
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">Month</td>
    <td>{{ $monthName }}</td>

    <td width="25%" class="label-blue">Printed By</td>
    <td>{{ $printedBy }}</td>
</tr>

<tr>
    <td class="label-blue">Total Deposit in Cash</td>
    <td><b>Rs. {{ fmtMoney11($grandTotal['deposit_cash']) }}</b></td>

    <td class="label-blue">Total Income in Month</td>
    <td><b>Rs. {{ fmtMoney11($grandTotal['total_income']) }}</b></td>
</tr>

</table>

<!-- =======================================================
     CATEGORY-WISE BREAKDOWN
======================================================= -->

<table>

<thead>
<tr>
    <th>Name of Category</th>
    <th>Received Cash</th>
    <th>Received Card / Non-Cash</th>
    <th>Refund Cash</th>
    <th>Doctor Payment Cash</th>
    <th>Deposit in Cash</th>
    <th>Doctor Payment (Non-Cash Source)</th>
    <th>Total Income</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row['category'] }}</td>
    <td class="text-end">{{ fmtMoney11($row['received_cash']) }}</td>
    <td class="text-end">{{ fmtMoney11($row['received_noncash']) }}</td>
    <td class="text-end">{{ fmtMoney11($row['refund_cash']) }}</td>
    <td class="text-end">{{ fmtMoney11($row['doctor_payment_cash']) }}</td>
    <td class="text-end">{{ fmtMoney11($row['deposit_cash']) }}</td>
    <td class="text-end">{{ fmtMoney11($row['doctor_payment_noncash_source']) }}</td>
    <td class="text-end">{{ fmtMoney11($row['total_income']) }}</td>
</tr>
@empty
<tr>
    <td colspan="8" class="text-center">No activity found for the selected month.</td>
</tr>
@endforelse

<tr style="background-color:#f0f0f0; font-weight:bold;">
    <td>Total</td>
    <td class="text-end">{{ fmtMoney11($grandTotal['received_cash']) }}</td>
    <td class="text-end">{{ fmtMoney11($grandTotal['received_noncash']) }}</td>
    <td class="text-end">{{ fmtMoney11($grandTotal['refund_cash']) }}</td>
    <td class="text-end">{{ fmtMoney11($grandTotal['doctor_payment_cash']) }}</td>
    <td class="text-end">{{ fmtMoney11($grandTotal['deposit_cash']) }}</td>
    <td class="text-end">{{ fmtMoney11($grandTotal['doctor_payment_noncash_source']) }}</td>
    <td class="text-end">{{ fmtMoney11($grandTotal['total_income']) }}</td>
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
