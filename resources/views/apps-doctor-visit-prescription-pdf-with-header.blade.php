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

{{-- Left info column: doctor details, Invoice No., Patient ID -- grouped
     the same way as the generic pre-printed-letterhead layout (tight
     lines within a group, a wider gap BETWEEN groups). --}}
.info-group {
    margin-bottom: 10px;
    font-size: 10px;
    line-height: 1.35;
}

.patient-line {
    font-size: 10px;
    margin-bottom: 10px;
}

.patient-name-value {
    font-size: 14px;
    font-weight: bold;
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
        <td width="50%" style="vertical-align:top;">

            <div class="info-group">
                <div class="label-blue">Doctor Name</div>
                <div>{{ $invoice->doctor_name ?? optional($doctor)->doctor_name }}</div>
                @if(optional($doctor)->qualification)
                <div>{{ $doctor->qualification }}</div>
                @endif
                @if(optional($doctor)->specialisation)
                <div>{{ $doctor->specialisation }}</div>
                @endif
                <div><span class="label-blue">Reg. No. :</span> {{ optional($doctor)->registration_no }}</div>
            </div>

            <div class="info-group">
                <div class="label-blue">Invoice No.</div>
                <div>{{ $invoice->invoice_no }}</div>
            </div>

            <div class="info-group">
                <div class="label-blue">Patient ID</div>
                <div>{{ $invoice->patient_id }}</div>
            </div>

        </td>
        <td width="50%" style="vertical-align:top;">

            <div class="patient-line">
                <span class="label-blue">Patient Name :</span>
                <span class="patient-name-value">{{ $invoice->patient_name }}</span>
            </div>

            <div class="patient-line">
                <span class="label-blue">Age / Sex :</span>
                {{ $invoice->patient_age ?? '' }} / {{ $invoice->patient_gender ?? '' }}
            </div>

            <div class="patient-line">
                <span class="label-blue">Date :</span>
                {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y') }}
            </div>

            <div class="patient-line">
                <span class="label-blue">BP :</span> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <span class="label-blue">Weight :</span>
            </div>

        </td>
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
