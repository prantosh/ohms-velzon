< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Doctor Visit Collection Summary
                </title>

@php
// Mirrors the on-screen report: each date column shows its own visit count
// and collected amount for that specialisation, which together sum to the
// row's own Visits/Collected totals.
function dvcDateCell($stats)
{
    $stats = $stats ?? ['visits' => 0, 'collected' => 0];
    if (empty($stats['visits'])) {
        return '0';
    }
    return '<b>' . number_format($stats['collected'], 2) . '</b>'
        . '<div style="font-size:7px; color:#555;">' . $stats['visits'] . ' visit' . ($stats['visits'] == 1 ? '' : 's') . '</div>';
}
@endphp

                <style>

                    @page {
                            margin-top: 1in;
                            margin-right: 40px;
                            margin-bottom: 40px;
                            margin-left: 40px;
                        }

                    @page :first {
                            margin-top: 0.4in;
                        }

                    body {
                        font-family: DejaVu Sans, sans-serif;
                        font-size: 8.5px;
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
                            border: 1px solid #000;
                            padding: 2.5px 4px;
                            vertical-align: top;
                        }

                    .summary-table th,
                    .summary-table td {
                        border: 1px solid #ccc;
                    }

                    .text-end {
                        text-align: right;
                    }

                    .text-center {
                        text-align: center;
                    }

                    .no-border,
                    .no-border td {
                        border: none;
                    }

                    .label-blue {
                        color: #000;
                        font-weight: bold;
                    }

                    table thead th {
                        color: #000;
                        font-weight: bold;
                        text-align: center;
                    }

                    .signature-section {
                        margin-top: 60px;
                    }
                </style>

        </head>

        <body>

@include('partials.pdf-header', ['reportTitle' => 'DOCTOR VISIT COLLECTION SUMMARY'])

<!-- =======================================================
     RANGE / TOTALS
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">From Date</td>
    <td>{{ $fromDate->format('d-m-Y (l)') }}</td>

    <td width="25%" class="label-blue">To Date</td>
    <td>{{ $toDate->format('d-m-Y (l)') }}</td>
</tr>

<tr>
    <td class="label-blue">Total Visits</td>
    <td>{{ $grandTotal['visits'] }}</td>

    <td class="label-blue">Total Collected</td>
    <td>{{ number_format($grandTotal['collected'], 2) }}</td>
</tr>

<tr>
    <td class="label-blue">Doctor Share</td>
    <td>{{ number_format($grandTotal['doctor_share'], 2) }}</td>

    <td class="label-blue">Clinic Share</td>
    <td><b>{{ number_format($grandTotal['clinic_share'], 2) }}</b></td>
</tr>

<tr>
    <td class="label-blue">Printed By</td>
    <td colspan="3">{{ $printedBy }}</td>
</tr>

</table>

<p style="font-size:7.5px; color:#555; margin-top:4px;">
    <b>How to read the table below:</b> Clinic Share = Collected &minus; Doctor Share on every row. Each
    date column shows that specialisation's own visit count and collected amount for that day.
</p>

<!-- =======================================================
     SPECIALISATION-WISE BREAKDOWN (rows), DATE-WISE (columns)
======================================================= -->

<table>

@php $colCount = 5 + count($dateColumns); @endphp

<thead>
<tr>
    <th colspan="{{ $colCount }}" style="text-align:left; background-color:#e6e6e6;">
        Specialisation-Wise Collection for {{ $fromDate->format('d-m-Y') }} to {{ $toDate->format('d-m-Y') }}
    </th>
</tr>
<tr>
    <th width="14%">Specialisation</th>
    <th width="7%">Visits</th>
    <th width="9%">Collected</th>
    <th width="9%">Doctor Share</th>
    <th width="9%">Clinic Share</th>
    @foreach($dateColumns as $col)
    <th>{{ $col['label'] }}</th>
    @endforeach
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row['specialisation'] }}</td>
    <td class="text-end">{{ $row['visits'] }}</td>
    <td class="text-end">{{ number_format($row['collected'], 2) }}</td>
    <td class="text-end">{{ number_format($row['doctor_share'], 2) }}</td>
    <td class="text-end">{{ number_format($row['clinic_share'], 2) }}</td>
    @foreach($dateColumns as $col)
    <td class="text-end">{!! dvcDateCell($row['dates'][$col['key']] ?? null) !!}</td>
    @endforeach
</tr>
@empty
<tr>
    <td colspan="{{ $colCount }}" class="text-center">No Doctor Visit invoices found in this date range.</td>
</tr>
@endforelse

<tr style="background-color:#f0f0f0; font-weight:bold;">
    <td>Total</td>
    <td class="text-end">{{ $grandTotal['visits'] }}</td>
    <td class="text-end">{{ number_format($grandTotal['collected'], 2) }}</td>
    <td class="text-end">{{ number_format($grandTotal['doctor_share'], 2) }}</td>
    <td class="text-end">{{ number_format($grandTotal['clinic_share'], 2) }}</td>
    @foreach($dateColumns as $col)
    <td class="text-end">{!! dvcDateCell($grandTotal['dates'][$col['key']] ?? null) !!}</td>
    @endforeach
</tr>

</tbody>

</table>

<div class="signature-section">

<table border="0" style="border:none; margin:0;">

<tr>

<td class="no-border text-center">

_____________________

<br>

Prepared By ({{ $printedBy }})

</td>

</tr>

</table>

</div>

</body>

</html>
