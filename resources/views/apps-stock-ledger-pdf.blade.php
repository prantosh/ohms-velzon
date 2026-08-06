< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Stock Ledger
                </title>

@php

function fmtMoney18($v)
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

@include('partials.pdf-header', ['reportTitle' => 'STOCK LEDGER'])

<!-- =======================================================
     ITEM / PERIOD
======================================================= -->

<table class="summary-table">

<tr>
    <td width="20%" class="label-blue">Item Code</td>
    <td width="30%">{{ $item['item_code'] }}</td>

    <td width="20%" class="label-blue">Item Name</td>
    <td>{{ $item['item_name'] }}</td>
</tr>

<tr>
    <td class="label-blue">UOM</td>
    <td>{{ $item['uom'] }}</td>

    <td class="label-blue">Period</td>
    <td>
        {{ $fromDate ? $fromDate->format('d-m-Y') : 'Beginning' }}
        to
        {{ $toDate ? $toDate->format('d-m-Y') : 'Date' }}
    </td>
</tr>

<tr>
    <td class="label-blue">Opening Balance</td>
    <td>Qty {{ fmtMoney18($openingQty) }} / Value Rs. {{ fmtMoney18($openingValue) }}</td>

    <td class="label-blue">Printed By</td>
    <td>{{ $printedBy }}</td>
</tr>

</table>

<!-- =======================================================
     LEDGER
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="8" style="text-align:left; background-color:#e6e6e6;">
        Transaction History
    </th>
</tr>
<tr>
    <th width="5%">SL</th>
    <th width="10%">Date</th>
    <th width="12%">Txn No</th>
    <th width="10%">Type</th>
    <th width="10%">Qty</th>
    <th width="10%">Rate</th>
    <th width="12%">Value</th>
    <th width="15%">Balance (Qty / Value)</th>
</tr>
</thead>

<tbody>

<tr style="background-color:#f8f8f8;">
    <td colspan="7" class="text-end"><b>Opening Balance</b></td>
    <td class="text-center"><b>{{ fmtMoney18($openingQty) }} / {{ fmtMoney18($openingValue) }}</b></td>
</tr>

@forelse($ledger as $row)
<tr>
    <td class="text-center">{{ $loop->iteration }}</td>
    <td>{{ $row['txn_date'] }}</td>
    <td>{{ $row['txn_no'] }}</td>
    <td class="text-center">{{ $row['type'] }}</td>
    <td class="text-end">{{ fmtMoney18($row['qty']) }}</td>
    <td class="text-end">{{ fmtMoney18($row['rate']) }}</td>
    <td class="text-end">{{ fmtMoney18($row['value']) }}</td>
    <td class="text-center">{{ fmtMoney18($row['balance_qty']) }} / {{ fmtMoney18($row['balance_value']) }}</td>
</tr>
@empty
<tr>
    <td colspan="8" class="text-center">No transactions found for this item in the selected period.</td>
</tr>
@endforelse

<tr style="background-color:#f0f0f0; font-weight:bold;">
    <td colspan="7" class="text-end">Closing Balance</td>
    <td class="text-center">{{ fmtMoney18($closingQty) }} / {{ fmtMoney18($closingValue) }}</td>
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
