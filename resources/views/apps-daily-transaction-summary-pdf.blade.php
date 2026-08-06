< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Daily Transaction Summary
                </title>

@php

function fmtMoney7($v)
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
                        font-size: 8px;
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

@include('partials.pdf-header', ['reportTitle' => 'DAILY TRANSACTION SUMMARY'])

<!-- =======================================================
     RANGE / TOTALS
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">From Date</td>
    <td>{{ $fromDate->format('d-m-Y (l)') }}</td>

    <td width="25%" class="label-blue">To Date</td>
    <td>{{ $toDate->format('d-m-Y (l)') }}</td>
</tr>

<tr>
    <td class="label-blue">Total Collection</td>
    <td>Rs. {{ fmtMoney7($grandTotal['collection_total']) }}</td>

    <td class="label-blue">Total Refund</td>
    <td>Rs. {{ fmtMoney7($grandTotal['refund_total']) }}</td>
</tr>

<tr>
    <td class="label-blue">Total Payment</td>
    <td>Rs. {{ fmtMoney7($grandTotal['payment_total']) }}</td>

    <td class="label-blue">Printed By</td>
    <td>{{ $printedBy }}</td>
</tr>

<tr>
    <td class="label-blue">Net</td>
    <td colspan="3"><b>Rs. {{ fmtMoney7($grandTotal['net_total']) }}</b></td>
</tr>

</table>

<!-- =======================================================
     DAY-WISE BREAKDOWN
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="11" style="text-align:left; background-color:#e6e6e6;">
        Day-Wise Collection / Refund / Payment for {{ $fromDate->format('d-m-Y') }} to {{ $toDate->format('d-m-Y') }}
    </th>
</tr>
<tr>
    <th rowspan="2" width="10%">Date</th>
    <th colspan="3">Collection</th>
    <th colspan="3">Refund</th>
    <th colspan="3">Payment</th>
    <th rowspan="2" width="8%">Net</th>
</tr>
<tr>
    <th width="7%">Cash</th>
    <th width="7%">Non-Cash</th>
    <th width="7%">Total</th>
    <th width="7%">Cash</th>
    <th width="7%">Non-Cash</th>
    <th width="7%">Total</th>
    <th width="7%">Cash</th>
    <th width="7%">Non-Cash</th>
    <th width="7%">Total</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row['date_fmt'] }}</td>
    <td class="text-end">{{ fmtMoney7($row['collection_cash']) }}</td>
    <td class="text-end">{{ fmtMoney7($row['collection_noncash']) }}</td>
    <td class="text-end">{{ fmtMoney7($row['collection_total']) }}</td>
    <td class="text-end">{{ fmtMoney7($row['refund_cash']) }}</td>
    <td class="text-end">{{ fmtMoney7($row['refund_noncash']) }}</td>
    <td class="text-end">{{ fmtMoney7($row['refund_total']) }}</td>
    <td class="text-end">{{ fmtMoney7($row['payment_cash']) }}</td>
    <td class="text-end">{{ fmtMoney7($row['payment_noncash']) }}</td>
    <td class="text-end">{{ fmtMoney7($row['payment_total']) }}</td>
    <td class="text-end">{{ fmtMoney7($row['net_total']) }}</td>
</tr>
@empty
<tr>
    <td colspan="11" class="text-center">No transactions found in this date range.</td>
</tr>
@endforelse

<tr style="background-color:#f0f0f0; font-weight:bold;">
    <td>Total</td>
    <td class="text-end">{{ fmtMoney7($grandTotal['collection_cash']) }}</td>
    <td class="text-end">{{ fmtMoney7($grandTotal['collection_noncash']) }}</td>
    <td class="text-end">{{ fmtMoney7($grandTotal['collection_total']) }}</td>
    <td class="text-end">{{ fmtMoney7($grandTotal['refund_cash']) }}</td>
    <td class="text-end">{{ fmtMoney7($grandTotal['refund_noncash']) }}</td>
    <td class="text-end">{{ fmtMoney7($grandTotal['refund_total']) }}</td>
    <td class="text-end">{{ fmtMoney7($grandTotal['payment_cash']) }}</td>
    <td class="text-end">{{ fmtMoney7($grandTotal['payment_noncash']) }}</td>
    <td class="text-end">{{ fmtMoney7($grandTotal['payment_total']) }}</td>
    <td class="text-end">{{ fmtMoney7($grandTotal['net_total']) }}</td>
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
