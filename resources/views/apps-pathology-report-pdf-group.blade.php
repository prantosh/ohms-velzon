<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>Pathology Report</title>

<style>

/*
    No system-rendered header/footer -- this prints straight onto the
    clinic's pre-printed pathology letterhead paper, which already
    carries its own header/footer art. Margins reuse the exact blank
    content zone already calibrated for this same physical stationery by
    the old grid-based report (apps-diagnostic-test-report-pdf.blade.php).
    Same layout as apps-pathology-report-pdf.blade.php, except this one
    combines several reports into a single printed document -- either
    every report within ONE test group (Print Group Report, a group
    completed in stages needs several independent reports combined), or
    every confirmed report across EVERY group on the invoice (Print All
    Reports) -- both share this same header/footer-less layout, printing a
    group heading per section whenever the group changes (see $sections'
    doc comment below); a single-group call just prints that one heading
    once, unchanged from before.
*/
@page {
    margin-top: 50mm;
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

.group-heading {
    color: #003399;
    font-weight: bold;
    font-size: 14px;
    border-bottom: 2px solid #003399;
    margin-top: 18px;
    padding-bottom: 2px;
}

.group-heading:first-of-type {
    margin-top: 10px;
}

.study-title {
    text-align: center;
    margin-top: 12px;
}

.section-divider {
    border: none;
    border-top: 1px dashed #999;
    margin: 16px 0;
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
    /* !important: CKEditor's TableCellProperties can bake a per-cell inline
       padding onto individual td/th (HtmlSanitizerService deliberately
       allows it, so doctors can customize cells while composing) -- an
       inline style always wins over this rule otherwise, so the printed
       report needs to force its own compact spacing regardless. */
    padding: 3px !important;
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

{{--
    $sections: every confirmed report to include, each carrying its own
    'group_name' -- Print Group Report passes only one group's reports (so
    exactly one heading prints, same as before), Print All Reports passes
    every group's, printing a new heading each time the group changes
    (grouped together, not interleaved, same order the controller built
    them in) so the whole invoice still reads as one document sectioned by
    test type, not a flat unlabelled list.
--}}
@php $previousGroup = null; @endphp

@foreach($sections as $i => $section)

@if($section['group_name'] !== $previousGroup)
<h4 class="group-heading">{{ $section['group_name'] }}</h4>
@php $previousGroup = $section['group_name']; @endphp
@elseif($i > 0)
<hr class="section-divider">
@endif

<table class="no-border">
    <tr>
        <td width="70%">
            <span class="label-blue">Confirmed On :</span>
            {{ optional($section['confirmed_at'])->format('d-m-Y H:i') }}
        </td>
    </tr>
</table>

<h4 class="study-title">{{ $section['title'] }}</h4>

<div class="report-body">
    {!! $section['content'] !!}
</div>

@endforeach

<div class="signature-section">
    <div class="signature">
        ______________________
        <br>
        Doctor's Signature
    </div>
</div>

</body>

</html>
