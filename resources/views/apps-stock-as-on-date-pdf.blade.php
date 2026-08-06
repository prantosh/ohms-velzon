< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Stock As On Date
                </title>

@php

function fmtMoney17($v)
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

@include('partials.pdf-header', ['reportTitle' => 'STOCK AS ON DATE'])

<!-- =======================================================
     FILTERS / TOTALS
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">As On Date</td>
    <td>{{ $asOnDate }}</td>

    <td width="25%" class="label-blue">Printed By</td>
    <td>{{ $printedBy }}</td>
</tr>

<tr>
    <td class="label-blue">Total Closing Qty</td>
    <td>{{ fmtMoney17($grandTotal['closing_qty']) }}</td>

    <td class="label-blue">Total Closing Value</td>
    <td><b>Rs. {{ fmtMoney17($grandTotal['closing_value']) }}</b></td>
</tr>

</table>

<!-- =======================================================
     STOCK
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="8" style="text-align:left; background-color:#e6e6e6;">
        Item-wise Closing Stock as on {{ $asOnDate }}
    </th>
</tr>
<tr>
    <th width="5%">SL</th>
    <th width="12%">Item Code</th>
    <th>Item Name</th>
    <th width="14%">Category</th>
    <th width="6%">UOM</th>
    <th width="12%">Closing Qty</th>
    <th width="12%">Eff. Rate</th>
    <th width="14%">Closing Value</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td class="text-center">{{ $loop->iteration }}</td>
    <td>{{ $row['item_code'] }}</td>
    <td>{{ $row['item_name'] }}</td>
    <td>{{ $row['category_name'] ?: '-' }}</td>
    <td class="text-center">{{ $row['uom'] }}</td>
    <td class="text-end">{{ fmtMoney17($row['closing_qty']) }}</td>
    <td class="text-end">{{ fmtMoney17($row['effective_rate']) }}</td>
    <td class="text-end">{{ fmtMoney17($row['closing_value']) }}</td>
</tr>
@empty
<tr>
    <td colspan="8" class="text-center">No items found for the selected filters.</td>
</tr>
@endforelse

<tr style="background-color:#f0f0f0; font-weight:bold;">
    <td colspan="5" class="text-end">Total</td>
    <td class="text-end">{{ fmtMoney17($grandTotal['closing_qty']) }}</td>
    <td></td>
    <td class="text-end">{{ fmtMoney17($grandTotal['closing_value']) }}</td>
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
