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
    line-height: 1.35;
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

/* One brand navy used throughout -- matches the #1F4E78 header color
   already baked into every pathology template's own table headers
   (DEPARTMENT OF ..., INVESTIGATION/RESULT/RANGE), so the system-rendered
   chrome around the report no longer reads as a visually different blue
   from the admin-authored content inside it. */
.label-blue {
    color: #1F4E78;
    font-weight: bold;
}

/* A letterhead-style data strip (top/bottom rule only) instead of a full
   grid of boxed cells -- reads as part of the report's header, not a
   spreadsheet dropped onto the page. */
.patient-detail-table {
    border-top: 1.4pt solid #1F4E78;
    border-bottom: 1.4pt solid #1F4E78;
}

.patient-detail-table td {
    font-size: 9.5px;
    padding: 5px 6px;
    vertical-align: middle;
}

.patient-detail-table .pdt-label {
    color: #1F4E78;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.patient-detail-table .pdt-value {
    color: #222222;
}

.study-title {
    font-size: 16px;
    font-weight: bold;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #1F4E78;
    margin: 14px 0 0 0;
    padding-bottom: 6px;
    border-bottom: 1pt solid #B8C8D4;
}

.report-body {
    margin-top: 14px;
}

.report-body table {
    margin-top: 6px;
}

.key-param-history {
    margin-top: 16px;
    padding-top: 8px;
    border-top: 1pt solid #B8C8D4;
}

.key-param-history .heading {
    font-size: 11px;
    font-weight: bold;
    color: #1F4E78;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 4px;
}

.key-param-history table {
    font-size: 9px;
    border: 1px solid #B8C8D4;
}

.key-param-history th {
    background-color: #1F4E78;
    color: #FFFFFF;
    font-weight: bold;
    padding: 4px 6px;
    text-align: left;
    border: 1px solid #B8C8D4;
}

.key-param-history td {
    padding: 3px 6px;
    border: 1px solid #B8C8D4;
    text-align: left;
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
        <td class="pdt-label" width="12%">Patient Name</td>
        <td class="pdt-value" width="18%">{{ $invoice->patient_name }}</td>

        <td class="pdt-label" width="10%">Age / Sex</td>
        <td class="pdt-value" width="13%">{{ $invoice->patient_age ?? '' }} / {{ $invoice->patient_gender ?? '' }}</td>

        <td class="pdt-label" width="12%">Invoice No</td>
        <td class="pdt-value" width="18%">{{ $invoice->invoice_no }}</td>

        <td class="pdt-label" width="7%">Date</td>
        <td class="pdt-value" width="10%">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y') }}</td>
    </tr>
</table>

@php
    // From the controller: one date printed once when every test in this
    // report shares it; when the dates differ there is no single date, and the
    // title carries each date in brackets beside its test name instead.
    $dateInfo = $dateInfo ?? [
        'single' => $invoice->test_date ? \Carbon\Carbon::parse($invoice->test_date)->format('d-m-Y') : null,
        'title' => $itemDescriptions->implode(', '),
        'mixed' => false,
    ];
@endphp

@if(!$dateInfo['mixed'])
<table class="no-border">
    <tr>
        <td width="70%">
            <span class="label-blue">Test Date :</span>
            {{ $dateInfo['single'] ?? '-' }}
        </td>
    </tr>
</table>
@endif

@php $customTitle = trim((string) ($finding->report_title ?? '')); @endphp

<h4 class="study-title">{{ $customTitle !== '' ? $customTitle : $dateInfo['title'] }}</h4>
@if($customTitle !== '' && $dateInfo['mixed'])
<div style="text-align:center; font-size:10px; margin:0 0 6px 0;">Test dates: {{ $dateInfo['title'] }}</div>
@endif

<div class="report-body">
    {!! $finding->content !!}
</div>

@if(!empty($keyParameterHistory))
<div class="key-param-history">
    <div class="heading">Previous Values</div>
    <table>
        <thead>
            <tr>
                <th>Parameter</th>
                <th>Current</th>
                @for($i = 0; $i < 3; $i++)
                <th>Previous {{ $i + 1 }}</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @foreach($keyParameterHistory as $row)
            <tr style="{{ $loop->even ? 'background-color:#F7FAFC;' : '' }}">
                <td>{{ $row['parameter_name'] }}{{ $row['uom'] ? ' (' . $row['uom'] . ')' : '' }}</td>
                <td>{{ $row['current'] ?? '-' }}</td>
                @for($i = 0; $i < 3; $i++)
                <td>
                    @if(isset($row['history'][$i]))
                        {{ $row['history'][$i]['result_value'] }} ({{ $row['history'][$i]['test_date_fmt'] }})
                    @else
                        -
                    @endif
                </td>
                @endfor
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@include('partials.report-correction-footnote', ['correctedFromSentAt' => $finding->corrected_from_sent_at ?? null])

</body>

</html>
