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
       (unchanged, tied to the physical art -- see .report-header-image's
       own comment for why its offset must always exactly match this
       margin-top value); the remaining 17.5mm is layout BUDGET for the
       patient-detail strip's own two lines of content plus the gap before
       the group heading that follows it -- NOT itself tied to any
       pre-printed art, so safe to resize on its own (see
       .patient-detail-fixed's own comment for how it stays flush against
       the header image despite this). Was 35mm (-> 10mm of actual content
       + ~25mm of pure blank gap); halved to close roughly half that blank
       gap per request, while keeping both images and the patient-detail
       strip in their original printed positions. */
    margin-top: 61.3mm;
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
    /* Offset must always equal the FULL margin-top exactly, not just the
       image's own 43.8mm height -- DomPDF measures position:fixed from
       the page's margin/content box, which sits however far margin-top
       pushed it down to make room for .patient-detail-fixed below it.
       Using -43.8mm here would drag the image down with that box and
       overlap the patient-detail strip instead of staying flush with the
       true page edge -- this has to change in lockstep with @page's own
       margin-top any time that value does. */
    top: -61.3mm;
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
   page) -- positioned in the 17.5mm strip @page reserves for it just
   below the header image, with the same 40px side padding the body text
   uses (this div sits outside body's own padding since it's fixed), so
   every page identifies the patient even if pages get separated.
   Paired with @page's own margin-top and .report-header-image's offset:
   this strip's true position is margin-top + top = 61.3mm - 17.5mm =
   43.8mm from the true page top -- exactly flush against the bottom of
   the header image, same as before (was 78.8mm - 35mm = 43.8mm) --
   shrinking both margin-top and this offset by the same 17.5mm keeps the
   strip flush in its original spot while pulling the group heading that
   follows it closer, which is the whole point of the change. */
.patient-detail-fixed {
    position: fixed;
    top: -17.5mm;
    left: 0;
    right: 0;
    padding: 0 40px;
}

/* Same two-line letterhead-style header as the single-report layout
   (apps-pathology-report-pdf.blade.php): line 1 is a bordered strip
   (top/bottom rule), line 2 sits directly under it with no border of its
   own -- scoped to .patient-detail-fixed specifically so it doesn't
   affect the unrelated .no-border table each section below already uses
   for its own per-section Test Date. Fits this header's own 17.5mm
   layout budget -- see .patient-detail-fixed above and @page's own
   comment for the 43.8mm anchor that IS tied to the scanned letterhead
   image and must not move. */
.patient-detail-table {
    border-top: 1.4pt solid #1F4E78;
    border-bottom: 1.4pt solid #1F4E78;
}

.patient-detail-fixed td {
    font-size: 8.5px;
    padding: 3px 4px;
    vertical-align: middle;
}

.patient-detail-fixed .no-border td {
    border: none;
    padding: 3px 4px;
}

.patient-detail-fixed .pdt-label {
    color: #1F4E78;
    font-weight: bold;
}

.patient-detail-fixed .pdt-value {
    color: #222222;
    font-weight: normal;
}

.patient-detail-fixed .pdt-value-name {
    color: #222222;
    font-weight: bold;
    font-size: 9.5px;
}

.study-title {
    font-size: 12px;
    text-align: center;
    margin-top: 6px;
}

.group-heading {
    color: #003399;
    font-weight: bold;
    font-size: 14px;
    border-bottom: 2px solid #003399;
    margin-top: 9px;
    padding-bottom: 2px;
}

.group-heading:first-of-type {
    margin-top: 3px;
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
            <td width="35%" style="text-align:left;">
                <span class="pdt-label">Patient Name :</span>
                <span class="pdt-value-name">{{ $invoice->patient_name }}</span>
            </td>

            <td width="18%">
                <span class="pdt-label">Age / Sex :</span>
                <span class="pdt-value">{{ $invoice->patient_age ?? '' }} / {{ $invoice->patient_gender ?? '' }}</span>
            </td>

            <td width="47%" style="text-align:right;">
                <span class="pdt-label">Invoice No / Date :</span>
                <span class="pdt-value">{{ $invoice->invoice_no }} / {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y') }}</span>
            </td>
        </tr>
    </table>

    {{-- One shared Test Date for the whole fixed header (the invoice's own
         date) -- each section below still prints its own precise test
         date(s) too (see the per-section "Test Date :" row further down),
         this is just the same at-a-glance identifier the patient-name
         line already gives on every page. --}}
    <table class="no-border">
        <tr>
            <td width="35%">
                <span class="pdt-label">Test Date :</span>
                <span class="pdt-value">{{ $invoice->test_date ? \Carbon\Carbon::parse($invoice->test_date)->format('d-m-Y') : '-' }}</span>
            </td>

            <td width="35%">
                <span class="pdt-label">Ref. Doctor :</span>
                <span class="pdt-value">{{ strlen(trim((string) $invoice->referred_doctor)) < 8 ? 'Not Mentioned' : trim($invoice->referred_doctor) }}</span>
            </td>

            <td width="30%" style="text-align:right;">
                <span class="pdt-label">Patient Id :</span>
                <span class="pdt-value">{{ $invoice->patient_id ?: '-' }}</span>
            </td>
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

    {{-- The single shared Test Date now prints once in the fixed header
         at the top of every page instead of repeating per section (see
         .patient-detail-fixed's own Test Date line) -- when dates differ
         across this report's own tests, the title below still carries
         each one in brackets, since the fixed header can only show ONE
         date for the whole invoice. --}}
    <h4 class="study-title">{{ $section['title'] }}</h4>
    @if(!empty($section['date_note']))
    <div style="text-align:center; font-size:10px; margin:0 0 6px 0;">Test dates: {{ $section['date_note'] }}</div>
    @endif

    <div class="report-body">
        {!! $section['content'] !!}
    </div>

    @include('partials.report-correction-footnote', ['correctedFromSentAt' => $section['corrected_from_sent_at'] ?? null])

</div>

@endforeach

</body>

</html>
