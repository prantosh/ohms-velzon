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
    /* 50mm is the clinic's pre-printed letterhead art zone (unchanged);
       the extra 20mm reserves room for the patient-detail strip below it,
       which is position:fixed so it repeats on every page -- see
       .patient-detail-fixed. */
    margin-top: 70mm;
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

/* Repeats on every page (DomPDF renders position:fixed elements once per
   page) -- positioned above the normal content box, in the 20mm strip
   @page reserves for it just below the letterhead art zone, so every
   page identifies the patient even if pages get separated. */
.patient-detail-fixed {
    position: fixed;
    top: -20mm;
    left: 0;
    right: 0;
}

/*
    Doctor's signature stamp, repeated on every page at a fixed spot on
    the physical letterhead paper: 11.5cm from the true left edge, 5.65cm
    from the true bottom edge. Same negative-offset trick as
    .patient-detail-fixed above, but relative to margin-bottom/
    margin-left instead of margin-top.
*/
.doctor-signature-wrap {
    position: fixed;
    bottom: -0.35cm; /* -(margin-bottom 6cm - 5.65cm) */
    left: 10.44cm;  /* 11.5cm - margin-left (40px = 1.06cm) */
    width: 7.5cm;
}

/* It's a raster image, not text, so font-weight can't bold it -- this
   stacks several copies of the same image with a tiny offset in each
   direction to fake a thicker/bolder stroke (same trick browsers use
   to fake-bold a font with no bold variant available). */
.doctor-signature-image {
    position: absolute;
    width: 100%;
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

/* Requirement: each test group starts on its own page -- except the very
   first group, which already starts on the document's first page.
   DomPDF's CSS selector engine doesn't support :not(), so this is applied
   via an explicit "group-break" class from the loop below rather than a
   :not(:first-of-type) selector (which it silently ignores). */
.group-heading.group-break {
    page-break-before: always;
}

/* Requirement: a single item's report (date + title + body) must never be
   split across a page boundary -- if it doesn't fit in what's left of the
   current page, DomPDF pushes the whole thing to the next page instead.
   Applied only to a CONTINUATION item (one that isn't first in its group)
   -- the first item of a report/group is already as early as it can start,
   so forcing "avoid" on it too would just push it past otherwise-usable
   space (or even to a blank page) when it doesn't fit a full page either. */
.report-item.avoid-break {
    page-break-inside: avoid;
}

.study-title {
    font-size: 18px;
    text-align: center;
    margin-top: 6px;
}

.section-divider {
    border: none;
    border-top: 1px dashed #999;
    margin: 8px 0;
}

.report-body {
    margin-top: 8px;
}

/* The CKEditor body is a run of <p>s, each with the browser-default 1em
   top/bottom margin -- most of a short report's height was that spacing,
   which is what pushed every report onto a page of its own. */
.report-body p {
    margin: 3px 0;
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
    padding: 1px 3px !important;
    vertical-align: top;
    line-height: 1.2;
}

/* Cells hold CKEditor <p> blocks, and DomPDF gives <p> a 1em default
   margin -- that, not the cell padding, was most of each row's height. */
.report-body table p {
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

<body>

<div class="doctor-signature-wrap">
    @foreach ([[0, 0], [0.3, 0], [-0.3, 0], [0, 0.3], [0, -0.3]] as [$dx, $dy])
        <img class="doctor-signature-image"
             style="left: {{ $dx }}mm; top: {{ $dy }}mm;"
             src="{{ public_path('images/doctor_signature_pathology.png') }}">
    @endforeach
</div>

<div class="patient-detail-fixed">
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
</div>

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

@php $isFirstInGroup = $section['group_name'] !== $previousGroup; @endphp

@if($isFirstInGroup)
<h4 class="group-heading @if($i > 0) group-break @endif">{{ $section['group_name'] }}</h4>
@php $previousGroup = $section['group_name']; @endphp
@elseif($i > 0)
<hr class="section-divider">
@endif

<div class="report-item @if(!$isFirstInGroup) avoid-break @endif">

    <table class="no-border">
        <tr>
            <td width="70%">
                <span class="label-blue">Test Date :</span>
                {{ $invoice->test_date ? \Carbon\Carbon::parse($invoice->test_date)->format('d-m-Y') : '-' }}
            </td>
        </tr>
    </table>

    <h4 class="study-title">{{ $section['title'] }}</h4>

    <div class="report-body">
        {!! $section['content'] !!}
    </div>

</div>

@endforeach

</body>

</html>
