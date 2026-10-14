<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>Expenditure Summary</title>

@php
$money = fn ($v) => number_format((float) ($v ?? 0), 2);
@endphp

<style>

@page { margin-top: 1in; margin-right: 35px; margin-bottom: 35px; margin-left: 35px; }
@page :first { margin-top: 0.4in; }

body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #000; margin: 0; padding: 0; }

table { width: 100%; border-collapse: collapse; margin-top: 6px; }
table th, table td { border: 1px solid #000; padding: 4px 6px; vertical-align: middle; }
table thead th { background-color: #e6e6e6; text-align: center; font-weight: bold; }

.text-end { text-align: right; }
.text-center { text-align: center; }
.label-blue { font-weight: bold; }
.total-row td { font-weight: bold; background-color: #f1f1f1; }
.info td { border: 1px solid #ccc; }

</style>

</head>

<body>

@include('partials.pdf-header', ['reportTitle' => 'EXPENDITURE SUMMARY (CATEGORY-WISE)'])

<table class="info">
<tr>
    <td width="14%" class="label-blue">Period</td>
    <td width="36%">{{ $period }}</td>
    <td width="14%" class="label-blue">Printed By</td>
    <td>{{ $printedBy }}</td>
</tr>
<tr>
    <td class="label-blue">Entries</td>
    <td>{{ $data['total_entries'] }}</td>
    <td class="label-blue">Printed On</td>
    <td>{{ now()->format('d-m-Y h:i A') }}</td>
</tr>
</table>

<table>
<thead>
<tr>
    <th width="7%">Sl</th>
    <th>Category</th>
    <th width="13%">Entries</th>
    <th width="22%">Amount (Rs.)</th>
    <th width="12%">Share</th>
</tr>
</thead>
<tbody>
@forelse($data['rows'] as $i => $r)
<tr>
    <td class="text-center">{{ $i + 1 }}</td>
    <td>{{ $r['category'] }}</td>
    <td class="text-end">{{ $r['entries'] }}</td>
    <td class="text-end">{{ $money($r['amount']) }}</td>
    <td class="text-end">{{ number_format($r['share'], 1) }}%</td>
</tr>
@empty
<tr><td colspan="5" class="text-center">No expenditure entries in this period.</td></tr>
@endforelse

@if(count($data['rows']))
<tr class="total-row">
    <td colspan="2" class="text-end">Total</td>
    <td class="text-end">{{ $data['total_entries'] }}</td>
    <td class="text-end">{{ $money($data['total_amount']) }}</td>
    <td class="text-end">100%</td>
</tr>
@endif
</tbody>
</table>

</body>

</html>
