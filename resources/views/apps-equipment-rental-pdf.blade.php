< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Equipment Rental Invoice
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

@include('partials.pdf-header', [
    'reportTitle' => 'EQUIPMENT RENTAL ' . ($isFinal ? 'FINAL SETTLEMENT INVOICE' : 'ADVANCE RECEIPT'),
    'headerColor' => '#003399',
])

<!-- =======================================================
     INVOICE INFORMATION
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
    <td class="label-blue">Age</td>
    <td>{{ $invoice->patient_age }}</td>

    <td class="label-blue">Gender</td>
    <td>{{ $invoice->patient_gender }}</td>
</tr>

<tr>
    <td class="label-blue">Mobile</td>
    <td>{{ $invoice->patient_mobile_no }}</td>

    <td class="label-blue">Payment Mode</td>
    <td>{{ $invoice->payment_mode }}</td>
</tr>

@if($isFinal)
<tr>
    <td class="label-blue">Advance Receipt No</td>
    <td>{{ $invoice->reference_no }}</td>

    <td class="label-blue">Days Rented</td>
    <td>{{ $detail->rental_days }}</td>
</tr>
@if($invoice->invoice_type === 'OXYGEN_RENT')
<tr>
    <td class="label-blue">Units (kg) Consumed</td>
    <td colspan="3">{{ $detail->oxygen_units_consumed }}</td>
</tr>
@endif
@endif

</table>

<br>

<!-- =======================================================
     RENTAL DETAILS
======================================================= -->

<table>

<thead>

<tr>

<th width="10%">SL</th>

<th width="15%">Type</th>

<th width="35%">Equipment Category</th>

<th width="10%">Qty</th>

<th width="15%">Rate</th>

<th width="15%">Amount</th>

</tr>

</thead>

<tbody>

<tr>

<td class="text-center">1</td>

<td>{{ $invoice->invoice_type === 'OXYGEN_RENT' ? 'Oxygen Cylinder' : 'Concentrator' }}</td>

<td>{{ $detail->item_description }}</td>

<td class="text-center">{{ $detail->quantity }}</td>

<td class="amount">{{ number_format($detail->rate,2) }}</td>

<td class="amount">{{ number_format($detail->amount,2) }}</td>

</tr>

</tbody>

</table>

<!-- =======================================================
     TOTALS
======================================================= -->

<table>

@if($isFinal && $invoice->invoice_type === 'OXYGEN_RENT')

@php
    $rentalCharge = round($detail->rate * $detail->rental_days, 2);
    $consumptionCharge = round($invoice->total_amount - $rentalCharge, 2);
@endphp

<tr>

    <td class="label-blue">
        Rental Charge for {{ $detail->rental_days }} Days
    </td>

    <td class="amount">
        {{ number_format($rentalCharge,2) }}
    </td>

</tr>

<tr>

    <td class="label-blue">
        Consumption Charge for {{ $detail->oxygen_units_consumed }} Units
    </td>

    <td class="amount">
        {{ number_format($consumptionCharge,2) }}
    </td>

</tr>

@elseif($isFinal)

<tr>

    <td class="label-blue">
        Rental Charge for {{ $detail->rental_days }} Days
    </td>

    <td class="amount">
        {{ number_format($invoice->total_amount,2) }}
    </td>

</tr>

@else

<tr>

    <td class="label-blue">
        Gross Advance Amount
    </td>

    <td class="amount">
        {{ number_format($invoice->total_amount,2) }}
    </td>

</tr>

@endif

<tr>

    <td class="label-blue">
        Discount
    </td>

    <td class="amount">
        {{ number_format($invoice->discount,2) }}
    </td>

</tr>

<tr>

    <td class="label-blue">
        Net Payable
    </td>

    <td class="amount">
        {{ number_format($invoice->paid_amount,2) }}
    </td>

</tr>

@if($invoice->discount > 0 && $approverName)

<tr>

    <td class="label-blue">
        Discount Authorised By
    </td>

    <td>
        {{ $approverName }}
    </td>

</tr>

@endif

@if($isFinal)

<tr>

    <td class="label-blue">
        Advance Already Paid
    </td>

    <td class="amount">
        {{ number_format($advanceAmount,2) }}
    </td>

</tr>

<tr>

    <td class="label-blue">
        {{ $settlement >= 0 ? 'Balance Collected' : 'Balance Refunded' }}
    </td>

    <td class="amount">
        {{ number_format(abs($settlement),2) }}
    </td>

</tr>

@endif

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
                towards equipment rental charges.

                <br>

                Received By :
                <b>{{ optional(Auth::user())->name }}</b>

        </td>

    </tr>

</table>

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
