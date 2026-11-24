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

// Modified Invoice: issued when item(s) of an earlier invoice were cancelled.
$isModified = !empty($invoice->is_modified_invoice);
$invoiceTitle = $isModified ? 'MODIFIED INVOICE' : 'INVOICE';
$cancelledItems = $isModified
    ? \Illuminate\Support\Facades\DB::table('invoice_partial_cancellations')
        ->where('new_invoice_no', $invoice->invoice_no)->orderBy('id')->get()
    : collect();
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
                {{ $invoiceTitle }}            </div>
        </td>

    </tr>

    <tr>

        <td colspan="3" style="border:none; text-align:right; padding-top:1px; padding-bottom:0; font-size:9px; color:#555;">
            @if($isModified)
            <table style="width:100%; margin:0; border:none;">
                <tr>
                    <td style="width:30%; border:none; padding:0;"></td>
                    <td style="width:40%; border:none; padding:0; text-align:center; color:#000;">Supersedes {{ $invoice->parent_invoice_no }}</td>
                    <td style="width:30%; border:none; padding:0; text-align:right; color:#555;">Printed On: {{ now()->format('d-m-Y h:i A') }}</td>
                </tr>
            </table>
            @else
            Printed On: {{ now()->format('d-m-Y h:i A') }}
            @endif
        </td>

    </tr>

</table>
@else
@include('partials.pdf-header', [
    'reportTitle' => $invoiceTitle,
    'reportSubtitle' => $isModified ? 'Supersedes ' . $invoice->parent_invoice_no : null,
    'headerColor' => '#003399',
])
@endif



<!-- =======================================================
     INVOICE INFORMATION
======================================================= -->

<table>

<tr>
    <td width="25%" class="label-blue">Patient Name</td>
    <td><strong>{{ $invoice->patient_name }}</strong></td>

    <td width="25%" class="label-blue">Invoice No</td>
    <td>{{ $invoice->invoice_no }}</td>
</tr>

<tr>
    <td class="label-blue">Gender</td>
    <td>{{ $invoice->patient_gender }}</td>

    <td class="label-blue">Invoice Date</td>
    <td>{{ fmtDate($invoice->invoice_date) }}</td>
</tr>

<tr>
    <td class="label-blue">Age</td>
    <td>{{ $invoice->patient_age }}</td>

    <td class="label-blue">Patient ID</td>
    <td>{{ $invoice->patient_id }}</td>
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
    <td>{{ !empty($testDateText) ? $testDateText : fmtDate($invoice->test_date) }}</td>
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

<th width="19%" style="white-space:nowrap;">Test Category</th>

<th width="46%">Test Description</th>

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

@if($isModified && $cancelledItems->isNotEmpty())
@php
    $cancelledTotal = $cancelledItems->sum('amount');
    $refundTotal = (float) ($cancelledItems->first()->refund_amount ?? 0);
@endphp
<div style="margin-top:8px; font-weight:bold; color:#b30000;">
    Cancelled Items (from {{ $invoice->parent_invoice_no }})
</div>
<table class="bordered-table">
<thead>
<tr>
<th width="5%">SL</th>
<th width="55%">Test Description</th>
<th width="15%">Rate</th>
<th width="25%" style="white-space:nowrap;">Cancelled Value</th>
</tr>
</thead>
<tbody>
@foreach($cancelledItems as $ci)
<tr>
<td class="text-center">{{ $loop->iteration }}</td>
<td style="text-decoration: line-through;">{{ $ci->item_description }}</td>
<td class="amount">{{ number_format($ci->rate, 2) }}</td>
<td class="amount">{{ number_format($ci->amount, 2) }}</td>
</tr>
@endforeach
<tr>
<td colspan="3" class="amount"><b>Total Cancelled</b></td>
<td class="amount"><b>{{ number_format($cancelledTotal, 2) }}</b></td>
</tr>
</tbody>
</table>
@endif



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
        {{ ($isModified && ($refundTotal ?? 0) > 0) ? 'Amount to Refund' : 'Payable Amount' }}
    </td>

    <td class="amount">
        {{ number_format(($isModified && ($refundTotal ?? 0) > 0) ? $refundTotal : $invoice->total_amount,2) }}
    </td>

    <td class="label-blue">
        Paid Amount
    </td>

    <td class="amount">
        {{ number_format($invoice->paid_amount ?? 0,2) }}
    </td>

</tr>
</table>

