<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>Item Wise Summary Report - Detail</title>

@php
function fmtMoney26($v)
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

@include('partials.pdf-header', ['reportTitle' => 'ITEM WISE SUMMARY REPORT - DETAIL'])

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
    <td class="label-blue">Invoices</td>
    <td>{{ $grandTotal['invoice_count'] }}</td>

    <td class="label-blue">Billed / Settled</td>
    <td><b>Rs. {{ fmtMoney26($grandTotal['amount']) }} / Rs. {{ fmtMoney26($grandTotal['settled_amount']) }}</b></td>
</tr>

</table>

<!-- =======================================================
     DETAIL
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="7" style="text-align:left; background-color:#e6e6e6;">
        Invoice Detail, {{ $fromDate->format('d-m-Y') }} to {{ $toDate->format('d-m-Y') }}
    </th>
</tr>
<tr>
    <th width="8%">Invoice Date</th>
    <th width="10%">Invoice No</th>
    <th width="14%">Patient</th>
    <th>Item / Sub-Item</th>
    <th width="9%">Amount</th>
    <th width="9%">Payment Mode</th>
    <th width="8%">Status</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row['invoice_date_fmt'] }}</td>
    <td>{{ $row['invoice_no'] }}</td>
    <td>{{ $row['patient_name'] ?: '-' }}</td>
    <td>{{ $row['sub_item_label'] ?: '-' }}</td>
    <td class="text-end">{{ fmtMoney26($row['item_amount']) }}</td>
    <td>{{ $row['payment_mode'] }}</td>
    <td class="text-center">{{ $row['is_settled'] ? 'Settled' : 'Pending' }}</td>
</tr>
@empty
<tr>
    <td colspan="7" class="text-center">No invoices found for the selected item and period.</td>
</tr>
@endforelse

<tr style="background-color:#f0f0f0; font-weight:bold;">
    <td colspan="4" class="text-end">Total</td>
    <td class="text-end">{{ fmtMoney26($grandTotal['amount']) }}</td>
    <td colspan="2"></td>
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
