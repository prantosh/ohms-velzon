< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Doctor Payable Report (Non-Cash Invoices) - Detail
                </title>

@php

function fmtMoney21($v)
{
    return number_format((float) ($v ?? 0), 2);
}
@endphp

                <style>

                    @page {
                            margin-top: 1in;
                            margin-right: 25px;
                            margin-bottom: 40px;
                            margin-left: 25px;
                        }

                    @page :first {
                            margin-top: 0.4in;
                        }

                    body {
                        font-family: DejaVu Sans, sans-serif;
                        font-size: 7px;
                        color: #000;
                        margin: 0;
                        padding: 0;
                    }

                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 8px;
                    }

                        table th,
                        table td {
                            border: 1px solid #000;
                            padding: 3px;
                            vertical-align: top;
                        }

                    .summary-table th,
                    .summary-table td {
                        border: 1px solid #ccc;
                        font-size: 9px;
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

                    .signature-section {
                        margin-top: 30px;
                    }
                </style>

        </head>

        <body>

@include('partials.pdf-header', ['reportTitle' => 'DOCTOR PAYABLE REPORT (NON-CASH INVOICES) - DETAIL'])

<!-- =======================================================
     FILTERS / TOTALS
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">Invoice Date Period</td>
    <td>{{ $fromDate->format('d-m-Y') }} to {{ $toDate->format('d-m-Y') }}</td>

    <td width="25%" class="label-blue">Printed By</td>
    <td>{{ $printedBy }}</td>
</tr>

<tr>
    <td class="label-blue">Invoices</td>
    <td>{{ $grandTotal['invoice_count'] }}</td>

    <td class="label-blue">Total Payable / Settled / Pending</td>
    <td><b>Rs. {{ fmtMoney21($grandTotal['payable']) }} / Rs. {{ fmtMoney21($grandTotal['settled']) }} / Rs. {{ fmtMoney21($grandTotal['pending']) }}</b></td>
</tr>

</table>

<!-- =======================================================
     DETAIL
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="11" style="text-align:left; background-color:#e6e6e6;">
        Non-cash patient invoices with a doctor payable, {{ $fromDate->format('d-m-Y') }} to {{ $toDate->format('d-m-Y') }}
    </th>
</tr>
<tr>
    <th width="7%">Invoice Date</th>
    <th width="8%">Invoice No</th>
    <th width="13%">Doctor(s)</th>
    <th width="10%">Patient</th>
    <th width="6%">Patient Paid</th>
    <th width="9%">User Involved</th>
    <th width="8%">Payable</th>
    <th width="8%">Settled</th>
    <th width="8%">Pending</th>
    <th width="12%">Settlement No(s)</th>
    <th width="11%">Settlement Date(s)</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row['invoice_date_fmt'] }}</td>
    <td>{{ $row['invoice_no'] }}</td>
    <td>{{ $row['doctor_names'] ?: '-' }}</td>
    <td>{{ $row['patient_name'] ?: '-' }}</td>
    <td class="text-center">{{ $row['patient_payment_mode'] }}</td>
    <td>{{ $row['user_involved'] }}</td>
    <td class="text-end">{{ fmtMoney21($row['total_payable']) }}</td>
    <td class="text-end">{{ fmtMoney21($row['total_settled']) }}</td>
    <td class="text-end">{{ fmtMoney21($row['total_pending']) }}</td>
    <td>{{ $row['settlement_nos'] }}</td>
    <td>{{ $row['settlement_dates'] }}</td>
</tr>
@empty
<tr>
    <td colspan="11" class="text-center">No non-cash invoices with a doctor payable found for the selected filters.</td>
</tr>
@endforelse

<tr style="background-color:#f0f0f0; font-weight:bold;">
    <td colspan="6" class="text-end">Total</td>
    <td class="text-end">{{ fmtMoney21($grandTotal['payable']) }}</td>
    <td class="text-end">{{ fmtMoney21($grandTotal['settled']) }}</td>
    <td class="text-end">{{ fmtMoney21($grandTotal['pending']) }}</td>
    <td colspan="2"></td>
</tr>

</tbody>

</table>

<div class="signature-section">

<table border="0" style="border:none; margin:0;">

<tr>

<td class="no-border text-center">

_____________________

<br>

Prepared By ({{ $printedBy }})

</td>

</tr>

</table>

</div>

</body>

</html>