@php
// The LAST payment is already stated in the Receipt Acknowledgement below (amount and
// who received it), so the table lists only the EARLIER payments.
// Modified Invoice: the earlier payments are printed exactly as on the ORIGINAL invoice
// (walk back to the root of the chain); refunds given at partial-cancellation time are
// listed separately under the name of the employee who gave them.
$pay = $invoice;
$refundTxns = collect();
if ($isModified) {
    $chain = [];
    $cur = $invoice;
    $guard = 0;
    while ($cur && !empty($cur->parent_invoice_no) && $guard++ < 10) {
        $chain[] = $cur->parent_invoice_no;
        $cur = \Illuminate\Support\Facades\DB::table('invoices')->where('invoice_no', $cur->parent_invoice_no)->first();
    }
    if ($cur && $cur->invoice_no !== $invoice->invoice_no) {
        $pay = $cur;
    }
    $refundTxns = \Illuminate\Support\Facades\DB::table('daily_transactions')
        ->whereIn('invoice_reference', $chain)
        ->where('transaction_type', 'REFUND')
        ->orderBy('id')->get();
}
// amount collected on the spot at partial-cancellation time (recorded against the new invoice)
$collectTxns = $isModified
    ? \Illuminate\Support\Facades\DB::table('daily_transactions')
        ->where('invoice_reference', $invoice->invoice_no)
        ->where('transaction_type', 'RECEIVED')
        ->orderBy('id')->get()
    : collect();
$paymentCount = 1 + (($pay->paid_amount_1 ?? 0) > 0 ? 1 : 0) + (($pay->paid_amount_2 ?? 0) > 0 ? 1 : 0);
// With a collection taken at cancellation, ALL original payments go in the table and the
// collection becomes the "last payment" of the Receipt Acknowledgement (like any multi-phase invoice).
$rootLastInAck = $collectTxns->isEmpty();
$showPayTable = $paymentCount > 1 || $refundTxns->isNotEmpty() || $collectTxns->isNotEmpty();
@endphp

<table class="bordered-table">
@if($showPayTable)
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
        Received By
    </td>

    <td class="label-blue">
        Due after Payment
    </td>

</tr>
@endif

@php

$firstPaidAmount =
    ($pay->paid_amount ?? 0)
    - ($pay->paid_amount_1 ?? 0)
    - ($pay->paid_amount_2 ?? 0);

$cumulativeAfterFirst = $firstPaidAmount;

$cumulativeAfterPhase1 = $firstPaidAmount + ($pay->paid_amount_1 ?? 0);

$cumulativeAfterPhase2 = $firstPaidAmount + ($pay->paid_amount_1 ?? 0) + ($pay->paid_amount_2 ?? 0);

// Each payment (initial + up to 2 due-payment phases) can be collected in
// a different mode, but invoices.payment_mode only stores the LATEST
// one -- daily_transactions has one row per payment event and is the only
// place the mode for each individual phase actually survives.
$initialPaymentTxn = \Illuminate\Support\Facades\DB::table('daily_transactions')
    ->where('invoice_reference', $pay->invoice_no)
    ->where('remarks', 'Diagnostic Invoice')
    ->first();

$phase1PaymentTxn = \Illuminate\Support\Facades\DB::table('daily_transactions')
    ->where('invoice_reference', $pay->invoice_no)
    ->where('remarks', 'like', 'Diagnostic Due Payment - Phase 1%')
    ->first();

$phase2PaymentTxn = \Illuminate\Support\Facades\DB::table('daily_transactions')
    ->where('invoice_reference', $pay->invoice_no)
    ->where('remarks', 'like', 'Diagnostic Due Payment - Phase 2%')
    ->first();

$initialPaymentMode = $initialPaymentTxn->payment_mode ?? $pay->payment_mode ?? '-';
$phase1PaymentMode = $phase1PaymentTxn->payment_mode ?? '-';
$phase2PaymentMode = $phase2PaymentTxn->payment_mode ?? '-';

// Who received each payment: the cashier recorded on that payment's own
// transaction. The initial payment falls back to whoever created the invoice
// (older invoices without a transaction row); due payments have no other record.
$initialReceivedBy = $initialPaymentTxn->operator_name
    ?? \Illuminate\Support\Facades\DB::table('users')->where('id', $pay->created_by)->value('name')
    ?? '-';
$phase1ReceivedBy = $phase1PaymentTxn->operator_name ?? '-';
$phase2ReceivedBy = $phase2PaymentTxn->operator_name ?? '-';

@endphp

@if($paymentCount > 1 || !$rootLastInAck)
<tr>

    <td>
        {{ fmtDateTime($initialPaymentTxn->created_at ?? $pay->created_at) }}
    </td>

    <td class="text-end">
        {{ number_format($firstPaidAmount,2) }}
    </td>

    <td>
        {{ $initialPaymentMode }}
    </td>

    <td>
        {{ $initialReceivedBy }}
    </td>

    <td class="text-end">
        {{ number_format(
            $pay->total_amount
            - $cumulativeAfterFirst,
            2
        ) }}
    </td>

</tr>
@endif

@if($pay->paid_amount_1 > 0 && ($pay->paid_amount_2 > 0 || !$rootLastInAck))

