< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Reporting Group Summary Report
                </title>

@php

function fmtMoney12($v)
{
    return number_format((float) ($v ?? 0), 2);
}

$columns = ['collection_cash', 'collection_noncash', 'refund', 'doctor_payable', 'paid_to_doctor', 'cash_to_deposit'];
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
                        margin-top: 8px;
                    }

                        table th,
                        table td {
                            border: 1px solid #000;
                            padding: 3px 5px;
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

                    .group-row td {
                        background-color: #e6e6e6;
                        font-weight: bold;
                    }

                    .subtotal-row td {
                        background-color: #f5f5f5;
                        font-weight: bold;
                    }

                    .signature-section {
                        margin-top: 60px;
                    }
                </style>

        </head>

        <body>

@include('partials.pdf-header', ['reportTitle' => 'REPORTING GROUP SUMMARY REPORT'])

<!-- =======================================================
     DATE / TOTALS
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">Date</td>
    <td>{{ $dateFmt }}</td>

    <td width="25%" class="label-blue">Printed By</td>
    <td>{{ $printedBy }}</td>
</tr>

<tr>
    <td class="label-blue">Total Cash to Deposit</td>
    <td><b>Rs. {{ fmtMoney12($grandTotal['cash_to_deposit']) }}</b></td>

    <td class="label-blue">Total Collection (Cash + Non-Cash)</td>
    <td><b>Rs. {{ fmtMoney12($grandTotal['collection_cash'] + $grandTotal['collection_noncash']) }}</b></td>
</tr>

</table>

<!-- =======================================================
     GROUP-WISE BREAKDOWN
======================================================= -->

<table>

<thead>
<tr>
    <th>User</th>
    <th>Collection Cash</th>
    <th>Collection Non-Cash</th>
    <th>Refund</th>
    <th>Doctor Payable</th>
    <th>Paid to Doctor</th>
    <th>Cash to Deposit</th>
</tr>
</thead>

<tbody>

@forelse($groups as $group)
<tr class="group-row">
    <td colspan="7">{{ $group['group_name'] }}</td>
</tr>
@foreach($group['rows'] as $row)
<tr>
    <td>{{ $row['user_name'] }}</td>
    @foreach($columns as $col)
    <td class="text-end">{{ fmtMoney12($row[$col]) }}</td>
    @endforeach
</tr>
@endforeach
<tr class="subtotal-row">
    <td>{{ $group['group_name'] }} -- Subtotal</td>
    @foreach($columns as $col)
    <td class="text-end">{{ fmtMoney12($group['subtotal'][$col]) }}</td>
    @endforeach
</tr>
@empty
<tr>
    <td colspan="7" class="text-center">No activity found for the selected date.</td>
</tr>
@endforelse

<tr style="background-color:#d9d9d9; font-weight:bold;">
    <td>Grand Total</td>
    @foreach($columns as $col)
    <td class="text-end">{{ fmtMoney12($grandTotal[$col]) }}</td>
    @endforeach
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
