<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>User Reconciliation</title>

@php

$money = fn ($v) => number_format((float) ($v ?? 0), 2);

// doctor balance = payable - paid: positive is pending, negative is excess paid
$balance = function ($v) use ($money) {
    $v = round((float) $v, 2);

    if ($v > 0.004) return $money($v) . ' pending';
    if ($v < -0.004) return $money(abs($v)) . ' excess';

    return $money(0);
};

$isEmployees = $mode === 'employees';

@endphp

<style>

@page {
    margin-top: 1in;
    margin-right: 25px;
    margin-bottom: 30px;
    margin-left: 25px;
}

@page :first {
    margin-top: 0.4in;
}

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 7.5px;
    color: #000;
    margin: 0;
    padding: 0;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 6px;
}

table th,
table td {
    border: 1px solid #000;
    padding: 3px 4px;
    vertical-align: middle;
}

table thead th {
    text-align: center;
    font-weight: bold;
    background-color: #e6e6e6;
}

.text-end { text-align: right; }
.label-blue { font-weight: bold; }
.total-row td { font-weight: bold; background-color: #f1f1f1; }
.muted { color: #555; font-size: 6.5px; }
.info td { border: 1px solid #ccc; font-size: 8.5px; }

</style>

</head>

<body>

@include('partials.pdf-header', ['reportTitle' => 'USER RECONCILIATION'])

<table class="info">
<tr>
    <td width="12%" class="label-blue">{{ $isEmployees ? 'Employees' : 'User' }}</td>
    <td width="38%">{{ $label }}</td>
    <td width="12%" class="label-blue">Printed By</td>
    <td>{{ $printedBy }}</td>
</tr>
<tr>
    <td class="label-blue">Period</td>
    <td>{{ $isEmployees ? $data['range_fmt'] : 'Month by month, cumulative since inception' }}</td>
    <td class="label-blue">Printed On</td>
    <td>{{ now()->format('d-m-Y h:i A') }}</td>
</tr>
</table>

<table>

<thead>
<tr>
    <th rowspan="2">{{ $isEmployees ? 'Employee' : 'Month' }}</th>
    <th colspan="6">This Month</th>
    <th colspan="3">Doctor Balance (Payable - Paid)</th>
    <th colspan="6">Cumulative Since Inception (up to month end)</th>
</tr>
<tr>
    <th>Collection<br>Cash</th>
    <th>Collection<br>Non-Cash</th>
    <th>Refund</th>
    <th>Doctor<br>Payable</th>
    <th>Paid to<br>Doctor</th>
    <th>Net Cash</th>
    <th>Carried over from<br>previous month</th>
    <th>This month<br>movement</th>
    <th>Balance at<br>month end</th>
    <th>Collection</th>
    <th>Refund</th>
    <th>Doctor<br>Payable</th>
    <th>Paid to<br>Doctor</th>
    <th>Net Cash</th>
    <th>Doctor Payment<br>Pending</th>
</tr>
</thead>

<tbody>

@php
$printRow = function ($r, $first, $class = '') use ($money, $balance) {

    $closing = $r['cum_doctor_payable'] - $r['cum_doctor_paid'];
    $movement = $r['period_doctor_payable'] - $r['period_doctor_paid'];
    $carried = $closing - $movement;

    return '<tr class="' . $class . '"><td>' . $first . '</td>'
        . '<td class="text-end">' . $money($r['period_collection_cash']) . '</td>'
        . '<td class="text-end">' . $money($r['period_collection_non_cash']) . '</td>'
        . '<td class="text-end">' . $money($r['period_refund']) . '</td>'
        . '<td class="text-end">' . $money($r['period_doctor_payable']) . '</td>'
        . '<td class="text-end">' . $money($r['period_doctor_paid']) . '</td>'
        . '<td class="text-end">' . $money($r['period_net_cash']) . '</td>'
        . '<td class="text-end">' . $balance($carried) . '</td>'
        . '<td class="text-end">' . $balance($movement) . '</td>'
        . '<td class="text-end">' . $balance($closing) . '</td>'
        . '<td class="text-end">' . $money($r['cum_collection_cash'] + $r['cum_collection_non_cash']) . '</td>'
        . '<td class="text-end">' . $money($r['cum_refund']) . '</td>'
        . '<td class="text-end">' . $money($r['cum_doctor_payable']) . '</td>'
        . '<td class="text-end">' . $money($r['cum_doctor_paid']) . '</td>'
        . '<td class="text-end">' . $money($r['cum_net_cash']) . '</td>'
        . '<td class="text-end"><b>' . $money($r['doctor_pending']) . '</b>'
        . (($r['doctor_pending_waiting'] ?? 0) > 0.004 ? '<br><span class="muted">incl. ' . $money($r['doctor_pending_waiting']) . ' awaiting final payment of the invoice</span>' : '')
        . '</td></tr>';
};
@endphp

@forelse($data['rows'] as $r)
    @if($isEmployees)
        {!! $printRow($r, e($r['user_name']) . ' <span class="muted">(' . e($r['role']) . ')</span>') !!}
    @else
        {!! $printRow($r, '<b>' . e($r['month_label']) . '</b>' . (!empty($r['is_live']) ? ' <span class="muted">(month to date)</span>' : '') . '<br><span class="muted">' . e($r['range_fmt']) . '</span>') !!}
    @endif
@empty
<tr><td colspan="16" style="text-align:center;">No transactions found for this selection.</td></tr>
@endforelse

@if($isEmployees && count($data['rows']))
    {!! $printRow($data['totals'], 'Total', 'total-row') !!}
@endif

</tbody>

</table>

<p class="muted">
    Doctor Balance = doctor payable - paid to doctors; carried over from previous month is the balance on the 1st, the month's
    movement added to it gives the balance at month end, which is carried to the next month. Net Cash = cash collected - cash refunded -
    cash paid to doctors. Doctor Payment Pending = doctor payable on the user's own invoices - everything settled against it; part of it may be
    awaiting the final payment of an invoice the patient has not paid in full (it cannot be settled until then).
</p>

</body>

</html>
