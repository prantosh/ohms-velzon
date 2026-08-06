< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Doctor Payment Report
                </title>

@php

function fmtMoney6($v)
{
    return number_format((float) ($v ?? 0), 2);
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
                        font-size: 9px;
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
                            padding: 3px 5px;
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

@include('partials.pdf-header', ['reportTitle' => 'DOCTOR PAYMENT REPORT (VISIT AND TEST)'])

<!-- =======================================================
     USER / DOCTOR / DATE / SUMMARY
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">User</td>
    <td>{{ $userLabel }}</td>

    <td width="25%" class="label-blue">Doctor</td>
    <td>{{ $doctorLabel }}</td>
</tr>

<tr>
    <td class="label-blue">Date</td>
    <td>{{ $date->format('d-m-Y (l)') }}</td>

    <td class="label-blue">Items</td>
    <td>{{ $summary['total_items'] }}</td>
</tr>

<tr>
    <td class="label-blue">Total Received</td>
    <td>Rs. {{ fmtMoney6($summary['total_gross']) }}</td>

    <td class="label-blue">Total Doctor Fees</td>
    <td>Rs. {{ fmtMoney6($summary['total_doctor_fees']) }}</td>
</tr>

<tr>
    <td class="label-blue">Total Clinic Charge</td>
    <td colspan="3"><b>Rs. {{ fmtMoney6($summary['total_clinic_charge']) }}</b></td>
</tr>

</table>

<!-- =======================================================
     DETAIL
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="{{ 8 + ($isAllUsers ? 1 : 0) + ($isAllDoctors ? 1 : 0) }}" style="text-align:left; background-color:#e6e6e6;">
        {{ $doctorLabel }} &mdash; {{ $userLabel }} &mdash; Doctor Payment Report for {{ $date->format('d-m-Y (l)') }}
    </th>
</tr>
<tr>
    <th>Invoice No</th>
    @if($isAllUsers)
    <th>User</th>
    @endif
    @if($isAllDoctors)
    <th>Doctor</th>
    @endif
    <th>Patient Name</th>
    <th width="10%">Card No</th>
    <th>Item Description</th>
    <th width="7%">Time</th>
    <th width="10%">Doctor Fees</th>
    <th width="10%">Clinic Charge</th>
    <th width="12%">Settlement No</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row->invoice_no }}</td>
    @if($isAllUsers)
    <td>{{ $row->user_name }}</td>
    @endif
    @if($isAllDoctors)
    <td>{{ $row->doctor_name }}</td>
    @endif
    <td>{{ $row->patient_name }}</td>
    <td>{{ $row->card_number ?: '-' }}</td>
    <td>{{ $row->item_description }}</td>
    <td>{{ $row->time_fmt }}</td>
    <td class="text-end">{{ fmtMoney6($row->doctor_fees) }}</td>
    <td class="text-end">{{ fmtMoney6($row->clinic_charge) }}</td>
    <td>{{ $row->settlement_display }}</td>
</tr>
@empty
<tr>
    <td colspan="{{ 8 + ($isAllUsers ? 1 : 0) + ($isAllDoctors ? 1 : 0) }}" class="text-center">No invoices found for this user and doctor on this date.</td>
</tr>
@endforelse

<tr style="background-color:#f0f0f0; font-weight:bold;">
    <td colspan="{{ 5 + ($isAllUsers ? 1 : 0) + ($isAllDoctors ? 1 : 0) }}" class="text-end">Total</td>
    <td class="text-end">{{ fmtMoney6($summary['total_doctor_fees']) }}</td>
    <td class="text-end">{{ fmtMoney6($summary['total_clinic_charge']) }}</td>
    <td></td>
</tr>

</tbody>

</table>

<div class="signature-section">

<table border="0" style="border:none; margin:0;">

<tr>

<td class="no-border text-center">

_____________________

<br>

Prepared By ({{ $userLabel }})

</td>

</tr>

</table>

</div>

</body>

</html>
