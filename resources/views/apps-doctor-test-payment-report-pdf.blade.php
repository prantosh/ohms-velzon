<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>Doctor Test Payment Report</title>

@php

function fmtMoney2($v)
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
    font-size: 12px;
    color: #000;
    margin: 0;
    padding: 0;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

table th,
table td {
    border: 1px solid #000;
    padding: 6px;
    vertical-align: top;
}

.text-end {
    text-align: right;
}

.text-center {
    text-align: center;
}

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

.settled {
    color: #000;
    font-weight: bold;
}

.not-settled {
    color: #000;
    font-style: italic;
}

.signature-section {
    margin-top: 60px;
}
</style>

</head>

<body>

@include('partials.pdf-header', ['reportTitle' => 'DOCTOR TEST PAYMENT REPORT'])

<!-- =======================================================
     DOCTOR / DATE / SUMMARY
======================================================= -->

<table>

<tr>
    <td width="25%" class="label-blue">Doctor</td>
    <td>{{ $doctor->doctor_name }}</td>

    <td width="25%" class="label-blue">Date</td>
    <td>{{ $date->format('d-m-Y (l)') }}</td>
</tr>

<tr>
    <td class="label-blue">Total Tests</td>
    <td>{{ $summary['total_invoices'] }}</td>

    <td class="label-blue">Settled / Not Settled</td>
    <td>{{ $summary['settled_count'] }} / {{ $summary['not_settled_count'] }}</td>
</tr>

<tr>
    <td class="label-blue">Total Payable</td>
    <td>Rs. {{ fmtMoney2($summary['total_payable']) }}</td>

    <td class="label-blue">Total Settled / Balance</td>
    <td>Rs. {{ fmtMoney2($summary['total_settled_amount']) }} / Rs. {{ fmtMoney2($summary['total_balance']) }}</td>
</tr>

</table>

<!-- =======================================================
     TEST PAYMENT LIST
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="8" style="text-align:left; background-color:#e6e6e6;">
        {{ $doctor->doctor_name }} &mdash; Doctor Test Payment Report for {{ $date->format('d-m-Y (l)') }}
    </th>
</tr>
<tr>
    <th>Invoice No</th>
    <th>Patient Name</th>
    <th>Test / Item</th>
    <th width="9%">Time</th>
    <th width="11%">Payable</th>
    <th width="11%">Settled</th>
    <th width="11%">Balance</th>
    <th width="14%">Settlement No / Status</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row['invoice_no'] }}</td>
    <td>{{ $row['patient_name'] }}</td>
    <td>{{ $row['item_description'] }}</td>
    <td>{{ $row['invoice_time'] }}</td>
    <td class="text-end">{{ $row['is_settled'] ? fmtMoney2($row['payable_amount']) : '-' }}</td>
    <td class="text-end">{{ $row['is_settled'] ? fmtMoney2($row['settled_amount']) : '-' }}</td>
    <td class="text-end">{{ $row['is_settled'] ? fmtMoney2($row['balance']) : '-' }}</td>
    <td>
        @if($row['is_settled'])
            <span class="settled">{{ $row['settlement_numbers'] }}</span>
        @else
            <span class="not-settled">Not Settled</span>
        @endif
    </td>
</tr>
@empty
<tr>
    <td colspan="8" class="text-center">No diagnostic test payments found for this date.</td>
</tr>
@endforelse

</tbody>

</table>

<div class="signature-section">

<table border="0" style="border:none; margin:0;">

<tr>

<td class="no-border text-center" style="width:45%;">

_____________________

<br>

Prepared By

</td>

<td class="no-border" style="width:10%;"></td>

<td class="no-border text-center" style="width:45%;">

_____________________

<br>

Doctor's Acknowledgement

</td>

</tr>

</table>

</div>

</body>

</html>
