<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>Item Wise Summary Report - Summary</title>

@php
function fmtMoney25($v)
{
    return number_format((float) ($v ?? 0), 2);
}
@endphp

<style>

@page {
    margin-top: 1in;
    margin-right: 25px;
    margin-bottom: 40px;
    margin-left: 25px;
}

@page :first {
    margin-top: 0.4in;
}

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 7px;
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
    padding: 3px;
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
    margin-top: 30px;
}
</style>

</head>

<body>

@include('partials.pdf-header', ['reportTitle' => 'ITEM WISE SUMMARY REPORT'])

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
    <td class="label-blue">Invoices</td>
    <td>{{ $grandTotal['invoice_count'] }}</td>

    <td class="label-blue">Receivable / Received / Deposited</td>
    <td><b>Rs. {{ fmtMoney25($grandTotal['receivable']) }} / Rs. {{ fmtMoney25($grandTotal['received_total']) }} / Rs. {{ fmtMoney25($grandTotal['deposited']) }}</b></td>
</tr>

</table>

<!-- =======================================================
     SUMMARY
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="11" style="text-align:left; background-color:#e6e6e6;">
        Item Wise Summary, {{ $fromDate->format('d-m-Y') }} to {{ $toDate->format('d-m-Y') }}
    </th>
</tr>
<tr>
    <th>Item</th>
    <th width="6%">Code</th>
    <th width="6%">Invoices</th>
    <th width="8%">Receivable</th>
    <th width="8%">Received Cash</th>
    <th width="8%">Received Non-Cash</th>
    <th width="8%">Received Total</th>
    <th width="8%">Settled</th>
    <th width="7%">Refund Cash</th>
    <th width="9%">Doctor Payment Cash</th>
    <th width="8%">Deposited</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row['item_name'] }}</td>
    <td>{{ $row['item_code'] }}</td>
    <td class="text-center">{{ $row['invoice_count'] }}</td>
    <td class="text-end">{{ fmtMoney25($row['receivable']) }}</td>
    <td class="text-end">{{ fmtMoney25($row['received_cash']) }}</td>
    <td class="text-end">{{ fmtMoney25($row['received_noncash']) }}</td>
    <td class="text-end">{{ fmtMoney25($row['received_total']) }}</td>
    <td class="text-end">{{ fmtMoney25($row['settled_amount']) }}</td>
    <td class="text-end">{{ fmtMoney25($row['refund_cash']) }}</td>
    <td class="text-end">{{ fmtMoney25($row['doctor_payment_cash']) }}</td>
    <td class="text-end">{{ fmtMoney25($row['deposited']) }}</td>
</tr>
@empty
<tr>
    <td colspan="11" class="text-center">No activity found for the selected period.</td>
</tr>
@endforelse

<tr style="background-color:#f0f0f0; font-weight:bold;">
    <td colspan="2" class="text-end">Total</td>
    <td class="text-center">{{ $grandTotal['invoice_count'] }}</td>
    <td class="text-end">{{ fmtMoney25($grandTotal['receivable']) }}</td>
    <td class="text-end">{{ fmtMoney25($grandTotal['received_cash']) }}</td>
    <td class="text-end">{{ fmtMoney25($grandTotal['received_noncash']) }}</td>
    <td class="text-end">{{ fmtMoney25($grandTotal['received_total']) }}</td>
    <td class="text-end">{{ fmtMoney25($grandTotal['settled_amount']) }}</td>
    <td class="text-end">{{ fmtMoney25($grandTotal['refund_cash']) }}</td>
    <td class="text-end">{{ fmtMoney25($grandTotal['doctor_payment_cash']) }}</td>
    <td class="text-end">{{ fmtMoney25($grandTotal['deposited']) }}</td>
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
