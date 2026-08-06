< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Purchase Order Report - Summary
                </title>

@php

function fmtMoney12($v)
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

@include('partials.pdf-header', ['reportTitle' => 'PURCHASE ORDER REPORT - SUMMARY'])

<!-- =======================================================
     FILTERS / TOTALS
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">Period</td>
    <td>{{ $fromDate->format('d-m-Y') }} to {{ $toDate->format('d-m-Y') }}</td>

    <td width="25%" class="label-blue">Printed By</td>
    <td>{{ $printedBy }}</td>
</tr>

<tr>
    <td class="label-blue">Total Qty</td>
    <td>{{ fmtMoney12($grandTotal['qty']) }}</td>

    <td class="label-blue">Total Amount</td>
    <td><b>Rs. {{ fmtMoney12($grandTotal['amount']) }}</b></td>
</tr>

</table>

<!-- =======================================================
     BY ITEM
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="6" style="text-align:left; background-color:#e6e6e6;">By Item</th>
</tr>
<tr>
    <th width="12%">Item Code</th>
    <th>Item Name</th>
    <th width="8%">UOM</th>
    <th width="12%">PO Count</th>
    <th width="14%">Qty</th>
    <th width="16%">Amount</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row['item_code'] }}</td>
    <td>{{ $row['item_name'] }}</td>
    <td class="text-center">{{ $row['uom'] }}</td>
    <td class="text-center">{{ $row['po_count'] }}</td>
    <td class="text-end">{{ fmtMoney12($row['qty']) }}</td>
    <td class="text-end">{{ fmtMoney12($row['amount']) }}</td>
</tr>
@empty
<tr>
    <td colspan="6" class="text-center">No purchase order lines found for the selected period.</td>
</tr>
@endforelse

<tr style="background-color:#f0f0f0; font-weight:bold;">
    <td colspan="4" class="text-end">Total</td>
    <td class="text-end">{{ fmtMoney12($grandTotal['qty']) }}</td>
    <td class="text-end">{{ fmtMoney12($grandTotal['amount']) }}</td>
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
