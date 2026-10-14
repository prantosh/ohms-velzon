< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Daily Cash Settlement
                </title>

@php

function fmtMoney26($v)
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
                        margin-top: 6px;
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

                    .label-blue {
                        color: #000;
                        font-weight: bold;
                    }

                    table thead th {
                        color: #000;
                        font-weight: bold;
                        text-align: center;
                    }

                    .doctor-header {
                        background-color: #e6e6e6;
                        font-weight: bold;
                        text-align: left;
                        padding: 4px 6px;
                    }

                    .doctor-total-row td {
                        font-weight: bold;
                        background-color: #f5f5f5;
                    }

                    .grand-total-table {
                        margin-top: 10px;
                    }

                    .grand-total-table td {
                        font-weight: bold;
                    }

                    .no-border,
                    .no-border td {
                        border: none;
                    }

                    .signature-section {
                        margin-top: 35px;
                    }
                </style>

        </head>

        <body>

@include('partials.pdf-header', ['reportTitle' => 'DAILY CASH SETTLEMENT'])

<!-- =======================================================
     INFO / SUMMARY
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">User</td>
    <td>{{ $userLabel }}</td>

    <td width="25%" class="label-blue">Date</td>
    <td>{{ $dateFmt }}</td>
</tr>

<tr>
    <td class="label-blue">Printed By</td>
    <td>{{ $printedBy }}</td>

    <td class="label-blue">Printed On</td>
    <td>{{ now()->format('d-m-Y h:i A') }}</td>
</tr>

<tr>
    <td class="label-blue">Doctors</td>
    <td>{{ $summary['total_doctors'] }}</td>

    <td class="label-blue">Invoices Settled</td>
    <td>{{ $summary['total_invoices'] }}</td>
</tr>

<tr>
    <td class="label-blue">Total Doctor Payable</td>
    <td>Rs. {{ fmtMoney26($summary['total_payable']) }}</td>

    <td class="label-blue">Payable Not Settled</td>
    <td>Rs. {{ fmtMoney26($summary['total_unsettled']) }} ({{ $summary['unsettled_count'] }} invoice(s))</td>
</tr>

</table>

<!-- =======================================================
     DOCTOR-WISE SETTLED INVOICES
======================================================= -->

@forelse($groups as $group)
<table>
<thead>
<tr>
    <th colspan="{{ $isAllUsers ? 7 : 6 }}" class="doctor-header">
        {{ $group['doctor_name'] }} &mdash; {{ $group['invoice_count'] }} Settled
        @if($group['unsettled_count']) &mdash; {{ $group['unsettled_count'] }} Not settled (Rs. {{ fmtMoney26($group['total_unsettled']) }}) @endif
    </th>
</tr>
<tr>
    <th>Invoice No</th>
    <th>Category</th>
    @if($isAllUsers)
    <th>User</th>
    @endif
    <th>Patient</th>
    <th>Item</th>
    <th width="11%">Payable</th>
    <th width="11%">Settled</th>
</tr>
</thead>

<tbody>
@foreach($group['items'] as $item)
<tr>
    <td>{{ $item->invoice_no }}</td>
    <td>{{ $item->category }}</td>
    @if($isAllUsers)
    <td>{{ $item->user_name }}</td>
    @endif
    <td>{{ $item->patient_name }}</td>
    <td>{{ $item->item_description }}@if(!$item->is_settled) <b>(Not settled)</b>@endif</td>
    <td class="text-end">{{ fmtMoney26($item->payable_amount) }}</td>
    <td class="text-end">{{ fmtMoney26($item->settled_amount) }}</td>
</tr>
@endforeach
</tbody>

<tfoot>
<tr class="doctor-total-row">
    <td colspan="{{ $isAllUsers ? 5 : 4 }}" class="text-end">Total for {{ $group['doctor_name'] }}</td>
    <td class="text-end">{{ fmtMoney26($group['total_payable']) }}</td>
    <td class="text-end">{{ fmtMoney26($group['total_amount']) }}</td>
</tr>
</tfoot>
</table>
@empty
<table>
<tr>
    <td class="text-center">No doctor payables were settled, or are waiting to be settled, for this user on this day.</td>
</tr>
</table>
@endforelse

<!-- =======================================================
     GRAND TOTAL
======================================================= -->

@if(count($groups))
<table class="grand-total-table">
<tr>
    <td width="70%" class="text-end">Grand Total Settled ({{ $summary['total_doctors'] }} Doctor(s), {{ $summary['total_invoices'] }} Invoice(s))</td>
    <td class="text-end">Rs. {{ fmtMoney26($summary['total_amount']) }}</td>
</tr>
<tr>
    <td class="text-end">Total Doctor Payable</td>
    <td class="text-end">Rs. {{ fmtMoney26($summary['total_payable']) }}</td>
</tr>
<tr>
    <td class="text-end">Payable Not Settled</td>
    <td class="text-end">Rs. {{ fmtMoney26($summary['total_unsettled']) }}</td>
</tr>
</table>
@endif

<!-- =======================================================
     SIGNATURE
======================================================= -->

<div class="signature-section">

<table border="0" style="border:none; margin:0;">

<tr>

<td class="no-border text-center" style="width:45%;">

_____________________

<br>

Submitted By ({{ $userLabel }})

</td>

<td class="no-border" style="width:10%;"></td>

<td class="no-border text-center" style="width:45%;">

_____________________

<br>

Verified By (Accounts)

</td>

</tr>

</table>

</div>

</body>

</html>