<tr>

    <td>
        {{ fmtDateTime($pay->paid_date_1) }}
    </td>

    <td class="text-end">
        {{ number_format($pay->paid_amount_1,2) }}
    </td>

    <td>
        {{ $phase1PaymentMode }}
    </td>

    <td>
        {{ $phase1ReceivedBy }}
    </td>

    <td class="text-end">
        {{ number_format(
            $pay->total_amount
            - $cumulativeAfterPhase1,
            2
        ) }}
    </td>

</tr>

@endif

{{-- the 2nd due payment is always the last payment: shown in the Receipt Acknowledgement below --}}

@if(!$rootLastInAck && $pay->paid_amount_2 > 0)
<tr>
    <td>{{ fmtDateTime($pay->paid_date_2) }}</td>
    <td class="text-end">{{ number_format($pay->paid_amount_2,2) }}</td>
    <td>{{ $phase2PaymentMode }}</td>
    <td>{{ $phase2ReceivedBy }}</td>
    <td class="text-end">{{ number_format($pay->total_amount - $cumulativeAfterPhase2, 2) }}</td>
</tr>
@endif

@foreach($collectTxns->slice(0, -1) as $ct)
<tr>
    <td>{{ fmtDateTime($ct->created_at) }}</td>
    <td class="text-end">{{ number_format($ct->received_amount, 2) }} (Collected)</td>
    <td>{{ $ct->payment_mode ?? '-' }}</td>
    <td>{{ $ct->operator_name ?? '-' }}</td>
    <td class="text-end">{{ number_format($invoice->due_amount ?? 0, 2) }}</td>
</tr>
@endforeach

@foreach($refundTxns as $rf)
<tr>
    <td>{{ fmtDateTime($rf->created_at) }}</td>
    <td class="text-end">-{{ number_format($rf->refund_amount, 2) }} (Refund)</td>
    <td>{{ $rf->payment_mode ?? '-' }}</td>
    <td>{{ $rf->operator_name ?? '-' }}</td>
    <td class="text-end">{{ number_format($invoice->due_amount ?? 0, 2) }}</td>
</tr>
@endforeach

<tr>

    <td colspan="5" class="label-blue">

        Payment Status :

        <span style="color:#000000;">
            {{ $invoice->status }}
        </span>

        &nbsp;&nbsp;&nbsp;&nbsp;

        <span class="label-blue">Due Amount :</span>

        <span style="color:#000000;">
            Rs. {{ number_format($invoice->due_amount ?? 0, 2) }}
        </span>

    </td>

</tr>
                        
</table>


@php

$lastPayment = $pay->paid_amount;
$lastPaymentDate = $pay->created_at;

// Who actually collected this money -- the operator recorded on that
// payment's own daily_transactions row -- not whoever happens to be
// logged in when the invoice is later (re)printed.
$fallbackReceiver = \Illuminate\Support\Facades\DB::table('users')
    ->where('id', $pay->created_by)
    ->value('name') ?? '-';

$lastPaymentOperator = optional($initialPaymentTxn)->operator_name ?? $fallbackReceiver;
$lastPaymentAt = optional($initialPaymentTxn)->created_at ?? $pay->created_at;

if (!empty($pay->paid_amount_2) && $pay->paid_amount_2 > 0) {

    $lastPayment = $pay->paid_amount_2;
    $lastPaymentDate = $pay->paid_date_2;
    $lastPaymentOperator = optional($phase2PaymentTxn)->operator_name ?? $fallbackReceiver;
    $lastPaymentAt = $pay->paid_date_2 ?: (optional($phase2PaymentTxn)->created_at ?? $lastPaymentDate);

} elseif (!empty($pay->paid_amount_1) && $pay->paid_amount_1 > 0) {

    $lastPayment = $pay->paid_amount_1;
    $lastPaymentDate = $pay->paid_date_1;
    $lastPaymentOperator = optional($phase1PaymentTxn)->operator_name ?? $fallbackReceiver;
    $lastPaymentAt = $pay->paid_date_1 ?: (optional($phase1PaymentTxn)->created_at ?? $lastPaymentDate);
}

/*
|--------------------------------------------------------------------------
| Amount To Words
|--------------------------------------------------------------------------
*/

if ($collectTxns->isNotEmpty()) {
    $lastCt = $collectTxns->last();
    $lastPayment = $lastCt->received_amount;
    $lastPaymentOperator = $lastCt->operator_name ?? '-';
    $lastPaymentAt = $lastCt->created_at;
}

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

                &nbsp;&nbsp;&nbsp;&nbsp;

                Receipt Date &amp; Time :
                <b>{{ fmtDateTime($lastPaymentAt) }}</b>

                @foreach($refundTxns as $rf)
                    <br>
                    Refunded <b>Rs. {{ number_format($rf->refund_amount, 2) }}</b>
                    By : <b>{{ $rf->operator_name ?? '-' }}</b>
                    &nbsp;&nbsp;&nbsp;&nbsp;
                    Refund Date &amp; Time : <b>{{ fmtDateTime($rf->created_at) }}</b>
                @endforeach

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
