< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Diagnostic Test Report
                </title>
@php

function fmtDate($date)
{
    return empty($date)
        ? ''
        : \Carbon\Carbon::parse($date)->format('d-m-Y');
}

function formatReferenceRange($row)
{
    $lines = [];

    if (!empty($row['range_male'])) {
        $lines[] = 'Male : ' . e($row['range_male']);
    }

    if (!empty($row['range_female'])) {
        $lines[] = 'Female : ' . e($row['range_female']);
    }

    if (!empty($row['range_common'])) {
        $lines[] = 'Male/Female : ' . e($row['range_common']);
    }

    return implode('<br>', $lines);
}

@endphp
                <style>

                    @page {
                            margin-top: 50mm;
                            margin-right: 40px;
                            margin-bottom: 40mm;
                            margin-left: 40px;
                        }

                    body {
                        font-family: DejaVu Sans, sans-serif;
                        font-size: 10px;
                        color: #000;
                        margin: 0;
                        padding: 0;
                    }

                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 8px;
                    }

                        table th,
                        table td {
                            border: none;
                            padding: 3px;
                            vertical-align: top;
                        }

                    .text-center {
                        text-align: center;
                    }

                    .no-border td {
                        border: none;
                    }

                    /* Blue Labels */

                    .label-blue {
                        color: #000;
                        font-weight: bold;
                    }

                    /* Table Headers */

                    table thead th {
                        color: #000;
                        font-weight: bold;
                        text-align: center;
                    }

                    .additional-info {
                        margin-top: 20px;
                        border-top: 1px solid #999;
                        padding-top: 10px;
                    }

                    .additional-info h4 {
                        margin: 0 0 6px 0;
                        color: #000;
                    }

                    .additional-info p {
                        margin: 0;
                        text-align: justify;
                        line-height: 1.5;
                    }
                </style>

        </head>

        <body>

<!-- =======================================================
     INVOICE / PATIENT INFORMATION
======================================================= -->

<table>

<tr>
    <td width="25%" class="label-blue">Invoice No</td>
    <td>{{ $invoice->invoice_no }}</td>

    <td width="25%" class="label-blue">Invoice Date</td>
    <td>{{ fmtDate($invoice->invoice_date) }}</td>
</tr>

<tr>
    <td class="label-blue">Patient ID</td>
    <td>{{ $invoice->patient_id }}</td>

    <td class="label-blue">Patient Name</td>
    <td>{{ $invoice->patient_name }}</td>
</tr>

<tr>
    <td class="label-blue">Age / Gender</td>
    <td>{{ $invoice->patient_age }} / {{ $invoice->patient_gender }}</td>

    <td class="label-blue">Mobile</td>
    <td>{{ $invoice->patient_mobile_no }}</td>
</tr>

<tr>
    <td class="label-blue">Referred Doctor</td>
    <td>{{ $invoice->referred_doctor }}</td>

    <td class="label-blue">Payment Status</td>
    <td>{{ $invoice->status }}</td>
</tr>

<tr>
    <td class="label-blue">Test Date</td>
    <td colspan="3">{{ fmtDate($invoice->test_date) }}</td>
</tr>

</table>

<br>

<!-- =======================================================
     TEST RESULTS
======================================================= -->

<table>

<thead>

<tr>

<th colspan="6" style="background:#e6e6e6; font-weight:bold; color:#000;">{{ $groupName }}</th>

</tr>

<tr>

<th width="5%">SL</th>

<th width="30%">Test Description</th>

<th width="15%">Result</th>

<th width="10%">Unit</th>

<th width="20%">Reference Range</th>

<th width="20%">Method</th>

</tr>

</thead>

<tbody>

@php $sl = 1; $previousPackageGroup = null; @endphp

@foreach($tests as $row)

@if(!empty($row['package_group_name']) && $row['package_group_name'] !== $previousPackageGroup)

