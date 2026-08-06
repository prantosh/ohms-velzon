< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    Diagnostic Invoice
                </title>

                <style>

                    body{
                        font - family: DejaVu Sans, sans-serif;
                    font-size:12px;
}

                    table{
                        width:100%;
                    border-collapse:collapse;
}

                    table th,
                    table td{
                        border:1px solid #000;
                    padding:5px;
}

                    .text-right{
                        text - align:right;
}

                    .text-center{
                        text - align:center;
}

                    .header{
                        text - align:center;
                    margin-bottom:15px;
}

                    .no-border{
                        border:none !important;
}

                </style>

        </head>

        <body>

            <!-- =======================================================
            HEADER
======================================================= -->

<div class="header">

    <h2>

        {{ config('app.name') }}

    </h2>

    <h3>

        Diagnostic Invoice

    </h3>

</div>

<!-- =======================================================
     INVOICE INFORMATION
======================================================= -->

<table>

<tr>

<td width="25%">

    Invoice No

</td>

<td width="25%">

    {{ $invoice->invoice_no }}

</td>

<td width="25%">

    Invoice Date

</td>

<td width="25%">

    {{ $invoice->invoice_date }}

</td>

</tr>

<tr>

<td>

    Patient ID

</td>

<td>

    {{ $invoice->patient_id }}

</td>

<td>

    Patient Name

</td>

<td>

    {{ $invoice->patient_name }}

</td>

</tr>

<tr>

<td>

    Age

</td>

<td>

    {{ $invoice->patient_age }}

</td>

<td>

    Gender

</td>

<td>

    {{ $invoice->patient_gender }}

</td>

</tr>

<tr>

<td>

    Mobile

</td>

<td>

    {{ $invoice->patient_mobile_no }}

</td>

<td>

    Payment Mode

</td>

<td>

    {{ $invoice->payment_mode }}

</td>

</tr>

</table>

<br>

<!-- =======================================================
     TEST DETAILS
======================================================= -->

<table>

<thead>

<tr>

<th width="5%">

SL

</th>

<th width="15%">

Category

</th>

<th width="30%">

Test Name

</th>

<th width="10%">

Rate

</th>

<th width="10%">

Discount

</th>

<th width="10%">

Amount

</th>

<th width="20%">

Remarks

</th>

</tr>

</thead>

<tbody>

@php

$sl = 1;

@endphp

@foreach($tests as $row)

<tr>

<td class="text-center">

{{ $sl++ }}

</td>

<td>

{{ $row->item_code }}

</td>

<td>

{{ $row->test_name }}

</td>

<td class="text-right">

{{ number_format($row->rate,2) }}

</td>

<td class="text-right">

{{ number_format($row->discount,2) }}

</td>

<td class="text-right">

{{ number_format($row->amount,2) }}

</td>

<td>

{{ $row->remarks }}

</td>

</tr>

@endforeach

</tbody>

</table>

<br>

<!-- =======================================================
     TOTALS
======================================================= -->

<table>

<tr>

<td width="70%" class="no-border">

</td>

<td width="15%">

Gross Amount

</td>

<td width="15%" class="text-right">

{{ number_format($invoice->total_amount,2) }}

</td>

</tr>

<tr>

<td class="no-border">

</td>

<td>

Discount

</td>

<td class="text-right">

{{ number_format($invoice->discount,2) }}

</td>

</tr>

<tr>

<td class="no-border">

</td>

<td>

Paid

</td>

<td class="text-right">

{{ number_format($invoice->paid_amount,2) }}

</td>

</tr>

<tr>

<td class="no-border">

</td>

<td>

Due

</td>

<td class="text-right">

{{ number_format($invoice->due_amount,2) }}

</td>

</tr>

                        <tr>

                            <td>Test Date</td>

                            <td>{{ $invoice-> test_date}}</td>

                            <td>Status</td>

                            <td>{{ $invoice-> status}}</td>

                        </tr>
                        <tr>

                            <td class="no-border"></td>

                            <td>Doctor Payment</td>

                            <td class="text-right">

                                {{ number_format($invoice-> doctor_payment_amount, 2) }}

                            </td>

                        </tr>
</table>

<br><br>

<!-- =======================================================
     REMARKS
======================================================= -->

<table>

<tr>

<td>

<b>Remarks :</b>

{{ $invoice->remarks }}

</td>

</tr>

</table>

<br><br><br>

<!-- =======================================================
     SIGNATURES
======================================================= -->

<table>

<tr>

<td class="no-border text-center">

_____________________

<br>

Patient Signature

</td>

<td class="no-border text-center">

_____________________

<br>

Authorized Signature

</td>

</tr>

</table>

</body>

</html>
