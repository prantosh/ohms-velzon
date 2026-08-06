< !DOCTYPE html >
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
                        border: none;
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
                            border: none;
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

                    /* Bordered Tables (Test Details / Payment History) */

                    .bordered-table th,
                    .bordered-table td {
                        border: 1px solid #000;
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

@include('partials.pdf-header', ['reportTitle' => 'INVOICE', 'headerColor' => '#003399'])



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

<tr>
    <td class="label-blue">Referred Doctor</td>
    <td>{{ $invoice->referred_doctor }}</td>

    <td class="label-blue">Test Date</td>
    <td>{{ fmtDate($invoice->test_date) }}</td>
</tr>
</table>

<br>

<!-- =======================================================
     TEST DETAILS
======================================================= -->

<table class="bordered-table">

<thead>

<tr>

<th width="5%">SL</th>

<th width="15%">Test ID</th>

<th width="30%">Test Description</th>

<th width="10%">Rate</th>

<th width="10%">Discount</th>

<th width="10%">Amount</th>

<th width="20%">Remarks</th>

</tr>

</thead>

<tbody>

@php

$sl = 1;

@endphp

@foreach($tests as $row)

<tr>

<td class="text-center">{{ $sl++ }}</td>

<td>{{ $row->item_code_sub }}</td>

<td>{{ $row->test_name }}</td>

<td class="amount">
    {{ number_format($row->rate,2) }}
</td>

<td class="amount">
    {{ number_format($row->discount,2) }}
</td>

<td class="amount">
    {{ number_format($row->amount,2) }}
</td>

<td>
    @if($row->doctor_payment_waived)
    Doctor Fees Waived{{ $row->remarks ? ' - ' . $row->remarks : '' }}
    @else
    {{ $row->remarks }}
    @endif
</td>

</tr>

@endforeach

</tbody>

</table>



<!-- =======================================================
     TOTALS
======================================================= -->

<table>

<tr>

    <td class="label-blue">
        Gross Amount
    </td>

    <td class="amount">
        {{ number_format($invoice->total_amount + $invoice->discount,2) }}
    </td>

    <td class="label-blue">
        Discount
    </td>

    <td class="amount">
        {{ number_format($invoice->discount,2) }}
    </td>

    <td class="label-blue">
        Payable Amount
    </td>

    <td class="amount">
        {{ number_format($invoice->total_amount,2) }}
    </td>

</tr>
</table>

<table class="bordered-table">
<tr>
    <td class="label-blue">
        Date of Payment
    </td>

    <td class="label-blue">
        Amount Paid
    </td>

    <td class="label-blue">
        Mode of Payment
    </td>

    <td class="label-blue">
        Due after Payment
    </td>

</tr>

@php

$firstPaidAmount =
    ($invoice->paid_amount ?? 0)
    - ($invoice->paid_amount_1 ?? 0)
    - ($invoice->paid_amount_2 ?? 0);

$cumulativeAfterFirst = $firstPaidAmount;

$cumulativeAfterPhase1 = $firstPaidAmount + ($invoice->paid_amount_1 ?? 0);

$cumulativeAfterPhase2 = $firstPaidAmount + ($invoice->paid_amount_1 ?? 0) + ($invoice->paid_amount_2 ?? 0);

// Each payment (initial + up to 2 due-payment phases) can be collected in
// a different mode, but invoices.payment_mode only stores the LATEST
// one -- daily_transactions has one row per payment event and is the only
// place the mode for each individual phase actually survives.
$initialPaymentTxn = \Illuminate\Support\Facades\DB::table('daily_transactions')
    ->where('invoice_reference', $invoice->invoice_no)
    ->where('remarks', 'Diagnostic Invoice')
    ->first();

$phase1PaymentTxn = \Illuminate\Support\Facades\DB::table('daily_transactions')
    ->where('invoice_reference', $invoice->invoice_no)
    ->where('remarks', 'like', 'Diagnostic Due Payment - Phase 1%')
    ->first();

$phase2PaymentTxn = \Illuminate\Support\Facades\DB::table('daily_transactions')
    ->where('invoice_reference', $invoice->invoice_no)
    ->where('remarks', 'like', 'Diagnostic Due Payment - Phase 2%')
    ->first();

$initialPaymentMode = $initialPaymentTxn->payment_mode ?? $invoice->payment_mode ?? '-';
$phase1PaymentMode = $phase1PaymentTxn->payment_mode ?? '-';
$phase2PaymentMode = $phase2PaymentTxn->payment_mode ?? '-';

@endphp

<tr>

    <td>
        {{ fmtDateTime($initialPaymentTxn->created_at ?? $invoice->created_at) }}
    </td>

    <td class="text-end">
        {{ number_format($firstPaidAmount,2) }}
    </td>

    <td>
        {{ $initialPaymentMode }}
    </td>

    <td class="text-end">
        {{ number_format(
            $invoice->total_amount
            - $cumulativeAfterFirst,
            2
        ) }}
    </td>

</tr>

@if($invoice->paid_amount_1 > 0)

<tr>

    <td>
        {{ fmtDateTime($invoice->paid_date_1) }}
    </td>

    <td class="text-end">
        {{ number_format($invoice->paid_amount_1,2) }}
    </td>

    <td>
        {{ $phase1PaymentMode }}
    </td>

    <td class="text-end">
        {{ number_format(
            $invoice->total_amount
            - $cumulativeAfterPhase1,
            2
        ) }}
    </td>

</tr>

@endif

@if($invoice->paid_amount_2 > 0)

<tr>

    <td>
        {{ fmtDateTime($invoice->paid_date_2) }}
    </td>

    <td class="text-end">
        {{ number_format($invoice->paid_amount_2,2) }}
    </td>

    <td>
        {{ $phase2PaymentMode }}
    </td>

    <td class="text-end">
        {{ number_format(
            $invoice->total_amount
            - $cumulativeAfterPhase2,
            2
        ) }}
    </td>

</tr>

@endif

<tr>

    <td colspan="4" class="label-blue">

        Payment Status :

        <span style="color:#000000;">
            {{ $invoice->status }}
        </span>

    </td>

</tr>
                        
</table>


@php

$lastPayment = $invoice->paid_amount;
$lastPaymentDate = $invoice->created_at;

// Who actually collected this money -- the operator recorded on that
// payment's own daily_transactions row -- not whoever happens to be
// logged in when the invoice is later (re)printed.
$fallbackReceiver = \Illuminate\Support\Facades\DB::table('users')
    ->where('id', $invoice->created_by)
    ->value('name') ?? '-';

$lastPaymentOperator = optional($initialPaymentTxn)->operator_name ?? $fallbackReceiver;

if (!empty($invoice->paid_amount_2) && $invoice->paid_amount_2 > 0) {

    $lastPayment = $invoice->paid_amount_2;
    $lastPaymentDate = $invoice->paid_date_2;
    $lastPaymentOperator = optional($phase2PaymentTxn)->operator_name ?? $fallbackReceiver;

} elseif (!empty($invoice->paid_amount_1) && $invoice->paid_amount_1 > 0) {

    $lastPayment = $invoice->paid_amount_1;
    $lastPaymentDate = $invoice->paid_date_1;
    $lastPaymentOperator = optional($phase1PaymentTxn)->operator_name ?? $fallbackReceiver;
}

/*
|--------------------------------------------------------------------------
| Amount To Words
|--------------------------------------------------------------------------
*/

$f = new \NumberFormatter(
    "en",
    \NumberFormatter::SPELLOUT
);

$amountInWords = ucwords(
    $f->format($lastPayment)
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
                    Rs. {{ number_format($lastPayment,2) }}
                    ({{ $amountInWords }} Only)
                </b>
                towards diagnostic charges.

                <br>

                Received By :
                <b>{{ $lastPaymentOperator }}</b>

        </td>

    </tr>

</table>

<!-- =======================================================
     REMARKS
======================================================= -->

<table>

<tr>

<td>

<b>Remarks :</b>

Report can be obtained from Monday to Saturday after 7 P.M.

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
