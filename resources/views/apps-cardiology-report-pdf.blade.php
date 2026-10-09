<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>Cardiology Report</title>

<style>

@page {
    /* 45mm is the clinic's pre-printed letterhead art zone (unchanged);
       +15mm pushes the patient-detail strip 1.5cm further down than USG's
       (a prior, Cardiology-only request); the remaining 20mm reserves room
       for that strip itself, which is position:fixed so it repeats on
       every page for a multi-page report -- see .patient-detail-fixed. */
    margin-top: 80mm;
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

.patient-detail-table .no-border-row td {
    border: none !important;
    padding: 4px 0 !important;
}

.label-blue {
    color: #003399;
    font-weight: bold;
}

/* Repeats on every page (DomPDF renders position:fixed elements once per
   page) -- positioned above the normal content box, in the reserved strip
   @page's margin-top carves out just below the letterhead art zone (see
   @page comment above for the 1.5cm Cardiology-specific extra push-down),
   so a multi-page report still identifies the patient on every page. */
.patient-detail-fixed {
    position: fixed;
    top: -20mm;
    left: 0;
    right: 0;
}

.patient-detail-table th,
.patient-detail-table td {
    font-size: 11px;
    padding: 3px;
}

.study-title {
    font-size: 18px;
    text-align: center;
    margin-top: 12px;
}

.report-section {
    margin-top: 6px;
    /* Keeps a numbered item's heading and its own content together --
       without this, a page break can land right between them, leaving
       e.g. "5. Aortic Valve" alone at the bottom of one page and its
       actual finding orphaned at the top of the next. */
    page-break-inside: avoid;
}

.report-section-heading {
    color: #003399;
    font-weight: bold;
    font-size: 12px;
    margin-bottom: 2px;
}

.report-section-body {
    min-height: 24px;
    white-space: pre-wrap;
}

/* Plain (non-list) content -- e.g. each numbered field's own sentences --
   is typed as a run of separate <p> tags, each carrying a full default
   top/bottom margin. That default margin, not .report-section's own
   spacing, was most of the visible gap both between lines within one
   numbered item and between one numbered item and the next. */
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

/* But text-align has NO effect on a display:inline element -- CKEditor's
   Alignment toolbar always sets it as an inline style directly on the <p>
   (e.g. style="text-align:center"), so a centered/right/justified list
   item silently rendered flush-left in the PDF despite looking correct in
   the editor, which never applies this print-only display:inline rule.
   Reinstating display:block ONLY for a paragraph that actually carries an
   explicit text-align restores its alignment, while plain (unaligned)
   paragraphs -- the common "Header" + "content" pair this rule exists for
   -- keep merging onto one line exactly as before. */
.report-section-body li p[style*="text-align"] {
    display: block;
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
    // Each Cardiology field is already discretely labeled (unlike USG's
    // single freeform blob), so no ALL-CAPS-heading-detection heuristic is
    // needed here -- content is trusted/sanitized HTML from the CKEditor
    // entry as-is.
    $cardioFields = \App\Support\CardiologyReportFields::FIELDS;

@endphp

<body>

<div class="patient-detail-fixed">

<table class="patient-detail-table">
    <tr>
        <th width="12%">Patient Name</th>
        <td width="18%">{{ $invoice->patient_name }}</td>

        <th width="10%">Age / Sex</th>
        <td width="13%">{{ $invoice->patient_age ?? '' }} / {{ $invoice->patient_gender ?? '' }}</td>

        <th width="12%">Invoice No</th>
        <td width="18%">{{ $invoice->invoice_no }}</td>

        <th width="7%">Invoice Date</th>
        <td width="10%">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y') }}</td>
    </tr>
    <tr class="no-border-row">
        <td colspan="3">
            <span class="label-blue">Referred By :</span>
            {{ $invoice->referred_doctor }}
        </td>
        <td colspan="2">
            <span class="label-blue">Test Done on :</span>
            {{ $invoice->test_date ? \Carbon\Carbon::parse($invoice->test_date)->format('d-m-Y') : '-' }}
        </td>
        <td colspan="3">
            <span class="label-blue">Patient ID :</span>
            {{ $invoice->patient_id ?? '-' }}
        </td>
    </tr>
</table>

</div>

<h4 class="study-title">
    {{ !empty($finding->heading) ? strip_tags($finding->heading) : '2D ECHOCARDIOGRAPHY REPORT' }}
</h4>

@foreach($cardioFields as $key => $label)
    @if($key !== 'heading' && !empty($finding->{$key}))
    <div class="report-section">
        <div class="report-section-heading">{{ $label }}</div>
        <div class="report-section-body">{!! $finding->{$key} !!}</div>
    </div>
    @endif
@endforeach

</body>

</html>
