<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>Prescription</title>

<style>

{{-- No header on this layout -- it prints onto pre-printed clinic
     letterhead paper (logo/clinic name/address/footer already on the
     physical sheet). @page margin is zero and every element below is
     positioned with position:absolute inside a full-page .page-canvas
     (position:relative, exactly A4-sized) -- so every top/left is a
     direct, unambiguous measurement from the true physical page corner,
     matching how an alignment offset would be measured with a ruler
     against the printed letterhead. Same layout as
     apps-doctor-visit-prescription-pdf.blade.php, for EYE/Optometry
     doctors' own letterhead, minus the Sex field (not on that
     letterhead) and with no vertical line. --}}
@page {
    margin: 0;
}

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 12px;
    color: #000;
    margin: 0;
    padding: 0;
}

.page-canvas {
    position: relative;
    width: 210mm;
    height: 297mm;
}

.page-canvas > div {
    position: absolute;
}

.label-blue {
    color: #003399;
    font-weight: bold;
}

.doctor-line {
    left: 10mm;
    width: 190mm;
    white-space: nowrap;
}

.doctor-line .label-blue {
    margin-right: 4px;
}

.patient-line-value {
    font-size: 10px;
    white-space: nowrap;
}

.ids-line {
    left: 10mm;
    font-size: 10px;
}

.signature {
    left: 145mm;
    width: 55mm;
    text-align: center;
}

</style>

</head>

<body>

<div class="page-canvas">

    <div class="doctor-line" style="top:60mm;">
        <span class="label-blue">Doctor :</span>
        {{ $invoice->doctor_name ?? optional($doctor)->doctor_name }},
        {{ optional($doctor)->qualification }}
        &nbsp;&nbsp;&nbsp;
        <span class="label-blue">Specialisation :</span>
        {{ optional($doctor)->specialisation }}
        &nbsp;&nbsp;&nbsp;
        <span class="label-blue">Reg. No :</span>
        {{ optional($doctor)->registration_no }}
    </div>

    {{-- Name/Age/Date labels are already pre-printed on the letterhead
         paper on this line -- only the values are rendered here, each at
         the exact left margin measured from the physical page edge, so it
         lands in the blank space after its printed label. No Sex field on
         this letterhead. --}}
    <div class="patient-line-value" style="top:67mm; left:30mm;">{{ $invoice->patient_name }}</div>
    <div class="patient-line-value" style="top:67mm; left:125mm;">{{ $invoice->patient_age ?? '' }}</div>
    <div class="patient-line-value" style="top:67mm; left:175mm;">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y') }}</div>

    <div class="ids-line" style="top:84mm;"><span class="label-blue">Invoice No.</span></div>
    <div class="ids-line" style="top:92mm;">{{ $invoice->invoice_no }}</div>
    <div class="ids-line" style="top:100mm;"><span class="label-blue">Patient ID</span></div>
    <div class="ids-line" style="top:108mm;">{{ $invoice->patient_id }}</div>

    {{-- History/BP/Weight/SPO2 are already pre-printed on the letterhead
         paper -- not rendered here, same reasoning as the header. Blank
         space below (up to the signature) is left for the prescription
         itself to be filled in by hand. --}}

    <div class="signature" style="top:250mm;">
        ______________________
        <br>
        Doctor's Signature
    </div>

</div>

</body>

</html>
