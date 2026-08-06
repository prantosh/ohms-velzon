< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Expenditure Voucher
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
                        color: #000;
                        font-weight: bold;
                    }

                    table thead th {
                        color: #000;
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

@include('partials.pdf-header', ['reportTitle' => 'EXPENDITURE VOUCHER'])

<!-- =======================================================
     VOUCHER INFORMATION
======================================================= -->

<table>

<tr>
    <td width="25%" class="label-blue">Voucher No</td>
    <td>{{ $transaction->voucher_number }}</td>

    <td width="25%" class="label-blue">Date</td>
    <td>{{ fmtDate($transaction->transaction_date) }}</td>
</tr>

<tr>
    <td class="label-blue">Bill No</td>
    <td>{{ $transaction->bill_number ?? '-' }}</td>

    <td class="label-blue">Payment Mode</td>
    <td>{{ $transaction->payment_mode }}</td>
</tr>

<tr>
    <td class="label-blue">Expenditure Category</td>
    <td>{{ optional($transaction->category)->description }}</td>

    <td class="label-blue">Paid To (Agency)</td>
    <td>{{ optional($transaction->agency)->description }}</td>
</tr>

@if($transaction->payment_mode === 'Cheque')

<tr>
    <td class="label-blue">Cheque No</td>
    <td>{{ $transaction->cheque_number ?? '-' }}</td>

    <td class="label-blue">Cheque Date</td>
    <td>{{ fmtDate($transaction->cheque_date) }}</td>
</tr>

<tr>
    <td class="label-blue">Bank Name</td>
    <td colspan="3">{{ $transaction->bank_name ?? '-' }}</td>
</tr>

@endif

<tr>
    <td class="label-blue">Expenditure By</td>
    <td colspan="3">{{ optional($transaction->expenditureByUser)->name }}</td>
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
    $f->format($transaction->amount)
);

@endphp

<table>

<tr>

    <td class="label-blue" width="25%">Amount</td>

    <td class="amount">Rs. {{ number_format($transaction->amount, 2) }}</td>

</tr>

<tr>

    <td class="label-blue">Amount in Words</td>

    <td>{{ $amountInWords }} Only</td>

</tr>

<tr>

    <td class="label-blue">Prepared By</td>

    <td>{{ $preparedByName ?? '-' }}</td>

</tr>

</table>

@if($transaction->remarks)

<table>

<tr>

<td>

<b>Remarks :</b> {{ $transaction->remarks }}

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

Prepared By

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