<tr>
<td colspan="6" style="font-weight:bold; background-color:#f0f0f0; padding-left:8px;">{{ $row['package_group_name'] }}</td>
</tr>

@endif

@php $previousPackageGroup = $row['package_group_name'] ?? null; @endphp

@php
    $printAsHeaderOnly = $row['has_analytes']
        && empty($row['result_value'])
        && empty($row['remarks']);
@endphp

@if($printAsHeaderOnly)

<tr>

<td colspan="6" style="font-weight:bold; color:#000; padding-top:8px;">{{ $row['item_description'] }}</td>

</tr>

@else

<tr>

<td class="text-center">{{ $sl++ }}</td>

<td class="text-center">{{ $row['item_description'] }}</td>

<td class="text-center">{{ $row['result_value'] }}</td>

<td class="text-center">{{ $row['uom'] }}</td>

<td class="text-center">{!! formatReferenceRange($row) !!}</td>

<td class="text-center">{{ $row['method'] }}</td>

</tr>

@endif

@if($row['has_analytes'])

@php $previousAnalyteGroup = null; $previousAnalyteSubGroup = null; @endphp

@foreach($row['analytes'] as $analyte)

@if(!empty($analyte['sub_group_name']) && $analyte['sub_group_name'] !== $previousAnalyteSubGroup)

<tr>
<td colspan="6" style="font-weight:bold; background-color:#e6e6e6; padding-left:8px;">{{ $analyte['sub_group_name'] }}</td>
</tr>

@php $previousAnalyteGroup = null; @endphp

@endif

@if(!empty($analyte['group_name']) && $analyte['group_name'] !== $previousAnalyteGroup)

<tr>
<td colspan="6" style="font-weight:bold; background-color:#f0f0f0; padding-left:16px;">{{ $analyte['group_name'] }}</td>
</tr>

@endif

@php
    $previousAnalyteGroup = $analyte['group_name'] ?? null;
    $previousAnalyteSubGroup = $analyte['sub_group_name'] ?? null;
@endphp

<tr>

<td class="text-center">{{ $sl++ }}</td>

<td class="text-center" style="padding-left:16px;">{{ $analyte['analyte_name'] }}</td>

<td class="text-center">{{ $analyte['result_value'] }}</td>

<td class="text-center">{{ $analyte['uom'] }}</td>

<td class="text-center">{!! formatReferenceRange($analyte) !!}</td>

<td class="text-center">{{ $analyte['method'] }}</td>

</tr>

@endforeach

@endif

@endforeach

</tbody>

</table>

<!-- =======================================================
     ADDITIONAL PARAMETERS (Instrument Used, Kit Used, etc.)
======================================================= -->

<table>

<tr>

<td>

@foreach($tests as $row)
    @foreach($row['extra_values'] as $extraValue)
        @if(!empty($extraValue['value']))
            <b>{{ $extraValue['field_name'] }} :</b> {{ $extraValue['value'] }}<br>
        @endif
    @endforeach
@endforeach

</td>

</tr>

</table>

<!-- =======================================================
     REMARKS
======================================================= -->

<table>

<tr>

<td>

<b>Remarks :</b>

@foreach($tests as $row)
    @if(!empty($row['remarks']))
        {{ $row['item_description'] }} - {{ $row['remarks'] }}<br>
    @endif
    @if($row['has_analytes'])
        @foreach($row['analytes'] as $analyte)
            @if(!empty($analyte['remarks']))
                {{ $analyte['analyte_name'] }} - {{ $analyte['remarks'] }}<br>
            @endif
        @endforeach
    @endif
@endforeach

</td>

</tr>

</table>

@if(!empty($additionalContent))

<!-- =======================================================
     ADDITIONAL INFORMATION
======================================================= -->

<div class="additional-info">

    <h4>Additional Information : {{ $groupName }}</h4>

    <p>{!! nl2br(e($additionalContent)) !!}</p>

</div>

@endif

</body>

</html>
