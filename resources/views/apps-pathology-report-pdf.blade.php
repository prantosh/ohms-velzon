<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>Pathology Report</title>

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
    border: 1px solid #000;
    vertical-align: top;
}

.study-title {
    text-align: center;
    margin-top: 12px;
}

.report-body {
    margin-top: 14px;
}

.report-body table {
    margin-top: 6px;
}

.report-body table th,
.report-body table td {
    border: 1px solid #000;
    padding: 6px;
    vertical-align: top;
}

.signature-section {
    margin-top: 50px;
}

.signature {
    width: 45%;
    text-align: center;
    display: inline-block;
    float: right;
}

/* Matches CKEditor's own editing-view sizing exactly (ckeditor5-content.css
   --ck-content-font-size-*), so the printed report matches what was typed. */
.text-tiny { font-size: 0.7em; }
.text-small { font-size: 0.85em; }
.text-big { font-size: 1.4em; }
.text-huge { font-size: 1.8em; }

</style>

</head>

<body>

@include('partials.pdf-header', ['reportTitle' => 'PATHOLOGY REPORT', 'headerColor' => '#003399'])

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
            <span class="label-blue">Confirmed On :</span>
            {{ optional($finding->confirmed_at)->format('d-m-Y H:i') }}
        </td>
    </tr>
</table>

<h4 class="study-title">{{ $itemDescriptions->implode(', ') }}</h4>

<div class="report-body">
    {!! $finding->content !!}
</div>

<div class="signature-section">
    <div class="signature">
        ______________________
        <br>
        Doctor's Signature
    </div>
</div>

</body>

</html>
