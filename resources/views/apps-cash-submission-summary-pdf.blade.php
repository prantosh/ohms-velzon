< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Cash Submission Category Wise Summary
                </title>

@php

function fmtMoney4($v)
{
    return number_format((float) ($v ?? 0), 2);
}

$netCashFormatter = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
$netCashInWords = ucwords($netCashFormatter->format((float) ($grandTotal['amount_to_deposit'] ?? 0)));
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
                        font-size: 9px;
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
                            padding: 4px 6px;
                            vertical-align: top;
                        }

                    .header-table th,
                    .header-table td {
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

                    .total-row td {
                        font-weight: bold;
                        background-color: #f2f2f2;
                    }

                    .signature-section {
                        margin-top: 45px;
                    }
                </style>

        </head>

        <body>

@include('partials.pdf-header', ['reportTitle' => 'DAILY CASH SUBMISSION - CATEGORY WISE SUMMARY'])

<!-- =======================================================
     USER / DATE
======================================================= -->

<table class="header-table">

<tr>
    <td width="25%" class="label-blue">User</td>
    <td>{{ $user->name }} ({{ $user->role }})</td>

    <td width="25%" class="label-blue">Date</td>
    <td>{{ $date->format('d-m-Y (l)') }}</td>
</tr>

</table>

<!-- =======================================================
     CATEGORY WISE SUMMARY
======================================================= -->

<table>

<thead>
<tr>
    <th style="text-align:left;">Category</th>
    <th width="12%">Collection (Cash)</th>
    <th width="12%">Collection (Non-Cash)</th>
    <th width="12%">Collection (Total)</th>
    <th width="10%">Refund</th>
    <th width="14%">Payment To Doctor</th>
    <th width="14%">Amount To Deposit</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row['item_name'] }}</td>
    <td class="text-end">{{ fmtMoney4($row['cash_collected']) }}</td>
    <td class="text-end">{{ fmtMoney4($row['non_cash_collected']) }}</td>
    <td class="text-end">{{ fmtMoney4($row['total_collected']) }}</td>
    <td class="text-end">{{ fmtMoney4($row['refund']) }}</td>
    <td class="text-end">{{ fmtMoney4($row['doctor_payment']) }}</td>
    <td class="text-end">{{ fmtMoney4($row['amount_to_deposit']) }}</td>
</tr>
@empty
<tr>
    <td colspan="7" class="text-center">No cash transactions found for this date.</td>
</tr>
@endforelse

<tr class="total-row">
    <td>Grand Total</td>
    <td class="text-end">{{ fmtMoney4($grandTotal['cash_collected']) }}</td>
    <td class="text-end">{{ fmtMoney4($grandTotal['non_cash_collected']) }}</td>
    <td class="text-end">{{ fmtMoney4($grandTotal['total_collected']) }}</td>
    <td class="text-end">{{ fmtMoney4($grandTotal['refund']) }}</td>
    <td class="text-end">{{ fmtMoney4($grandTotal['doctor_payment']) }}</td>
    <td class="text-end">{{ fmtMoney4($grandTotal['amount_to_deposit']) }}</td>
</tr>

</tbody>

</table>

<table class="header-table">
<tr>
    <td width="25%" class="label-blue">Amount To Deposit (In Words)</td>
    <td colspan="3">Rupees {{ $netCashInWords }} Only</td>
</tr>
<tr>
    <td class="label-blue">Print Date/Time</td>
    <td colspan="3">{{ now()->format('d-m-Y h:i A') }}</td>
</tr>
</table>

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
