< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Item Wise Report - Detail
                </title>

@php

function fmtMoney9($v)
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

@include('partials.pdf-header', ['reportTitle' => 'ITEM WISE REPORT - DETAIL'])

<!-- =======================================================
     FILTERS / TOTALS
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
    <td><b>Rs. {{ fmtMoney9($grandTotal['amount']) }}</b></td>

    <td class="label-blue">Settled Amount</td>
    <td><b>Rs. {{ fmtMoney9($grandTotal['settled_amount'] ?? 0) }}</b></td>
</tr>

</table>

<!-- =======================================================
     DETAIL
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="9" style="text-align:left; background-color:#e6e6e6;">
        Invoices billing {{ $itemName }} between {{ $fromDate->format('d-m-Y') }} and {{ $toDate->format('d-m-Y') }}
    </th>
</tr>
<tr>
    <th width="4%">SL</th>
    <th width="11%">Invoice No</th>
    <th width="9%">Invoice Date</th>
    <th>Patient Name</th>
    <th>Test(s) / Item(s)</th>
    <th width="11%">Amount</th>
    <th width="9%">Payment Mode</th>
    <th width="8%">Status</th>
    <th width="8%">Settled?</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td class="text-center">{{ $loop->iteration }}</td>
    <td>{{ $row['invoice_no'] }}</td>
    <td>{{ $row['invoice_date_fmt'] }}</td>
    <td>{{ $row['patient_name'] ?: '-' }}</td>
    <td>{{ $row['sub_item_label'] ?: '-' }}</td>
    <td class="text-end">{{ fmtMoney9($row['item_amount']) }}</td>
    <td class="text-center">{{ $row['payment_mode'] ?? '-' }}</td>
    <td class="text-center">{{ $row['payment_status'] }}</td>
    <td class="text-center">{{ ($row['is_settled'] ?? false) ? 'Settled' : 'Pending' }}</td>
</tr>
@empty
<tr>
    <td colspan="9" class="text-center">No invoices found for this item in the selected period.</td>
</tr>
@endforelse

<tr style="background-color:#f0f0f0; font-weight:bold;">
    <td colspan="5" class="text-end">Total</td>
    <td class="text-end">{{ fmtMoney9($grandTotal['amount']) }}</td>
    <td colspan="3"></td>
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
