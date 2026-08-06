< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Membership Fee Invoice
                </title>
@php

function fmtDate($date)
{
    return empty($date)
        ? ''
        : \Carbon\Carbon::parse($date)->format('d-m-Y');
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

@include('partials.pdf-header', ['reportTitle' => 'MEMBERSHIP FEE INVOICE', 'headerColor' => '#003399'])

<!-- =======================================================
     INVOICE / MEMBER INFORMATION
======================================================= -->

<table>

<tr>
    <td width="25%" class="label-blue">Invoice No</td>
    <td>{{ $invoice->invoice_no }}</td>

    <td width="25%" class="label-blue">Date of Payment</td>
    <td>{{ fmtDate($invoice->invoice_date) }}</td>
</tr>

<tr>
    <td class="label-blue">Member ID</td>
    <td>{{ $invoice->patient_id }}</td>

    <td class="label-blue">Member Name</td>
    <td>{{ $invoice->patient_name }}</td>
</tr>

<tr>
    <td class="label-blue">Mobile</td>
    <td>{{ $invoice->patient_mobile_no }}</td>

    <td class="label-blue">Payment Mode</td>
    <td>{{ $invoice->payment_mode }}</td>
</tr>

</table>

<br>

<!-- =======================================================
     MONTHS PAID
======================================================= -->

<table>

<thead>

<tr>

<th width="10%">Sl No</th>

<th width="60%">Month</th>

<th width="30%">Amount</th>

</tr>

</thead>

<tbody>

@foreach($months as $index => $month)

<tr>

<td class="text-center">{{ $index + 1 }}</td>

<td>Membership Fee - {{ \Carbon\Carbon::parse($month->payment_month)->format('F Y') }}</td>

<td class="amount">{{ number_format($month->amount, 2) }}</td>

</tr>

@endforeach

</tbody>

</table>

<!-- =======================================================
     TOTALS
======================================================= -->

<table>

<tr>

    <td class="label-blue">Total Amount</td>

    <td class="amount">{{ number_format($invoice->total_amount, 2) }}</td>

</tr>

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
                towards membership fee for {{ $months->count() }} month(s).

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
