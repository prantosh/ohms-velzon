< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Item Wise Report - Summary
                </title>

@php

function fmtMoney10($v)
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

@include('partials.pdf-header', ['reportTitle' => 'ITEM WISE REPORT - SUMMARY'])

<!-- =======================================================
     RANGE / TOTALS
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">Item</td>
    <td>{{ $itemName }}</td>

    <td width="25%" class="label-blue">Period</td>
    <td>{{ $fromDate->format('d-m-Y') }} to {{ $toDate->format('d-m-Y') }}</td>
</tr>

<tr>
    <td class="label-blue">Total Invoices</td>
    <td>{{ $grandTotal['invoice_count'] }}</td>

    <td class="label-blue">Printed By</td>
    <td>{{ $printedBy }}</td>
</tr>

<tr>
    <td class="label-blue">Total Amount</td>
    <td colspan="3"><b>Rs. {{ fmtMoney10($grandTotal['amount']) }}</b></td>
</tr>

</table>

<!-- =======================================================
     BY DATE
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="3" style="text-align:left; background-color:#e6e6e6;">By Date</th>
</tr>
<tr>
    <th width="30%">Date</th>
    <th width="20%">Invoices</th>
    <th>Amount</th>
</tr>
</thead>

<tbody>

@forelse($byDate as $row)
<tr>
    <td>{{ $row['date_fmt'] }}</td>
    <td class="text-center">{{ $row['invoice_count'] }}</td>
    <td class="text-end">{{ fmtMoney10($row['amount']) }}</td>
</tr>
@empty
<tr>
    <td colspan="3" class="text-center">No invoices found for this item in the selected period.</td>
</tr>
@endforelse

<tr style="background-color:#f0f0f0; font-weight:bold;">
    <td>Total</td>
    <td class="text-center">{{ $grandTotal['invoice_count'] }}</td>
    <td class="text-end">{{ fmtMoney10($grandTotal['amount']) }}</td>
</tr>

</tbody>

</table>

<!-- =======================================================
     BY TEST / DOCTOR
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="3" style="text-align:left; background-color:#e6e6e6;">By Test / Doctor</th>
</tr>
<tr>
    <th>Description</th>
    <th width="20%">Count</th>
    <th width="20%">Amount</th>
</tr>
</thead>

<tbody>

@forelse($bySubItem as $row)
<tr>
    <td>{{ $row['label'] }}</td>
    <td class="text-center">{{ $row['invoice_count'] }}</td>
    <td class="text-end">{{ fmtMoney10($row['amount']) }}</td>
</tr>
@empty
<tr>
    <td colspan="3" class="text-center">No invoices found for this item in the selected period.</td>
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
