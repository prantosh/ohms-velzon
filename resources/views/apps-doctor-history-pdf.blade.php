<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>Doctor History</title>

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
        font-size: 10px;
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
        padding: 4px;
        vertical-align: top;
    }

    table thead th {
        font-weight: bold;
        text-align: center;
        background-color: #e6e6e6;
    }

    .text-end { text-align: right; }
    .text-center { text-align: center; }
    .label { font-weight: bold; }
    .section-title { font-weight: bold; font-size: 12px; margin-top: 16px; }
    .cancelled { text-decoration: line-through; }

</style>

</head>

<body>

@include('partials.pdf-header', ['reportTitle' => 'DOCTOR HISTORY'])

<table>
<tr>
    <td width="15%" class="label">Doctor</td>
    <td width="35%">{{ $doctor }}</td>
    <td width="15%" class="label">Period</td>
    <td>{{ $fromDate }} to {{ $toDate }}</td>
</tr>
<tr>
    <td class="label">Total Patients</td>
    <td>{{ $summary['total_patients'] }}</td>
    <td class="label">Male / Female</td>
    <td>{{ $summary['male_patients'] }} / {{ $summary['female_patients'] }}</td>
</tr>
</table>

<div class="section-title">Visit Details</div>

<table>
<thead>
<tr>
    <th width="9%">Date</th>
    <th width="8%">Time</th>
    <th width="12%">Invoice No</th>
    <th>Patient</th>
    <th width="6%">Age</th>
    <th width="7%">Gender</th>
    <th width="11%">Mobile</th>
    <th width="16%">Doctor</th>
    <th width="12%">Specialisation</th>
</tr>
</thead>
<tbody>
@forelse($rows as $row)
<tr>
    <td class="text-center">{{ $row->visit_date }}</td>
    <td class="text-center">{{ $row->visit_time }}</td>
    <td class="{{ ($row->cancelled ?? 'N') === 'Y' ? 'cancelled' : '' }}">{{ $row->invoice_no ?? '-' }}</td>
    <td>{{ $row->patient_name }}</td>
    <td class="text-center">{{ $row->patient_age ?? '-' }}</td>
    <td class="text-center">{{ $row->patient_gender ?? '-' }}</td>
    <td>{{ $row->patient_mobile_no ?? '-' }}</td>
    <td>{{ $row->doctor_name }}</td>
    <td>{{ $row->specialisation ?? '-' }}</td>
</tr>
@empty
<tr>
    <td colspan="9" class="text-center">No records found.</td>
</tr>
@endforelse
</tbody>
</table>

<div class="section-title">Daily Summary</div>

<table>
<thead>
<tr>
    <th>Date</th>
    <th width="15%">Patients</th>
    <th width="15%">Male</th>
    <th width="15%">Female</th>
    <th width="20%">Doctor Payment</th>
</tr>
</thead>
<tbody>
@foreach($daily_summary as $date => $d)
<tr>
    <td class="text-center">{{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}</td>
    <td class="text-center">{{ $d['patients'] }}</td>
    <td class="text-center">{{ $d['male'] }}</td>
    <td class="text-center">{{ $d['female'] }}</td>
    <td class="text-end">{{ number_format($d['doctor_payment'], 2) }}</td>
</tr>
@endforeach
</tbody>
</table>

<div class="section-title">Doctor Summary</div>

<table>
<thead>
<tr>
    <th>Doctor</th>
    <th width="15%">Patients</th>
    <th width="20%">Doctor Payment</th>
</tr>
</thead>
<tbody>
@foreach($doctor_summary as $name => $d)
<tr>
    <td>{{ $name }}</td>
    <td class="text-center">{{ $d['patients'] }}</td>
    <td class="text-end">{{ number_format($d['amount'], 2) }}</td>
</tr>
@endforeach
</tbody>
</table>

<p style="margin-top:14px; font-size:9px;">
    Printed by {{ $printedBy }} on {{ $printedOn }}
</p>

</body>

</html>
