< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Money Receipt
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
                        margin-top: 60px;
                    }

                    .signature {
                        width: 45%;
                        text-align: center;
                        display: inline-block;
                    }
                </style>

        </head>

        <body>

@include('partials.pdf-header', ['reportTitle' => 'MONEY RECEIPT', 'headerColor' => '#003399'])

<!-- =======================================================
     RECEIPT INFORMATION
======================================================= -->

<table>

<tr>
    <td width="25%" class="label-blue">Invoice No</td>
    <td>{{ $invoice->invoice_no }}</td>

    <td width="25%" class="label-blue">Date</td>
    <td>{{ fmtDate($invoice->invoice_date) }}</td>
</tr>

<tr>
    <td class="label-blue">Reference No</td>
    <td>{{ optional($detail)->reference_number ?? '-' }}</td>

    <td class="label-blue">Payment Mode</td>
    <td>{{ $invoice->payment_mode }}</td>
</tr>

<tr>
    <td class="label-blue">Income from Other Source Category</td>
    <td>{{ optional($category)->description ?? '-' }}</td>

    <td class="label-blue">Received From</td>
    <td>{{ $invoice->patient_name ?? '-' }}</td>
</tr>

@if($invoice->payment_mode === 'Cheque')

<tr>
    <td class="label-blue">Cheque No</td>
    <td>{{ optional($detail)->cheque_number ?? '-' }}</td>

    <td class="label-blue">Cheque Date</td>
    <td>{{ fmtDate(optional($detail)->cheque_date) }}</td>
</tr>

<tr>
    <td class="label-blue">Bank Name</td>
    <td colspan="3">{{ optional($detail)->bank_name ?? '-' }}</td>
</tr>

@endif

<tr>
    <td class="label-blue">Received By</td>
    <td colspan="3">{{ $receivedByName ?? '-' }}</td>
</tr>

</table>

<br>

<!-- =======================================================
     AMOUNT
======================================================= -->

@php

$f = new \NumberFormatter(
    "en",
    \NumberFormatter::SPELLOUT
);

$amountInWords = ucwords(
    $f->format($invoice->total_amount)
);

@endphp

<table>

<tr>

    <td class="label-blue" width="25%">Amount</td>

    <td class="amount">Rs. {{ number_format($invoice->total_amount, 2) }}</td>

</tr>

<tr>

    <td class="label-blue">Amount in Words</td>

    <td>{{ $amountInWords }} Only</td>

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

<div class="signature-section">

<table border="0" style="border:none; margin:0;">

<tr>

<td class="no-border text-center" style="width:45%;">

_____________________

<br>

Received By

</td>

<td class="no-border" style="width:10%;"></td>

<td class="no-border text-center" style="width:45%;">

_____________________

<br>

Authorized Signature

</td>

</tr>

</table>

</div>

</body>

</html>
