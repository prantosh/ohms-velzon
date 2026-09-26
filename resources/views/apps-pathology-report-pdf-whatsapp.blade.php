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
    margin-top: 43.8mm;
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
    top: -43.8mm;
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

</body>

</html>
