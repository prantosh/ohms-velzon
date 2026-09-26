<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>All Invoices Report</title>

@php
if (!function_exists('fmtMoneyAir')) {
    function fmtMoneyAir($v)
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
tr { page-break-inside: avoid; }

.text-end { text-align: right; }
.text-center { text-align: center; }
.label-blue { font-weight: bold; }
.cancelled-row td { color: #777; text-decoration: line-through; }
.total-row td { font-weight: bold; background-color: #f2f2f2; }

</style>

</head>

<body>

@include('partials.pdf-header', ['reportTitle' => 'ALL INVOICES REPORT'])

<table>
    <tr>
        <td width="12%" class="label-blue">Date</td>
        <td width="21%">{{ $date->format('d-m-Y (l)') }}</td>
        <td width="12%" class="label-blue">Tab</td>
        <td width="21%">{{ $tabLabel }}</td>
        <td width="12%" class="label-blue">User</td>
        <td>{{ $filters['user'] }}</td>
    </tr>
    <tr>
        <td class="label-blue">Type</td>
        <td>{{ $filters['type'] }}</td>
        <td class="label-blue">Search</td>
        <td>{{ $filters['search'] !== '' ? $filters['search'] : '-' }}</td>
        <td class="label-blue">Invoices</td>
        <td>{{ $summary['count'] }} @if($summary['cancelled_count']) ({{ $summary['cancelled_count'] }} cancelled) @endif</td>
    </tr>
</table>

<table>

<thead>
<tr>
    <th width="3%">Sl</th>
    <th width="6%">Collected At</th>
    <th width="9%">Invoice No</th>
    <th width="9%">Type</th>
    <th>Patient</th>
    <th width="8%">Mobile</th>
    <th width="11%">Doctor</th>
    <th width="7%">Total</th>
    <th width="7%">Paid</th>
    <th width="7%">Due</th>
    <th width="6%">Mode</th>
    <th width="7%">Status</th>
    <th width="8%">Created By</th>
</tr>
</thead>

<tbody>

@forelse($rows as $i => $row)
<tr class="{{ $row->is_cancelled ? 'cancelled-row' : '' }}">
    <td class="text-center">{{ $i + 1 }}</td>
    <td class="text-center">{{ $row->collected_time_fmt ?: '-' }}</td>
    <td>{{ $row->invoice_no }}</td>
    <td>{{ $row->invoice_type_label }}</td>
    <td>{{ $row->patient_name ?: '-' }}</td>
    <td>{{ $row->patient_mobile_no ?: '-' }}</td>
    <td>{{ $row->doctor_display }}</td>
    <td class="text-end">{{ fmtMoneyAir($row->total_amount) }}</td>
    <td class="text-end">{{ fmtMoneyAir($row->paid_amount) }}</td>
    <td class="text-end">{{ fmtMoneyAir($row->due_amount) }}</td>
    <td class="text-center">{{ $row->payment_mode ?: '-' }}</td>
    <td class="text-center">{{ $row->is_cancelled ? 'Cancelled' : ($row->is_pending ? 'Pending' : ($row->status ?: 'Paid')) }}</td>
    <td>{{ $row->created_by_name }}</td>
</tr>
@empty
<tr>
    <td colspan="13" class="text-center">No invoices found.</td>
</tr>
@endforelse

@if($rows->count())
<tr class="total-row">
    <td colspan="7" class="text-end">Total (excluding cancelled)</td>
    <td class="text-end">{{ fmtMoneyAir($summary['total_amount']) }}</td>
    <td class="text-end">{{ fmtMoneyAir($summary['paid_amount']) }}</td>
    <td class="text-end">{{ fmtMoneyAir($summary['due_amount']) }}</td>
    <td colspan="3"></td>
</tr>
@endif

</tbody>

</table>

<table class="no-border" style="border:none; margin-top:6px;">
<tr>
    <td style="border:none; text-align:right;">Printed By: {{ auth()->user()->name ?? '-' }}</td>
</tr>
</table>

</body>

</html>
