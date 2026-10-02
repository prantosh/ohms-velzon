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
    /* !important: CKEditor's TableCellProperties can bake a per-cell inline
       padding onto individual td/th (HtmlSanitizerService deliberately
       allows it, so doctors can customize cells while composing) -- an
       inline style always wins over this rule otherwise, so the printed
       report needs to force its own compact spacing regardless. */
    padding: 3px !important;
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
    margin-top: 6px;
}

.report-section-heading {
    color: #003399;
    font-weight: bold;
    font-size: 12px;
    border-bottom: 1px solid #003399;
    padding-bottom: 1px;
    margin-bottom: 2px;
}

.report-section-body {
    min-height: 60px;
    white-space: pre-wrap;
}

/* Plain (non-list) content -- e.g. each sub-item sentence typed on its own
   line -- is a run of separate <p> tags, each carrying a full default
   top/bottom margin. That default margin, not .report-section's own
   spacing, was most of the visible gap between sub-items within
   Clinical History/Findings/Impression. */
.report-section-body p {
    margin: 0;
}

.report-section-body ol,
.report-section-body ul {
    margin: 0;
}

.report-section-body li {
    margin: 0;
    line-height: 1.25;
}

/* A pasted (or CKEditor Document-List) numbered/bulleted item can wrap its
   text in its own <p>, which otherwise carries a full default top/bottom
   margin on top of the list's own spacing -- stacking into a much bigger
   gap between sub-items, and a large blank gap after the last one.
   display:inline on top of margin:0 additionally keeps a bold "Header"
   paragraph and its following "content" paragraph on the SAME visual
   line as each other (e.g. "1. Header: content...") instead of each
   forcing its own line, since a <p> is a block box even with no margin --
   this is what actually saves the vertical space for a header+content
   item, not just the gap between items. */
.report-section-body li p {
    margin: 0;
    display: inline;
}

/* Tables typed into the report content (CKEditor's Table feature) --
   halved again from the general table rule above: this content is dense
   value/reference-range data, not prose, so it can run much tighter. */
.report-section-body table th,
.report-section-body table td {
    padding: 1px 3px !important;
    line-height: 1.2;
}

.report-section-body table p {
    margin: 0;
}

/* Matches CKEditor's own editing-view sizing exactly (ckeditor5-content.css
   --ck-content-font-size-*), so the printed report matches what was typed. */
.text-tiny { font-size: 0.7em; }
.text-small { font-size: 0.85em; }
.text-big { font-size: 1.4em; }
.text-huge { font-size: 1.8em; }

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

    // Rich HTML (from the CKEditor-based report entry, sanitized at save
    // time) is trusted as-is; records saved before that feature existed are
    // plain text and keep the old escape+bold-all-caps treatment. Presence
    // of a tag is enough to tell them apart -- plain clinical text never
    // legitimately contains a literal "<...>" sequence.
    if (!function_exists('usgRenderClinicalField')) {
        function usgRenderClinicalField($text) {
            if ($text === null || $text === '') {
                return '';
            }
            return ($text !== strip_tags($text)) ? $text : usgBoldAllCaps($text);
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
            <span class="label-blue">Test Date :</span>
            {{ $invoice->test_date ? \Carbon\Carbon::parse($invoice->test_date)->format('d-m-Y') : '-' }}
        </td>
    </tr>
</table>

<h4 class="study-title">{!! usgBoldAllCaps('USG ' . $finding->item_description) !!}</h4>

@if(!empty($finding->clinical_history))
<div class="report-section">
    <div class="report-section-heading">Clinical History</div>
    <div class="report-section-body">{!! usgRenderClinicalField($finding->clinical_history) !!}</div>
</div>
@endif

@if(!empty($finding->findings))
<div class="report-section">
    <div class="report-section-heading">Findings</div>
    <div class="report-section-body">{!! usgRenderClinicalField($finding->findings) !!}</div>
</div>
@endif

@if(!empty($finding->impression))
<div class="report-section">
    <div class="report-section-heading">Impression</div>
    <div class="report-section-body">{!! usgRenderClinicalField($finding->impression) !!}</div>
</div>
@endif

</body>

</html>
