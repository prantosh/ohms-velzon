< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Employee Performance Dashboard
                </title>

@php
function fmtMoney5($v)
{
    return number_format((float) ($v ?? 0), 2);
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

                    .text-end {
                        text-align: right;
                    }

                    .text-center {
                        text-align: center;
                    }

                    table thead th {
                        color: #000;
                        font-weight: bold;
                        text-align: center;
                    }

                    .amt-negative {
                        color: #a00;
                    }

                    .totals-row td {
                        font-weight: bold;
                        background-color: #f0f0f0;
                    }
                </style>

        </head>

        <body>

@include('partials.pdf-header', ['reportTitle' => 'EMPLOYEE PERFORMANCE DASHBOARD'])

<table>

<tr>
    <td style="border:none; padding:2px 0; font-weight:bold;" colspan="9">
        Period: {{ $fromDate->format('d-m-Y') }} to {{ $toDate->format('d-m-Y') }}
    </td>
</tr>

</table>

<table>

<thead>
<tr>
    <th>Employee</th>
    <th>Role</th>
    <th width="8%">Invoices</th>
    <th width="11%">Cash Collected</th>
    <th width="11%">Non-Cash Collected</th>
    <th width="9%">Refund</th>
    <th width="11%">Payment To Doctor</th>
    <th width="11%">Net Deposit</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row['name'] }}</td>
    <td>{{ $row['role'] }}</td>
    <td class="text-end">{{ $row['invoice_count'] }}</td>
    <td class="text-end">{{ fmtMoney5($row['cash_collected']) }}</td>
    <td class="text-end">{{ fmtMoney5($row['non_cash_collected']) }}</td>
    <td class="text-end amt-negative">{{ fmtMoney5($row['cash_refunded']) }}</td>
    <td class="text-end amt-negative">{{ fmtMoney5($row['cash_paid_to_doctors']) }}</td>
    <td class="text-end"><b>{{ fmtMoney5($row['net_cash_to_deposit']) }}</b></td>
</tr>
@empty
<tr>
    <td colspan="8" class="text-center">No staff users found.</td>
</tr>
@endforelse

<tr class="totals-row">
    <td colspan="2">Total</td>
    <td class="text-end">{{ $totals['invoice_count'] }}</td>
    <td class="text-end">{{ fmtMoney5($totals['cash_collected']) }}</td>
    <td class="text-end">{{ fmtMoney5($totals['non_cash_collected']) }}</td>
    <td class="text-end">{{ fmtMoney5($totals['cash_refunded']) }}</td>
    <td class="text-end">{{ fmtMoney5($totals['cash_paid_to_doctors']) }}</td>
    <td class="text-end">{{ fmtMoney5($totals['net_cash_to_deposit']) }}</td>
</tr>

</tbody>

</table>

        </body>

    </html>
