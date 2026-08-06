<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>Doctor Settlement Voucher</title>

<style>

body{

    font-family: DejaVu Sans, sans-serif;

    font-size:12px;

    color:#000;

}

.header{

    text-align:center;

    border-bottom:2px solid #000;

    padding-bottom:10px;

    margin-bottom:15px;

}

.header h2{

    margin:0;

    color:#000;

}

.header h4{

    margin:3px 0;

}

.header p{

    margin:2px;

    font-size:11px;

}

table{

    width:100%;

    border-collapse:collapse;

}

th{

    background:#e6e6e6;

    color:#000;

    border:1px solid #999;

    padding:6px;

    font-size:11px;

}

td{

    border:1px solid #999;

    padding:5px;

    font-size:11px;

}

.text-end{

    text-align:right;

}

.text-center{

    text-align:center;

}

.info td{

    border:none;

    padding:3px;

}

.total-table{

    margin-top:10px;

    width:40%;

    float:right;

}

.signature{

    margin-top:70px;

}

.signature td{

    border:none;

    text-align:center;

    padding-top:40px;

}

.footer{

    position:fixed;

    bottom:0;

    left:0;

    right:0;

    text-align:center;

    font-size:10px;

    color:#666;

}

</style>

</head>

<body>

<div class="header">

<h2>ABSSRK</h2>

<h4>Dr. Amitava Basu Smriti Swastha Raksha Kendra</h4>

<p>Nimta, Kolkata</p>

<h3>DOCTOR SETTLEMENT VOUCHER</h3>

</div>

<table class="info">

<tr>

<td width="18%"><b>Settlement No</b></td>

<td width="32%">{{ $settlement->settlement_no }}</td>

<td width="18%"><b>Date</b></td>

<td width="32%">{{ \Carbon\Carbon::parse($settlement->settlement_date)->format('d-m-Y') }}</td>

</tr>

<tr>

<td><b>Doctor</b></td>

<td>{{ $settlement->doctor_name }}</td>

<td><b>Payment Mode</b></td>

<td>{{ $settlement->payment_mode }}</td>

</tr>

<tr>

<td><b>Bank</b></td>

<td>{{ $settlement->bank_name }}</td>

<td><b>Cheque/UTR</b></td>

<td>@if($settlement->payment_mode=='CHEQUE')

    {{ $settlement->cheque_no }}

@elseif($settlement->payment_mode=='BANK' || $settlement->payment_mode=='UPI')

    {{ $settlement->utr_no }}

@else

    --

@endif</td>

</tr>

<tr>

<td><b>Remarks</b></td>

<td colspan="3">

{{ $settlement->remarks }}

</td>

</tr>

<tr>

<td><b>Settled By</b></td>

<td colspan="3">

{{ optional($settlement->creator)->name ?? '-' }}

</td>

</tr>

</table>

<br>

<table>

<thead>

<tr>

    <th width="5%">Sl</th>

    <th width="18%">Invoice No</th>

    <th width="24%">Patient Name</th>

    <th width="23%">Test</th>

    <th width="10%">Payable</th>

    <th width="10%">Previously Paid</th>

    <th width="10%">Settlement</th>

</tr>

</thead>

<tbody>

@foreach($items as $index => $row)

<tr>

    <td class="text-center">

        {{ $index + 1 }}

    </td>

    <td>

        {{ $row->invoice_no }}

    </td>

    <td>

        {{ $row->patient_name }}

    </td>

    <td>

        {{ $row->item_description }}

    </td>

    <td class="text-end">

        {{ number_format($row->payable_amount,2) }}

    </td>

    <td class="text-end">

        {{ number_format($row->previous_paid_amount,2) }}

    </td>

    <td class="text-end">

        {{ number_format($row->settlement_amount,2) }}

    </td>

</tr>

@endforeach

</tbody>

</table>

<table class="total-table">

<tr>

    <td><b>Gross Settlement</b></td>

    <td class="text-end">

        {{ number_format($settlement->gross_amount,2) }}

    </td>

</tr>

<tr>

    <td><b>Deduction</b></td>

    <td class="text-end">

        {{ number_format($settlement->deduction_amount,2) }}

    </td>

</tr>

<tr style="background:#e6e6e6;">

    <td><b>NET PAYABLE</b></td>

    <td class="text-end">

        <b>

        {{ number_format($settlement->net_amount,2) }}

        </b>

    </td>

</tr>

</table>

<div style="clear:both;"></div>

<table class="signature">

<tr>

<td>

____________________

<br>

Prepared By

</td>

<td>

____________________

<br>

Checked By

</td>

<td>

____________________

<br>

Approved By

</td>

<td>

____________________

<br>

Doctor

</td>

</tr>

</table>

<div class="footer">

Generated on {{ now()->format('d-m-Y H:i') }}

</div>

</body>

</html>
