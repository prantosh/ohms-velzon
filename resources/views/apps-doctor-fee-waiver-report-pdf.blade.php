<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>Doctor Fee Waiver Report</title>

@php
function fmtMoneyWaiver($v)
{
    return number_format((float) ($v ?? 0), 2);
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
    font-size: 8px;
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
    font-size: 9px;
}

.text-end { text-align: right; }
.text-center { text-align: center; }

.no-border,
.no-border td { border: none; }

.label-blue { color: #000; font-weight: bold; }

table thead th {
    color: #000;
    font-weight: bold;
    text-align: center;
}

.signature-section { margin-top: 60px; }

</style>

</head>

<body>

@include('partials.pdf-header', ['reportTitle' => 'DOCTOR FEE WAIVER REPORT'])

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">Period</td>
    <td>{{ $fromDate->format('d-m-Y') }} to {{ $toDate->format('d-m-Y') }}</td>

    <td width="25%" class="label-blue">Doctor</td>
    <td>{{ $doctorName }}</td>
</tr>

<tr>
    <td class="label-blue">Visits Waived</td>
    <td>{{ $grandTotal['count'] }}</td>

    <td class="label-blue">Total Fee Waived</td>
    <td><b>Rs. {{ fmtMoneyWaiver($grandTotal['amount']) }}</b></td>
</tr>

<tr>
    <td class="label-blue">Printed By</td>
    <td colspan="3">{{ $printedBy }}</td>
</tr>

</table>

<table>

<thead>
<tr>
    <th colspan="10" style="text-align:left; background-color:#e6e6e6;">
        Waived Doctor Visits between {{ $fromDate->format('d-m-Y') }} and {{ $toDate->format('d-m-Y') }}
    </th>
</tr>
<tr>
    <th width="4%">SL</th>
    <th width="11%">Invoice No</th>
    <th width="7%">Visit Date</th>
    <th width="12%">Patient</th>
    <th width="12%">Doctor</th>
    <th width="7%">Fee</th>
    <th width="8%">Fee Waived</th>
    <th width="10%">Waived By</th>
    <th width="10%">Waived At</th>
    <th>Reason</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td class="text-center">{{ $loop->iteration }}</td>
    <td>{{ $row->invoice_no }}</td>
    <td>{{ $row->visit_date_fmt }}</td>
    <td>{{ $row->patient_name }}</td>
    <td>{{ $row->doctor_name }}</td>
    <td class="text-end">{{ fmtMoneyWaiver($row->total_amount) }}</td>
    <td class="text-end">{{ fmtMoneyWaiver($row->waived_amount) }}</td>
    <td>{{ $row->waived_by_name }}</td>
    <td>{{ $row->waived_at_fmt }}</td>
    <td>{{ $row->waiver_reason }}</td>
</tr>
@empty
<tr>
    <td colspan="10" class="text-center">No waived doctor visits found for the selected period.</td>
</tr>
@endforelse

<tr style="background-color:#f0f0f0; font-weight:bold;">
    <td colspan="6" class="text-end">Total</td>
    <td class="text-end">{{ fmtMoneyWaiver($grandTotal['amount']) }}</td>
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
