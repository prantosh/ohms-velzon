< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Cash Submission Slip
                </title>

@php

function fmtMoney4($v)
{
    return number_format((float) ($v ?? 0), 2);
}

$netCashFormatter = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
// A deposit can never be negative: when payouts (refunds / doctor payments)
// plus any deficit carried in exceed the day's collections, the amount to
// deposit is 0 and the shortfall is shown as a deficit instead.
$netMovement = (float) ($summary['net_cash_to_deposit'] ?? 0);
$closingPosition = (float) ($summary['closing_position'] ?? $netMovement);
$depositAmount = max(0, $closingPosition);
$deficitAmount = $closingPosition < 0 ? abs($closingPosition) : 0;
$netCashInWords = ucwords($netCashFormatter->format($depositAmount));
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
                        font-size: 8.5px;
                        color: #000;
                        margin: 0;
                        padding: 0;
                    }

                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 6px;
                    }

                        table th,
                        table td {
                            border: 1px solid #000;
                            padding: 2.5px 4px;
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

                    .amt-positive {
                        color: #000;
                        font-weight: bold;
                    }

                    .amt-negative {
                        color: #000;
                        font-weight: bold;
                    }

                    .signature-section {
                        margin-top: 35px;
                    }
                </style>

        </head>

        <body>

@include('partials.pdf-header', ['reportTitle' => 'DAILY CASH SUBMISSION SLIP'])

<!-- =======================================================
     USER / DATE / SUMMARY
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">User</td>
    <td>{{ $user->name }} ({{ $user->role }})</td>

    <td width="25%" class="label-blue">Date</td>
    <td>{{ $date->format('d-m-Y (l)') }}</td>
</tr>

<tr>
    <td class="label-blue">Cash Collected</td>
    <td>Rs. {{ fmtMoney4($summary['cash_collected']) }}</td>

    <td class="label-blue">Non-Cash Collected</td>
    <td>Rs. {{ fmtMoney4($summary['non_cash_collected']) }}</td>
</tr>

<tr>
    <td class="label-blue">Cash Refunded</td>
    <td>Rs. {{ fmtMoney4($summary['cash_refunded']) }}</td>

    <td class="label-blue">Cash Payable/Paid To Doctors</td>
    <td>Rs. {{ fmtMoney4($summary['doctor_payable'] ?? 0) }} / {{ fmtMoney4($summary['cash_paid_to_doctors']) }}</td>
</tr>

<tr>
    <td class="label-blue">Net Cash To Deposit</td>
    <td><b>Rs. {{ fmtMoney4($depositAmount) }}</b> (Rupees {{ $netCashInWords }} Only)
        @if($deficitAmount > 0)
        <br><span style="color:#c0392b;">Nothing to deposit &ndash; see deficit below.</span>
        @endif
    </td>

    <td class="label-blue">Print Date/Time</td>
    <td>{{ now()->format('d-m-Y h:i A') }}</td>
</tr>

@if(($summary['adjustment_total'] ?? 0) > 0)
<tr>
    <td class="label-blue">Accounts Adjustment (Approved)</td>
    <td>+ Rs. {{ fmtMoney4($summary['adjustment_total']) }}</td>

    <td class="label-blue">Day's Cash Position</td>
    <td><b>Rs. {{ fmtMoney4($closingPosition) }}</b> (net cash + adjustment)</td>
</tr>
@endif

@if($deficitAmount > 0 || ($summary['pending_prior_deficit'] ?? 0) < 0)
<tr>
    <td class="label-blue">{{ $deficitAmount > 0 ? 'Cash Deficit (Today)' : 'Cash Deficit (Today)' }}</td>
    <td>
        @if($deficitAmount > 0)
        <b style="color:#c0392b;">Rs. {{ fmtMoney4($deficitAmount) }}</b>
        <br>Cash paid out exceeded cash collected. Nothing to deposit; the deficit stays pending until Admin posts an Accounts Adjustment. It is NOT recovered from the next day's collections.
        @else
        Nil
        @endif
    </td>

    <td class="label-blue">Earlier Pending Deficit</td>
    <td>
        @if(($summary['pending_prior_deficit'] ?? 0) < 0)
        <b style="color:#c0392b;">Rs. {{ fmtMoney4(abs($summary['pending_prior_deficit'])) }}</b>
        <br>Still unadjusted from earlier days (does not reduce today's deposit).
        @else
        Nil
        @endif
    </td>
</tr>
@endif

</table>

<!-- =======================================================
     GROUP-WISE CASH DEPOSIT BREAKDOWN
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="4" style="text-align:left; background-color:#e6e6e6;">
        Group-Wise Cash Deposit Breakdown
    </th>
</tr>
<tr>
    <th>Reporting Group</th>
    <th width="20%">{{ abs($netMovement - $depositAmount) > 0.004 ? 'Net Cash Amount' : 'Amount To Deposit' }}</th>
    <th>Reporting Group</th>
    <th width="20%">{{ abs($netMovement - $depositAmount) > 0.004 ? 'Net Cash Amount' : 'Amount To Deposit' }}</th>
</tr>
</thead>

<tbody>

@php
$groupPairs = collect($groupBreakdown)->chunk(2);
@endphp

@forelse($groupPairs as $pair)
<tr>
    @foreach($pair as $row)
    <td>{{ $row['group_name'] }}</td>
    <td class="text-end {{ $row['amount'] >= 0 ? 'amt-positive' : 'amt-negative' }}">{{ fmtMoney4($row['amount']) }}</td>
    @endforeach
    @if($pair->count() === 1)
    <td colspan="2"></td>
    @endif
</tr>
@empty
<tr>
    <td colspan="4" class="text-center">No group breakdown available.</td>
</tr>
@endforelse

<tr>
    <td class="label-blue">{{ abs($netMovement - $depositAmount) > 0.004 ? 'Total (Net Cash Movement)' : 'Total' }}</td>
    <td class="text-end" colspan="3"><b>Rs. {{ fmtMoney4($netMovement) }}</b></td>
</tr>

@if(abs($netMovement - $depositAmount) > 0.004)
@if(($summary['adjustment_total'] ?? 0) > 0)
<tr>
    <td class="label-blue">Accounts Adjustment (+)</td>
    <td class="text-end" colspan="3">Rs. {{ fmtMoney4($summary['adjustment_total']) }}</td>
</tr>
@endif
<tr>
    <td class="label-blue">Cash To Deposit</td>
    <td class="text-end" colspan="3"><b>Rs. {{ fmtMoney4($depositAmount) }}</b>
        @if($deficitAmount > 0)
        &nbsp;(deficit of Rs. {{ fmtMoney4($deficitAmount) }} pending adjustment)
        @endif
    </td>
</tr>
@endif

</tbody>

</table>

<!-- =======================================================
     LEDGER
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="10" style="text-align:left; background-color:#e6e6e6;">
        {{ $user->name }} &mdash; Daily Cash Submission Ledger for {{ $date->format('d-m-Y (l)') }}
    </th>
</tr>
<tr>
    <th width="12%">Invoice No</th>
    <th width="8%">Payment Status</th>
    <th width="11%">Category</th>
    <th width="8%">Txn No</th>
    <th width="15%">Txn From/To</th>
    <th width="7%">Time</th>
    <th width="9%">Amount</th>
    <th width="15%">Type</th>
    <th width="8%">Payment Mode</th>
    <th width="7%">Reference</th>
</tr>
</thead>

<tbody>

@forelse($ledger as $row)
<tr>
    <td>{{ $row['invoice_no'] }}</td>
    <td class="text-center">{{ $row['payment_status'] }}</td>
    <td>{{ $row['category'] }}</td>
    <td>{{ $row['transaction_no'] }}</td>
    <td>{{ $row['transaction_to'] }}</td>
    <td>{{ $row['time_fmt'] }}</td>
    <td class="text-end {{ $row['amount'] >= 0 ? 'amt-positive' : 'amt-negative' }}">{{ fmtMoney4($row['amount']) }}</td>
    <td>{{ $row['type'] }}</td>
    <td>{{ $row['type'] === 'Collection' ? $row['payment_mode'] : '' }}</td>
    <td>{{ $row['type'] === 'Collection' ? ($row['payment_reference'] ?? '') : '' }}</td>
</tr>
@empty
<tr>
    <td colspan="10" class="text-center">No cash transactions found for this date.</td>
</tr>
@endforelse

</tbody>

</table>

@if(!empty($adjustments))
<table style="margin-top:8px;">
<thead>
<tr>
    <th colspan="5" style="text-align:left; background-color:#e6e6e6;">Accounts Adjustments (cash handed back by Accounts / approved by Admin)</th>
</tr>
<tr>
    <th width="14%">Adj. No</th>
    <th width="14%">Amount</th>
    <th width="20%">Source</th>
    <th>Reference / Remarks</th>
    <th width="16%">Approved By</th>
</tr>
</thead>
<tbody>
@foreach($adjustments as $adj)
<tr>
    <td>{{ $adj['adjustment_no'] }}</td>
    <td class="text-end amt-positive">+{{ fmtMoney4($adj['amount']) }}</td>
    <td>{{ str_replace('_', ' ', $adj['source']) }}</td>
    <td>
        @if($adj['reference_date']) Against deposit of {{ \Carbon\Carbon::parse($adj['reference_date'])->format('d-m-Y') }}. @endif
        {{ $adj['reference_note'] }} {{ $adj['remarks'] }}
    </td>
    <td>{{ $adj['approved_by'] }}</td>
</tr>
@endforeach
</tbody>
</table>
@endif

<div class="signature-section">

<table border="0" style="border:none; margin:0;">

<tr>

<td class="no-border text-center" style="width:45%;">

_____________________

<br>

Submitted By ({{ $user->name }})

</td>

<td class="no-border" style="width:10%;"></td>

<td class="no-border text-center" style="width:45%;">

_____________________

<br>

Received By (Accounts)

</td>

</tr>

</table>

</div>

</body>

</html>
