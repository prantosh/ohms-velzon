< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Doctor Payable Report (Non-Cash Invoices) - Summary
                </title>

@php

function fmtMoney22($v)
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

                    .signature-section {
                        margin-top: 30px;
                    }
                </style>

        </head>

        <body>

@include('partials.pdf-header', ['reportTitle' => 'DOCTOR PAYABLE REPORT (NON-CASH INVOICES) - SUMMARY'])

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
    <td><b>Rs. {{ fmtMoney22($grandTotal['payable']) }} / Rs. {{ fmtMoney22($grandTotal['settled']) }} / Rs. {{ fmtMoney22($grandTotal['pending']) }}</b></td>
</tr>

</table>

<!-- =======================================================
     BY DOCTOR
======================================================= -->

<table>

<thead>
<tr>
    <th colspan="5" style="text-align:left; background-color:#e6e6e6;">By Doctor</th>
</tr>
<tr>
    <th>Doctor</th>
    <th width="14%">Invoices</th>
    <th width="18%">Payable</th>
    <th width="18%">Settled</th>
    <th width="18%">Pending</th>
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row['doctor_name'] }}</td>
    <td class="text-center">{{ $row['invoice_count'] }}</td>
    <td class="text-end">{{ fmtMoney22($row['payable']) }}</td>
    <td class="text-end">{{ fmtMoney22($row['settled']) }}</td>
    <td class="text-end">{{ fmtMoney22($row['pending']) }}</td>
</tr>
@empty
<tr>
    <td colspan="5" class="text-center">No non-cash invoices with a doctor payable found for the selected filters.</td>
</tr>
@endforelse

<tr style="background-color:#f0f0f0; font-weight:bold;">
    <td class="text-end">Total</td>
    <td class="text-center">{{ $grandTotal['invoice_count'] }}</td>
    <td class="text-end">{{ fmtMoney22($grandTotal['payable']) }}</td>
    <td class="text-end">{{ fmtMoney22($grandTotal['settled']) }}</td>
    <td class="text-end">{{ fmtMoney22($grandTotal['pending']) }}</td>
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
