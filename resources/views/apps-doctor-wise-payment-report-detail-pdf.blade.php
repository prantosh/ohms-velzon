< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Doctor Wise Payment Report - Detail
                </title>

@php

function fmtMoney23($v)
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

@include('partials.pdf-header', ['reportTitle' => 'DOCTOR WISE PAYMENT REPORT - DETAIL'])

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
    <td class="label-blue">Payment Items</td>
    <td>{{ $grandTotal['count'] }}</td>

    <td class="label-blue">Payable / Settled / Pending</td>
    <td><b>Rs. {{ fmtMoney23($grandTotal['payable']) }} / Rs. {{ fmtMoney23($grandTotal['settled']) }} / Rs. {{ fmtMoney23($grandTotal['pending']) }}</b></td>
</tr>

</table>

<!-- =======================================================
     DETAIL
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="12" style="text-align:left; background-color:#e6e6e6;">
        Doctor Payments, {{ $fromDate->format('d-m-Y') }} to {{ $toDate->format('d-m-Y') }}
    </th>
</tr>
<tr>
    <th width="6%">Invoice Date</th>
    <th width="8%">Invoice No</th>
    <th width="12%">Doctor</th>
    <th width="9%">Patient</th>
    <th width="12%">Item</th>
    <th width="7%">Gross</th>
    <th width="7%">Payable</th>
    <th width="7%">Settled</th>
    <th width="7%">Pending</th>
    <th width="7%">Status</th>
    <th width="9%">Settlement No</th>
    <th width="7%">Settlement Date</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row['invoice_date_fmt'] }}</td>
    <td>{{ $row['invoice_no'] }}</td>
    <td>{{ $row['doctor_name'] }}</td>
    <td>{{ $row['patient_name'] ?: '-' }}</td>
    <td>{{ $row['item_description'] ?: '-' }}</td>
    <td class="text-end">{{ fmtMoney23($row['gross_amount']) }}</td>
    <td class="text-end">{{ fmtMoney23($row['payable_amount']) }}</td>
    <td class="text-end">{{ fmtMoney23($row['settled_amount']) }}</td>
    <td class="text-end">{{ fmtMoney23($row['pending_amount']) }}</td>
    <td class="text-center">{{ $row['payment_status'] }}</td>
    <td>{{ $row['settlement_display'] }}</td>
    <td>{{ $row['last_settlement_date_fmt'] }}</td>
</tr>
@empty
<tr>
    <td colspan="12" class="text-center">No doctor payments found for the selected filters.</td>
</tr>
@endforelse

<tr style="background-color:#f0f0f0; font-weight:bold;">
    <td colspan="5" class="text-end">Total</td>
    <td class="text-end">{{ fmtMoney23($grandTotal['gross']) }}</td>
    <td class="text-end">{{ fmtMoney23($grandTotal['payable']) }}</td>
    <td class="text-end">{{ fmtMoney23($grandTotal['settled']) }}</td>
    <td class="text-end">{{ fmtMoney23($grandTotal['pending']) }}</td>
    <td colspan="3"></td>
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
