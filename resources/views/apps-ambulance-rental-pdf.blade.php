< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Ambulance Rental Invoice
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
        : \Carbon\Carbon::parse($date)->format('d-m-Y h:i A');
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
                        color: #003399;
                        font-weight: bold;
                    }

                    table thead th {
                        color: #003399;
                        font-weight: bold;
                        text-align: center;
                    }

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

@include('partials.pdf-header', ['reportTitle' => 'AMBULANCE RENTAL INVOICE', 'headerColor' => '#003399'])

<!-- =======================================================
     INVOICE / PATIENT INFORMATION
======================================================= -->

<table>

<tr>
    <td width="25%" class="label-blue">Invoice No</td>
    <td>{{ $invoice->invoice_no }}</td>

    <td width="25%" class="label-blue">Date of Booking</td>
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
    <td class="label-blue">Address</td>
    <td colspan="3">{{ $detail->address ?? '' }}</td>
</tr>

</table>

<br>

<!-- =======================================================
     TRIP DETAILS
======================================================= -->

<table>

<tr>
    <td width="25%" class="label-blue">From Destination</td>
    <td>{{ $detail->from_destination ?? '' }}</td>

    <td width="25%" class="label-blue">Destination</td>
    <td>{{ $detail->item_description }}</td>
</tr>

<tr>
    <td class="label-blue">Booking Type</td>
    <td>{{ $detail->booking_type === 'AC' ? 'AC' : 'Non-AC' }}</td>

    <td class="label-blue">Payment Mode</td>
    <td>{{ $invoice->payment_mode }}</td>
</tr>

<tr>
    <td class="label-blue">Pickup Time</td>
    <td>{{ fmtDateTime($detail->pickup_time) }}</td>

    <td class="label-blue">Release Time</td>
    <td>{{ fmtDateTime($detail->release_time) }}</td>
</tr>

<tr>
    <td class="label-blue">Odometer (Pickup)</td>
    <td>{{ $detail->odometer_pickup_km ?? '' }}</td>

    <td class="label-blue">Odometer (Release)</td>
    <td>{{ $detail->odometer_release_km ?? '' }}</td>
</tr>

</table>

<br>

<!-- =======================================================
     CHARGES
======================================================= -->

<table>

<thead>

<tr>

<th width="40%">Description</th>

<th width="20%">Fare</th>

<th width="20%">Waiting Charge</th>

<th width="20%">Amount</th>

</tr>

</thead>

<tbody>

<tr>

<td>Ambulance Rental - {{ $detail->item_description }}</td>

<td class="amount">{{ number_format($detail->rate, 2) }}</td>

<td class="amount">{{ number_format($detail->waiting_charge ?? 0, 2) }}</td>

<td class="amount">{{ number_format($invoice->total_amount, 2) }}</td>

</tr>

</tbody>

</table>

<!-- =======================================================
     TOTALS
======================================================= -->

<table>

<tr>

    <td class="label-blue">Actual Amount</td>

    <td class="amount">{{ number_format($invoice->total_amount, 2) }}</td>

</tr>

<tr>

    <td class="label-blue">Discount</td>

    <td class="amount">{{ number_format($invoice->discount, 2) }}</td>

</tr>

@if($invoice->discount > 0 && $approverName)

<tr>

    <td class="label-blue">Discount Authorised By</td>

    <td>{{ $approverName }}</td>

</tr>

@endif

<tr>

    <td class="label-blue">Received Amount</td>

    <td class="amount">{{ number_format($invoice->paid_amount, 2) }}</td>

</tr>

<tr>

    <td class="label-blue">Due Amount</td>

    <td class="amount">{{ number_format($invoice->due_amount, 2) }}</td>

</tr>

<tr>

    <td class="label-blue">Received By</td>

    <td>{{ $receivedByName ?? '' }}</td>

</tr>

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
                    Rs. {{ number_format($invoice->paid_amount, 2) }}
                    ({{ $amountInWords }} Only)
                </b>
                towards ambulance rental charges.

                <br>

                Received By :
                <b>{{ $receivedByName ?? optional(Auth::user())->name }}</b>

        </td>

    </tr>

</table>

@if($invoice->remarks)

<table>

<tr>

<td>

<b>Remarks :</b> {{ $invoice->remarks }}

</td>

</tr>

</table>

@endif

<br><br>

<!-- =======================================================
     SIGNATURES
======================================================= -->

<table border="0" style="border:none; margin:0;">

<tr>

<td class="no-border text-left">

_____________________

<br>

Authorized Signature

</td>

</tr>

</table>

</body>

</html>
