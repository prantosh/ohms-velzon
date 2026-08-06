< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Doctor Consultation List
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
                        font-size: 12px;
                        color: #000;
                        margin: 0;
                        padding: 0;
                    }

                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 10px;
                    }

                        table th,
                        table td {
                            border: 1px solid #000;
                            padding: 6px;
                            vertical-align: top;
                        }

                    .text-end {
                        text-align: right;
                    }

                    .text-center {
                        text-align: center;
                    }

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

                    .prepared {
                        color: #000;
                        font-weight: bold;
                    }

                    .not-prepared {
                        color: #000;
                        font-style: italic;
                    }

                    .signature-section {
                        margin-top: 60px;
                    }
                </style>

        </head>

        <body>

@include('partials.pdf-header', ['reportTitle' => 'DOCTOR CONSULTATION LIST'])

<!-- =======================================================
     DOCTOR / DATE INFO
======================================================= -->

<table>

<tr>
    <td width="25%" class="label-blue">Doctor</td>
    <td>{{ $doctor->doctor_name }}</td>

    <td width="25%" class="label-blue">Date</td>
    <td>{{ $date->format('d-m-Y (l)') }}</td>
</tr>

<tr>
    <td class="label-blue">Total Patients</td>
    <td>{{ $rows->count() }}</td>

    <td class="label-blue">Invoice Prepared</td>
    <td>{{ $rows->where('invoice_prepared', true)->count() }} / {{ $rows->count() }}</td>
</tr>

</table>

<!-- =======================================================
     PATIENT LIST
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="6" style="text-align:left; background-color:#e6e6e6;">
        {{ $doctor->doctor_name }} &mdash; Consultation List for {{ $date->format('d-m-Y (l)') }}
    </th>
</tr>
<tr>
    <th width="8%">Token</th>
    <th>Patient Name</th>
    <th width="17%">Mobile No</th>
    <th width="14%">Age/Gender</th>
    <th width="14%">Time</th>
    <th width="20%">Invoice Status</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td class="text-center">{{ $row->token_no }}</td>
    <td>{{ $row->patient_name }}</td>
    <td>{{ $row->patient_mobile_no ?? '-' }}</td>
    <td>{{ $row->patient_age ?? '-' }}/{{ $row->patient_gender ?? '-' }}</td>
    <td>{{ $row->appointment_time_fmt ?? '-' }}</td>
    <td>
        @if($row->invoice_prepared)
            <span class="prepared">{{ $row->invoice_no }}</span>
        @else
            <span class="not-prepared">Not Prepared</span>
        @endif
    </td>
</tr>
@empty
<tr>
    <td colspan="6" class="text-center">No appointments found for this date.</td>
</tr>
@endforelse

</tbody>

</table>

<div class="signature-section">

<table border="0" style="border:none; margin:0;">

<tr>

<td class="no-border text-center" style="width:45%;">

_____________________

<br>

Reception / Front Desk

</td>

<td class="no-border" style="width:10%;"></td>

<td class="no-border text-center" style="width:45%;">

_____________________

<br>

Doctor's Acknowledgement

</td>

</tr>

</table>

</div>

</body>

</html>
