<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>Pathology Report</title>

<style>

/*
    Header/footer are full-bleed scanned-letterhead images spanning the
    true physical page edge to edge -- @page margin-left/right are 0 so
    no horizontal offset math is needed, while margin-top/bottom are set
    to each image's own height (at full 210mm page width) so DomPDF
    reserves that band on EVERY generated page. The images themselves are
    position:fixed with a NEGATIVE top/bottom offset equal to that same
    margin -- DomPDF measures position:fixed from the page's
    margin/content box, not the true page edge, so this exact
    negative-offset trick is what lands them back at the true edge
    (same technique proven on the Doctor Visit prescription layouts).

    Both images are cropped from the same full-page scan, so they share
    the same width and scale.
    2544x530 / 2544x744 at 210mm wide -> 43.8mm / 61.4mm tall.
*/
@page {
    /* 43.8mm is the scanned letterhead header image's own height
       (unchanged); the extra 20mm reserves room for the patient-detail
       strip below it, which is position:fixed so it repeats on every
       page -- see .patient-detail-fixed. */
    margin-top: 63.8mm;
    margin-right: 0;
    margin-bottom: 61.4mm;
    margin-left: 0;
}

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 12px;
    color: #000;
    margin: 0;
    padding: 0 40px;
}

.report-header-image {
    position: fixed;
    /* Offset must equal the FULL margin-top (63.8mm), not just the image's
       own 43.8mm height -- DomPDF measures position:fixed from the page's
       margin/content box, which moved down 20mm when margin-top grew to
       make room for .patient-detail-fixed below it. Using -43.8mm here
       (the old value) would drag the image down with that box and overlap
       the patient-detail strip instead of staying flush with the true
       page edge. */
    top: -63.8mm;
    left: 0;
    width: 210mm;
}

.report-footer-image {
    position: fixed;
    bottom: -61.4mm;
    left: 0;
    width: 210mm;
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
   page) -- positioned in the 20mm strip @page reserves for it just below
   the header image, with the same 40px side padding the body text uses
   (this div sits outside body's own padding since it's fixed), so every
   page identifies the patient even if pages get separated. */
.patient-detail-fixed {
    position: fixed;
    top: -20mm;
    left: 0;
    right: 0;
    padding: 0 40px;
}

.patient-detail-table th,
.patient-detail-table td {
    font-size: 9px;
    padding: 3px;
    border: 1px solid #000;
    vertical-align: top;
}

.study-title {
    font-size: 18px;
    text-align: center;
    margin-top: 6px;
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
    margin-top: 6px;
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

.report-body {
    margin-top: 8px;
}

/* The CKEditor body is a run of <p>s, each with the browser-default 1em
   top/bottom margin -- most of a short report's height was that spacing,
   which pushed every report onto a page of its own. */
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

<img class="report-header-image" src="{{ public_path('images/report_pathology_header.png') }}">
<img class="report-footer-image" src="{{ public_path('images/report_pathology_footer.png') }}">

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
    $sections: one entry per confirmed report unit on this invoice, covering
    every test group -- this is what makes the WhatsApp send a single
    document for the WHOLE invoice instead of one message per group/report.
    Each entry: ['group_name', 'title' (item descriptions), 'content',
    'confirmed_at']. Printed in the controller's own group order (grouped
    together, not interleaved), with one heading per group.
--}}
@php $previousGroup = null; @endphp

@foreach($sections as $i => $section)

@php $isFirstInGroup = $section['group_name'] !== $previousGroup; @endphp

@if($isFirstInGroup)
<h3 class="group-heading @if($i > 0) group-break @endif">{{ $section['group_name'] }}</h3>
@php $previousGroup = $section['group_name']; @endphp
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
