<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>Diagnostic Invoices</title>

@php
if (!function_exists('fmtMoneyDil')) {
    function fmtMoneyDil($v)
    {
        return number_format((float) ($v ?? 0), 2);
    }
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
    padding: 4px;
    vertical-align: top;
}

table thead th {
    font-weight: bold;
    text-align: center;
    background-color: #f2f2f2;
}

thead { display: table-header-group; }

.text-end { text-align: right; }
.text-center { text-align: center; }
.label-blue { font-weight: bold; }
.cancelled-row td { color: #777; text-decoration: line-through; }
.total-row td { font-weight: bold; background-color: #f2f2f2; }

</style>

</head>

<body>

@include('partials.pdf-header', ['reportTitle' => 'DIAGNOSTIC INVOICES'])

<table>
    <tr>
        <td width="12%" class="label-blue">Category</td>
        <td width="21%">{{ $categoryLabel }}</td>
        <td width="12%" class="label-blue">Payment</td>
        <td width="21%">{{ $paymentLabel }}</td>
        <td width="12%" class="label-blue">Period</td>
        <td>{{ $rangeLabel }}</td>
    </tr>
    <tr>
        <td class="label-blue">Search</td>
        <td>{{ $search !== '' ? $search : '-' }}</td>
        <td class="label-blue">Invoices</td>
        <td colspan="3">{{ $summary['count'] }}</td>
    </tr>
</table>

<table>

<thead>
<tr>
    <th width="3%">Sl</th>
    <th width="11%">Invoice No.</th>
    <th width="11%">Patient ID</th>
    <th>Patient Name</th>
    <th width="7%">Invoice Date</th>
    <th width="7%">Test Date</th>
    <th width="7%">Total</th>
    <th width="7%">Discount</th>
    <th width="7%">Paid</th>
    <th width="7%">Due</th>
    <th width="7%">Paid to Doctor</th>
    <th width="7%">Status</th>
</tr>
</thead>

<tbody>

@forelse($rows as $i => $row)
<tr class="{{ $row->cancelled === 'Y' ? 'cancelled-row' : '' }}">
    <td class="text-center">{{ $i + 1 }}</td>
    <td>{{ $row->invoice_no }}</td>
    <td>{{ $row->patient_id }}</td>
    <td>{{ $row->patient_name ?: '-' }}</td>
    <td class="text-center">{{ $row->invoice_date_fmt }}</td>
    <td class="text-center">{{ $row->test_date_fmt }}</td>
    <td class="text-end">{{ fmtMoneyDil($row->total_amount) }}</td>
    <td class="text-end">{{ fmtMoneyDil($row->discount) }}</td>
    <td class="text-end">{{ fmtMoneyDil($row->paid_amount) }}</td>
    <td class="text-end">{{ fmtMoneyDil($row->due_amount) }}</td>
    <td class="text-end">{{ fmtMoneyDil($row->doctor_payment_amount) }}</td>
    <td class="text-center">{{ $row->status_label }}</td>
</tr>
@empty
<tr>
    <td colspan="12" class="text-center">No invoices found.</td>
</tr>
@endforelse

@if($rows->count())
<tr class="total-row">
    <td colspan="6" class="text-end">Total (excluding cancelled)</td>
    <td class="text-end">{{ fmtMoneyDil($summary['total']) }}</td>
    <td class="text-end">{{ fmtMoneyDil($summary['discount']) }}</td>
    <td class="text-end">{{ fmtMoneyDil($summary['paid']) }}</td>
    <td class="text-end">{{ fmtMoneyDil($summary['due']) }}</td>
    <td class="text-end">{{ fmtMoneyDil($summary['doctor']) }}</td>
    <td></td>
</tr>
@endif

</tbody>

</table>

<table style="border:none; margin-top:6px;">
<tr>
    <td style="border:none; text-align:right;">Printed By: {{ auth()->user()->name ?? '-' }}</td>
</tr>
</table>

</body>

</html>
