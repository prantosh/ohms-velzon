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

.study-title {
    font-size: 18px;
    text-align: center;
    margin-top: 12px;
}

.report-body {
    margin-top: 14px;
}

.report-body table {
    margin-top: 6px;
}

/*
    Doctor's signature stamp, repeated on every page at a fixed spot on
    the physical letterhead paper: 11.5cm from the true left edge, 5.65cm
    from the true bottom edge. DomPDF measures position:fixed elements
    from the page's margin/content box, not the true page edge, so the
    offsets below subtract out this page's own @page margins (same
    negative-offset trick used for the header/footer images in
    apps-pathology-report-pdf-whatsapp.blade.php) to land back at the
    true physical position regardless of margin-left/margin-bottom.
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
            <span class="label-blue">Test Date :</span>
            {{ $invoice->test_date ? \Carbon\Carbon::parse($invoice->test_date)->format('d-m-Y') : '-' }}
        </td>
    </tr>
</table>

<h4 class="study-title">{{ $itemDescriptions->implode(', ') }}</h4>

<div class="report-body">
    {!! $finding->content !!}
</div>

</body>

</html>
