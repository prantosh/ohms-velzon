<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>USG Report</title>

<style>

@page {
    margin-top: 45mm;
    margin-right: 40px;
    margin-bottom: 60mm;
    margin-left: 40px;
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

.study-title {
    text-align: center;
    margin-top: 12px;
}

.report-section {
    margin-top: 14px;
}

.report-section-heading {
    color: #003399;
    font-weight: bold;
    font-size: 12px;
    border-bottom: 1px solid #003399;
    padding-bottom: 2px;
    margin-bottom: 4px;
}

.report-section-body {
    min-height: 60px;
    white-space: pre-wrap;
}

</style>

</head>

@php
    // Radiologists type all-caps words in these fields for emphasis (e.g.
    // abbreviations, key findings) -- render that emphasis as bold+underline
    // instead of flattening it to the same weight as surrounding text.
    if (!function_exists('usgBoldAllCaps')) {
        function usgBoldAllCaps($text) {
            if ($text === null || $text === '') {
                return '';
            }
            return preg_replace('/\b[A-Z]{2,}\b/', '<strong><u>$0</u></strong>', e($text));
        }
    }
@endphp

<body>

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

<table class="no-border">
    <tr>
        <td width="70%">
            <span class="label-blue">Reported By :</span>
            {{ optional($doctor)->doctor_name }}
            @if(optional($doctor)->qualification)
                ({!! usgBoldAllCaps($doctor->qualification) !!})
            @endif
        </td>
        <td width="30%">
            <span class="label-blue">Confirmed On :</span>
            {{ optional($finding->confirmed_at)->format('d-m-Y H:i') }}
        </td>
    </tr>
</table>

<h4 class="study-title">{!! usgBoldAllCaps('USG ' . $finding->item_description) !!}</h4>

@if(!empty($finding->clinical_history))
<div class="report-section">
    <div class="report-section-heading">Clinical History</div>
    <div class="report-section-body">{!! usgBoldAllCaps($finding->clinical_history) !!}</div>
</div>
@endif

<div class="report-section">
    <div class="report-section-heading">Findings</div>
    <div class="report-section-body">{!! usgBoldAllCaps($finding->findings) !!}</div>
</div>

<div class="report-section">
    <div class="report-section-heading">Impression</div>
    <div class="report-section-body">{!! usgBoldAllCaps($finding->impression) !!}</div>
</div>

</body>

</html>
