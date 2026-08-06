< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Purchase Order Report - Detail
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
                            margin-right: 30px;
                            margin-bottom: 40px;
                            margin-left: 30px;
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
                            padding: 3px 5px;
                            vertical-align: top;
                        }

                    .summary-table th,
                    .summary-table td {
                        border: 1px solid #ccc;
                        font-size: 9px;
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

@include('partials.pdf-header', ['reportTitle' => 'PURCHASE ORDER REPORT - DETAIL'])

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
    <td>{{ fmtMoney11($grandTotal['qty']) }}</td>

    <td class="label-blue">Total Amount</td>
    <td><b>Rs. {{ fmtMoney11($grandTotal['amount']) }}</b></td>
</tr>

</table>

<!-- =======================================================
     DETAIL
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="12" style="text-align:left; background-color:#e6e6e6;">
        Purchase Order Lines between {{ $fromDate->format('d-m-Y') }} and {{ $toDate->format('d-m-Y') }}
    </th>
</tr>
<tr>
    <th width="5%">SL</th>
    <th width="8%">PO No</th>
    <th width="7%">PO Date</th>
    <th width="12%">Vendor</th>
    <th width="8%">Item Code</th>
    <th>Item Name</th>
    <th width="5%">UOM</th>
    <th width="7%">Qty</th>
    <th width="7%">Rate</th>
    <th width="5%">GST%</th>
    <th width="8%">Amount</th>
    <th width="7%">Received</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td class="text-center">{{ $loop->iteration }}</td>
    <td>{{ $row['po_no'] }}</td>
    <td>{{ $row['po_date_fmt'] }}</td>
    <td>{{ $row['vendor_name'] ?: '-' }}</td>
    <td>{{ $row['item_code'] }}</td>
    <td>{{ $row['item_name'] }}</td>
    <td class="text-center">{{ $row['uom'] }}</td>
    <td class="text-end">{{ fmtMoney11($row['po_qty']) }}</td>
    <td class="text-end">{{ fmtMoney11($row['unit_rate']) }}</td>
    <td class="text-end">{{ fmtMoney11($row['gst_percent']) }}</td>
    <td class="text-end">{{ fmtMoney11($row['amount']) }}</td>
    <td class="text-end">{{ fmtMoney11($row['received_qty']) }}</td>
</tr>
@empty
<tr>
    <td colspan="12" class="text-center">No purchase order lines found for the selected period.</td>
</tr>
@endforelse

<tr style="background-color:#f0f0f0; font-weight:bold;">
    <td colspan="7" class="text-end">Total</td>
    <td class="text-end">{{ fmtMoney11($grandTotal['qty']) }}</td>
    <td></td>
    <td></td>
    <td class="text-end">{{ fmtMoney11($grandTotal['amount']) }}</td>
    <td></td>
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
