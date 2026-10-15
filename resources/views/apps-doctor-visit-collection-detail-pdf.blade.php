< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Doctor Visit Collection Detail
                </title>

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
                            word-break: break-word;
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

@include('partials.pdf-header', ['reportTitle' => 'DOCTOR VISIT COLLECTION DETAIL'])

<!-- =======================================================
     DATE / SPECIALISATION / TOTALS
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">Date</td>
    <td>{{ $date->format('d-m-Y (l)') }}</td>

    <td width="25%" class="label-blue">Specialisation</td>
    <td>{{ $specialisation }}</td>
</tr>

<tr>
    <td class="label-blue">Total Visits</td>
    <td>{{ $summary['visits'] }}</td>

    <td class="label-blue">Total Collected</td>
    <td>{{ number_format($summary['collected'], 2) }}</td>
</tr>

<tr>
    <td class="label-blue">Doctor Share</td>
    <td>{{ number_format($summary['doctor_share'], 2) }}</td>

    <td class="label-blue">Clinic Share</td>
    <td><b>{{ number_format($summary['clinic_share'], 2) }}</b></td>
</tr>

<tr>
    <td class="label-blue">Printed By</td>
    <td colspan="3">{{ $printedBy }}</td>
</tr>

</table>

<!-- =======================================================
     VISIT DETAIL
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="7" style="text-align:left; background-color:#e6e6e6;">
        Visits On {{ $date->format('d-m-Y') }} -- {{ $specialisation }}
    </th>
</tr>
<tr>
    <th width="8%">Time</th>
    <th width="15%">Invoice No</th>
    <th>Patient</th>
    <th>Doctor</th>
    <th width="12%">Collected</th>
    <th width="12%">Doctor Share</th>
    <th width="12%">Clinic Share</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row->time_fmt }}</td>
    <td>{{ $row->invoice_no }}</td>
    <td>{{ $row->patient_name ?? '-' }}</td>
    <td>{{ $row->doctor_name ?? '-' }}</td>
    <td class="text-end">{{ number_format($row->collected, 2) }}</td>
    <td class="text-end">{{ number_format($row->doctor_share, 2) }}</td>
    <td class="text-end">{{ number_format($row->clinic_share, 2) }}</td>
</tr>
@empty
<tr>
    <td colspan="7" class="text-center">No Doctor Visit invoices found for this date and specialisation.</td>
</tr>
@endforelse

@if($rows->count())
<tr style="background-color:#f0f0f0; font-weight:bold;">
    <td colspan="4">Total</td>
    <td class="text-end">{{ number_format($summary['collected'], 2) }}</td>
    <td class="text-end">{{ number_format($summary['doctor_share'], 2) }}</td>
    <td class="text-end">{{ number_format($summary['clinic_share'], 2) }}</td>
</tr>
@endif

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
