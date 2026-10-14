<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>Expenditure Detail</title>

@php
$money = fn ($v) => number_format((float) ($v ?? 0), 2);
@endphp

<style>

@page { margin-top: 1in; margin-right: 30px; margin-bottom: 35px; margin-left: 30px; }
@page :first { margin-top: 0.4in; }

body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #000; margin: 0; padding: 0; }

table { width: 100%; border-collapse: collapse; margin-top: 6px; }
table th, table td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
table thead th { background-color: #e6e6e6; text-align: center; font-weight: bold; }

.text-end { text-align: right; }
.text-center { text-align: center; }
.label-blue { font-weight: bold; }
.cat-header { background-color: #d9d9d9; font-weight: bold; text-align: left; font-size: 9px; }
.total-row td { font-weight: bold; background-color: #f1f1f1; }
.info td { border: 1px solid #ccc; }

</style>

</head>

<body>

@include('partials.pdf-header', ['reportTitle' => 'EXPENDITURE DETAIL (CATEGORY-WISE)'])

<table class="info">
<tr>
    <td width="12%" class="label-blue">Period</td>
    <td width="38%">{{ $period }}</td>
    <td width="12%" class="label-blue">Printed By</td>
    <td>{{ $printedBy }}</td>
</tr>
<tr>
    <td class="label-blue">Categories</td>
    <td>{{ count($data['groups']) }} &nbsp;&nbsp; ({{ $data['total_entries'] }} entries)</td>
    <td class="label-blue">Printed On</td>
    <td>{{ now()->format('d-m-Y h:i A') }}</td>
</tr>
</table>

@forelse($data['groups'] as $g)

<table>
<thead>
<tr>
    <th colspan="9" class="cat-header">{{ $g['category'] }} &mdash; {{ $g['entries'] }} entr{{ $g['entries'] == 1 ? 'y' : 'ies' }}</th>
</tr>
<tr>
    <th width="4%">Sl</th>
    <th width="9%">Date</th>
    <th width="14%">Voucher</th>
    <th width="9%">Bill No</th>
    <th>Paid To</th>
    <th width="11%">Mode</th>
    <th width="12%">Expenditure By</th>
    <th width="15%">Remarks</th>
    <th width="10%">Amount (Rs.)</th>
</tr>
</thead>
<tbody>
@foreach($g['rows'] as $i => $r)
<tr>
    <td class="text-center">{{ $i + 1 }}</td>
    <td>{{ $r['date_fmt'] }}</td>
    <td>{{ $r['voucher_number'] }}</td>
    <td>{{ $r['bill_number'] ?? '-' }}</td>
    <td>{{ $r['paid_to'] ?? '-' }}</td>
    <td>{{ $r['payment_mode'] }}@if(!empty($r['cheque_number'])) ({{ $r['cheque_number'] }})@endif</td>
    <td>{{ $r['spent_by'] ?? '-' }}</td>
    <td>{{ $r['remarks'] }}</td>
    <td class="text-end">{{ $money($r['amount']) }}</td>
</tr>
@endforeach
<tr class="total-row">
    <td colspan="8" class="text-end">Total &mdash; {{ $g['category'] }}</td>
    <td class="text-end">{{ $money($g['amount']) }}</td>
</tr>
</tbody>
</table>

@empty

<table><tr><td class="text-center">No expenditure entries in this period.</td></tr></table>

@endforelse

@if(count($data['groups']) > 1)
<table>
<tr class="total-row">
    <td class="text-end">Grand Total ({{ count($data['groups']) }} categories, {{ $data['total_entries'] }} entries)</td>
    <td class="text-end" width="14%">Rs. {{ $money($data['total_amount']) }}</td>
</tr>
</table>
@endif

</body>

</html>
