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

// Short invoices (6 or fewer billable tests) print on ordinary A4 paper
// like every other invoice -- only the content is compacted so it
// naturally lands within the top half of the page (html content height
// doesn't depend on the page's total height), leaving the rest blank.
// See DiagnosticInvoiceController's paper-size decision, which counts
// the same $tests collection this view renders. Defaults to STANDARD so
// any other caller that doesn't pass $paperSize keeps the original
// (uncompacted) layout.
$isShort = ($paperSize ?? 'STANDARD') === 'SHORT';
@endphp
                <style>

                    @page {
                            margin-top: {{ $isShort ? '8mm' : '1in' }};
                            margin-right: {{ $isShort ? '8mm' : '40px' }};
                            margin-bottom: {{ $isShort ? '8mm' : '40px' }};
                            margin-left: {{ $isShort ? '8mm' : '40px' }};
                        }

                    @page :first {
                            margin-top: {{ $isShort ? '4mm' : '0.4in' }};
                        }

                    body {
                        font-family: DejaVu Sans, sans-serif;
                        font-size: {{ $isShort ? '10px' : '12px' }};
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
                        margin-top: {{ $isShort ? '3px' : '10px' }};
                    }

                        table th,
                        table td {
                            border: none;
                            padding: {{ $isShort ? '1px 3px' : '6px' }};
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
                        margin-top: {{ $isShort ? '9px' : '50px' }};
                    }

                    .signature {
                        width: 45%;
                        text-align: center;
                        display: inline-block;
                    }
                </style>

        </head>

        <body>

@if($isShort)
{{-- Header taken as-is from apps-doctor-visit-invoice-pdf.blade.php,
     including its own badge/text sizing -- kept clear of the shared
     partials.pdf-header (used by 42 other reports, and still used by
     this same view's STANDARD/>6-test branch below). --}}

<div style="position: fixed; top: 90px; left: 0; right: 0; text-align: center; opacity: 0.08;">
    <img src="{{ public_path('images/abssrk_logo.png') }}" style="width: 380px;">
</div>

<table style="border:none; margin:0; width:100%;">

    <tr>

        <td style="width:15%; border:none; text-align:left; vertical-align:middle;">
            <img src="{{ public_path('images/iso.jpg') }}" style="height:50px;">
        </td>

        <td style="width:70%; border:none; text-align:center; vertical-align:middle;">

            <div style="font-size:14px; font-weight:bold; color:#003399; white-space:nowrap;">
                Dr. Amitava Basu Smriti Swastha Raksha Kendra
            </div>

            <div style="font-size:13px; font-weight:bold; color:#003399; margin-top:2px;">
                Srayan Apartment, 19, M B Road, Kolkata - 700049
            </div>

            <div style="font-size:10px; margin-top:2px;">
                Web: www.abssrk.online; Phone: (033)2513-7070/7439, 2539-2009
                Mob: 8585882287/9051132429/9051129713/9038721959
            </div>

        </td>

        <td style="width:15%; border:none; text-align:right; vertical-align:middle;">
            <img src="{{ public_path('images/nabl.jpg') }}" style="height:50px;">
        </td>

    </tr>

    <tr>

        <td colspan="3" style="border:none; text-align:center; padding-top:3px; padding-bottom:0;">
            <div style="font-size:16px; font-weight:bold; color:#003399;">
                INVOICE
            </div>
        </td>

    </tr>

    <tr>

        <td colspan="3" style="border:none; text-align:right; padding-top:1px; padding-bottom:0; font-size:9px; color:#555;">
            Printed On: {{ now()->format('d-m-Y h:i A') }}
        </td>

    </tr>

</table>
@else
@include('partials.pdf-header', [
    'reportTitle' => 'INVOICE',
    'headerColor' => '#003399',
])
@endif



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

<div style="height: {{ $isShort ? '2px' : '10px' }};"></div>

<!-- =======================================================
     TEST DETAILS
======================================================= -->

<table class="bordered-table">

<thead>

<tr>

<th width="5%">SL</th>

<th width="15%">Test Category</th>

<th width="50%">Test Description</th>

<th width="10%">Rate</th>

<th width="10%">Discount</th>

<th width="10%">Amount</th>

</tr>

</thead>

<tbody>

@php

$sl = 1;

@endphp

@foreach($tests as $row)

<tr>

<td class="text-center">{{ $sl++ }}</td>

<td>{{ $row->test_category }}</td>

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



Please bring this slip for report collection

</td>

</tr>
<tr>

<td>

<b>Remarks :</b>

Report can be obtained from Monday to Sunday after 7 P.M.

</td>

</tr>

</table>

<div style="height: {{ $isShort ? '3px' : '20px' }};"></div>

<!-- =======================================================
     SIGNATURES
======================================================= -->

<table border="0" style="border:none; margin:0;">

<tr>



<td class="no-border text-right">

_____________________

<br>

Authorized Signature

</td>

</tr>

</table>

</body>

</html>
