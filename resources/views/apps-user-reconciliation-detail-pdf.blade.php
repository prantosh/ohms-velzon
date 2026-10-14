<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>User Reconciliation Detail</title>

@php

$money = fn ($v) => number_format((float) ($v ?? 0), 2);

$r = $detail['reconciliation'] ?? null;

@endphp

<style>

@page {
    margin-top: 1in;
    margin-right: 30px;
    margin-bottom: 35px;
    margin-left: 30px;
}

@page :first {
    margin-top: 0.4in;
}

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 8.5px;
    color: #000;
    margin: 0;
    padding: 0;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 5px;
}

table th,
table td {
    border: 1px solid #000;
    padding: 3px 4px;
    vertical-align: top;
}

table thead th {
    background-color: #e6e6e6;
    font-weight: bold;
    text-align: center;
}

.text-end { text-align: right; }
.label-blue { font-weight: bold; }
.total-row td { font-weight: bold; background-color: #f1f1f1; }
.info td { border: 1px solid #ccc; }
h4 { margin: 12px 0 2px 0; font-size: 10px; }
.muted { color: #555; font-size: 7.5px; }
.small td, .small th { font-size: 7.5px; }

</style>

</head>

<body>

@include('partials.pdf-header', ['reportTitle' => 'USER RECONCILIATION DETAIL'])

<table class="info">
<tr>
    <td width="14%" class="label-blue">User</td>
    <td width="36%">{{ $label }}</td>
    <td width="14%" class="label-blue">Month</td>
    <td>{{ $detail['month_label'] }} ({{ $detail['range_fmt'] }})</td>
</tr>
<tr>
    <td class="label-blue">Printed By</td>
    <td>{{ $printedBy }}</td>
    <td class="label-blue">Printed On</td>
    <td>{{ now()->format('d-m-Y h:i A') }}</td>
</tr>
</table>

@if($r)
<h4>How doctor payable and paid relate <span class="muted">(cumulative since inception, up to the end of this month)</span></h4>
<table>
<tr><td>Doctor payable created on this user's invoices</td><td class="text-end" width="18%">{{ $money($r['payable']) }}</td></tr>
<tr><td>Doctor payment made by this user</td><td class="text-end">{{ $money($r['paid']) }}</td></tr>
<tr class="total-row"><td>Difference (payable - paid)</td><td class="text-end">{{ $money($r['difference']) }}</td></tr>
<tr><td>&nbsp;&nbsp;&nbsp;of which: still pending on this user's invoices</td><td class="text-end">{{ $money($r['pending']) }}</td></tr>
<tr><td>&nbsp;&nbsp;&nbsp;plus: this user's invoices paid out by other users</td><td class="text-end">{{ $money($r['own_paid_by_others']) }}</td></tr>
<tr><td>&nbsp;&nbsp;&nbsp;less: other users' invoices paid out by this user</td><td class="text-end">- {{ $money($r['paid_for_others']) }}</td></tr>
</table>
@endif

@php
$settledTable = function ($title, $rows) use ($money) {

    if (empty($rows)) return '';

    $total = array_sum(array_column($rows, 'amount'));

    $html = '<h4>' . e($title) . '</h4><table class="small"><thead><tr>'
        . '<th>Invoice</th><th>Item</th><th>Doctor</th><th>Billed by</th><th>Payable date</th><th>Paid by</th><th>Settled on</th><th>Settlement no</th><th>Amount</th>'
        . '</tr></thead><tbody>';

    foreach ($rows as $x) {
        $html .= '<tr><td>' . e($x['invoice_no']) . '</td><td>' . e($x['item']) . '</td><td>' . e($x['doctor']) . '</td><td>' . e($x['billed_by'])
            . '</td><td>' . e($x['payable_date']) . '</td><td>' . e($x['paid_by']) . '</td><td>' . e($x['settled_on']) . '</td><td>' . e($x['settlement_no'])
            . '</td><td class="text-end">' . $money($x['amount']) . '</td></tr>';
    }

    return $html . '<tr class="total-row"><td colspan="8" class="text-end">Total (' . count($rows) . ')</td><td class="text-end">' . $money($total) . '</td></tr></tbody></table>';
};

$items = $detail['items'] ?? null;
@endphp

@if($items)

{!! $settledTable("Other users' invoices paid out by this user", $items['paid_for_others']) !!}
{!! $settledTable("This user's invoices paid out by other users", $items['own_paid_by_others']) !!}

@if(!empty($items['pending']))
<h4>Still pending on this user's invoices</h4>
<table class="small">
<thead>
<tr><th>Invoice</th><th>Item</th><th>Doctor</th><th>Payable date</th><th>Payable</th><th>Already settled</th><th>Pending</th><th>Patient's due</th><th>Can it be settled?</th></tr>
</thead>
<tbody>
@foreach($items['pending'] as $x)
<tr>
    <td>{{ $x['invoice_no'] }}</td><td>{{ $x['item'] }}</td><td>{{ $x['doctor'] }}</td><td>{{ $x['payable_date'] }}</td>
    <td class="text-end">{{ $money($x['payable_amount']) }}</td><td class="text-end">{{ $money($x['settled']) }}</td><td class="text-end">{{ $money($x['amount']) }}</td>
    <td class="text-end">{{ $money($x['patient_due']) }}</td>
    <td>@if($x['ready']) Ready to settle ({{ $x['settle_under'] }}, {{ $x['settle_on'] }}) @else Waiting: patient owes the balance @endif</td>
</tr>
@endforeach
<tr class="total-row"><td colspan="6" class="text-end">Total ({{ count($items['pending']) }})</td><td class="text-end">{{ $money(array_sum(array_column($items['pending'], 'amount'))) }}</td><td colspan="2"></td></tr>
<tr><td colspan="6" class="text-end">of which ready to settle now</td><td class="text-end">{{ $money(array_sum(array_column(array_filter($items['pending'], fn ($y) => $y['ready']), 'amount'))) }}</td><td colspan="2"></td></tr>
<tr><td colspan="6" class="text-end">of which waiting for the patient's balance payment</td><td class="text-end">{{ $money(array_sum(array_column(array_filter($items['pending'], fn ($y) => !$y['ready']), 'amount'))) }}</td><td colspan="2"></td></tr>
</tbody>
</table>
@endif

@endif

@php
$modeTable = function ($title, $by) use ($money) {

    $modes = array_unique(array_merge(array_keys($by['period'] ?? []), array_keys($by['cum'] ?? [])));
    sort($modes);

    if (empty($modes)) return '<h4>' . e($title) . '</h4><p class="muted">None.</p>';

    $tp = 0; $tc = 0;
    $html = '<h4>' . e($title) . '</h4><table><thead><tr><th>Mode</th><th width="22%">This month</th><th width="22%">Cumulative</th></tr></thead><tbody>';

    foreach ($modes as $m) {
        $p = (float) ($by['period'][$m] ?? 0);
        $c = (float) ($by['cum'][$m] ?? 0);
        $tp += $p; $tc += $c;
        $html .= '<tr><td>' . e($m) . '</td><td class="text-end">' . $money($p) . '</td><td class="text-end">' . $money($c) . '</td></tr>';
    }

    return $html . '<tr class="total-row"><td>Total</td><td class="text-end">' . $money($tp) . '</td><td class="text-end">' . $money($tc) . '</td></tr></tbody></table>';
};
@endphp

{!! $modeTable('Collection by mode', $detail['modes']['collection']) !!}
{!! $modeTable('Refund by mode', $detail['modes']['refund']) !!}
{!! $modeTable('Doctor payment made, by mode', $detail['modes']['doctor_paid']) !!}

@if(!empty($detail['doctors']))
<h4>Doctor-wise <span class="muted">(payable and paid are for this month; pending is cumulative at month end)</span></h4>
<table>
<thead>
<tr><th>Doctor</th><th width="16%">Payable<br>this month</th><th width="16%">Paid<br>this month</th><th width="16%">Pending<br>since inception</th><th width="12%">Unpaid items</th></tr>
</thead>
<tbody>
@foreach($detail['doctors'] as $d)
<tr>
    <td>{{ $d['doctor_name'] }}</td>
    <td class="text-end">{{ $money($d['payable']) }}</td>
    <td class="text-end">{{ $money($d['paid']) }}</td>
    <td class="text-end">{{ $money($d['pending']) }}</td>
    <td class="text-end">{{ $d['unpaid_count'] }}</td>
</tr>
@endforeach
<tr class="total-row">
    <td>Total</td>
    <td class="text-end">{{ $money(array_sum(array_column($detail['doctors'], 'payable'))) }}</td>
    <td class="text-end">{{ $money(array_sum(array_column($detail['doctors'], 'paid'))) }}</td>
    <td class="text-end">{{ $money(array_sum(array_column($detail['doctors'], 'pending'))) }}</td>
    <td class="text-end">{{ array_sum(array_column($detail['doctors'], 'unpaid_count')) }}</td>
</tr>
</tbody>
</table>
@endif

</body>

</html>
