<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>Prescription</title>

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

.no-border td {
    border: none;
    padding: 4px 0;
}

.label-blue {
    color: #003399;
    font-weight: bold;
}

.patient-detail-table th,
.patient-detail-table td {
    font-size: 9px;
    padding: 3px;
}

.vitals-box th,
.vitals-box td {
    font-size: 9px;
    padding: 3px 6px;
}

.vitals-box td {
    text-align: center;
}

.rx-box {
    min-height: 675px;
    margin-top: 10px;
    padding: 8px;
}

.signature-section {
    margin-top: 40px;
}

.signature {
    width: 45%;
    text-align: center;
    display: inline-block;
    float: right;
}

</style>

</head>

<body>

@include('partials.pdf-header', ['reportTitle' => 'PRESCRIPTION', 'headerColor' => '#003399'])

<table class="no-border">
    <tr>
        <td width="50%">
            <span class="label-blue">Doctor :</span>
            {{ $invoice->doctor_name ?? optional($doctor)->doctor_name }}
        </td>
        <td width="50%">
            <span class="label-blue">Qualification :</span>
            {{ optional($doctor)->qualification }}
        </td>
    </tr>
    <tr>
        <td>
            <span class="label-blue">Specialisation :</span>
            {{ optional($doctor)->specialisation }}
        </td>
        <td>
            <span class="label-blue">Registration No :</span>
            {{ optional($doctor)->registration_no }}
        </td>
    </tr>
</table>

<table class="patient-detail-table">
    <tr>
        <th width="12%">Patient Name</th>
        <td width="18%">{{ $invoice->patient_name }}</td>

        <th width="10%">Age / Sex</th>
        <td width="13%">{{ $invoice->patient_age ?? '' }} / {{ $invoice->patient_gender ?? '' }}</td>

        <th width="12%">Invoice No</th>
        <td width="18%">{{ $invoice->invoice_no }}</td>

        <th width="7%">Date</th>
        <td width="10%">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y') }}</td>
    </tr>
</table>

<table class="vitals-box">
    <tr>
        <th width="12%">Patient ID</th>
        <td width="26%">{{ $invoice->patient_id }}</td>

        <th width="12%">BP</th>
        <td width="25%">&nbsp;</td>

        <th width="12%">Weight</th>
        <td width="25%">&nbsp;</td>
    </tr>
</table>

{{-- Vertical rule from the bottom of the Patient ID table to the end of
     the page, 50mm from the left edge of the page. Position is
     page-relative (fixed), not flow-relative, so the top offset is
     calibrated to this template's fixed header/table layout above it. --}}
<div style="position:fixed; top:67mm; left:50mm; bottom:0; border-left:1px solid #000;"></div>

<div class="rx-box"></div>

<div class="signature-section">
    <div class="signature">
        ______________________
        <br>
        Doctor's Signature
    </div>
</div>

</body>

</html>
