<!DOCTYPE html>
<html>

 

        <head>

            <meta charset="utf-8">

                <title>
                    Diagnostic Invoice
                </title>
@php

function fmtDate($date)
{
    return empty($date)
        ? ''
        : \Carbon\Carbon::parse($date)->format('d-m-Y');
}
function fmtDateTime($date)
{
    return empty($date)
        ? ''
        : \Carbon\Carbon::parse($date)->format('d-m-Y h:i:s A' );
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
                        font-size: 12px;
                        color: #000;
                        margin: 0;
                        padding: 0;
                    }

                    .invoice-box {
                        width: 100%;
                        border: 1px solid #000;
                        padding: 15px;
                    }

                    .title {
                        text-align: center;
                        margin-bottom: 5px;
                    }

                        .title h2,
                        .title h3,
                        .title h4 {
                            margin: 2px 0;
                            color: #003399;
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

                    /* Blue Labels */

                    .label-blue {
                        color: #003399;
                        font-weight: bold;
                    }

                    /* Blue Table Headers */

                    table thead th {
                        color: #003399;
                        font-weight: bold;
                        text-align: center;
                    }

                    /* Amounts */

                    .amount {
                        color: #000000;
                        text-align: right;
                        font-weight: normal;
                    }

                    .signature-section {
                        margin-top: 50px;
                    }

                    .signature {
                        width: 45%;
                        text-align: center;
                        display: inline-block;
                    }
                </style>

        </head>

<body>
@include('partials.pdf-header', ['reportTitle' => 'DOCTOR CONSULTATION INVOICE', 'headerColor' => '#003399'])


    <div class="invoice-box">

        

        <!-- Patient Details -->
        <table>
            <tr>
                <th width="25%">Patient ID</th>
                <td width="25%">{{ $invoice->patient_id }}</td>

                <th>Patient Name</th>
                <td>{{ $invoice->patient_name }}</td>
            </tr>
             <tr>
                <th width="25%">Patient Age / Sex</th>
                <td width="25%">
                    {{ $invoice->patient_age ?? '' }}
                    /
                    {{ $invoice->patient_gender ?? '' }}
                </td>
                <th>Patient Mobile</th>
                <td>{{ $invoice->patient_mobile_no }}</td>
            </tr>

            <tr>
                
                <th width="25%">Invoice No</th>
                <td width="25%">{{ $invoice->invoice_no }}</td>
                <th>Invoice Date</th>
                <td>{{ date('d-m-Y', strtotime($invoice->invoice_date)) }}</td>
            </tr>

            <tr>
                <th>Name of Doctor</th>
                <td>{{ $appointment->doctor_name ?? '' }}</td>

                <th>Visit Date</th>
                <td>{{ $appointment->appointment_date ?? '' }}</td>
            </tr>

            
            <tr>
                <th>Visit Time</th>
                <td>{{ $appointment->appointment_time ?? '' }}</td>

                <th>Appointment No</th>
                <td>{{ $appointment->appointment_no ?? '' }}</td>
            </tr>
        </table>

        <!-- Invoice Amount -->
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th width="25%">Amount</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td>Doctor Consultation Fee</td>
                    <td class="text-end">
                        {{ number_format($invoice->consultation_fee, 2) }}
                    </td>
                </tr>

                <tr>
                    <td>
                        @if(($invoice->discount ?? 0) > 0)
                            Discount
                            @if(!empty($invoice->card_number))
                                (Reference Card No. {{ $invoice->card_number }})
                            @endif
                        @else
                            Discount
                        @endif
                    </td>

                    <td class="text-end">
                        {{ number_format($invoice->discount, 2) }}
                    </td>
                </tr>

                <tr>
                    <th>Total Amount</th>
                    <th class="text-end">
                        {{ number_format($invoice->total_amount, 2) }}
                    </th>
                </tr>

                <tr>
                    <th>Paid Amount</th>
                    <th class="text-end">
                        {{ number_format($invoice->paid_amount, 2) }}
                    </th>
                </tr>

                
               
            </tbody>
        </table>
        @php
        $f = new \NumberFormatter(
    "en",
    \NumberFormatter::SPELLOUT
);

$amountInWords = ucwords(
    $f->format($invoice->paid_amount)
);

@endphp
        <table>

    <tr>

        <td>

            <span class="label-blue">

                Receipt Acknowledgement :

            </span>

            Received with thanks from
                <b>{{ $invoice->patient_name }}</b>
                an amount of
                <b>
                    Rs. {{ number_format($invoice->paid_amount,2) }}
                    ({{ $amountInWords }} Only)
                </b>
                towards consultation charges.

                <br>

                Received By :
                <b>{{ optional(Auth::user())->name }}</b>
                Received Time :
                <b> {{ fmtDateTime($invoice->created_at)  }}</b>
        </td>

    </tr>

</table>


        <!-- Signature -->
        <div class="signature-section">

           

            <div class="signature" style="float:right;">
                ______________________
                <br>
                Authorized Signature
            </div>

        </div>

    </div>

</body>

</html>
