<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">

<style>

body{
    font-family: DejaVu Sans, sans-serif;
    font-size:11px;
    color:#000;
}

table{
    width:100%;
    border-collapse:collapse;
}

th,
td{
    border:1px solid #000;
    padding:4px;
}

th{
    background:#e9ecef;
    font-weight:bold;
}

/* A th's own background paints over its parent tr's background, so this
   needs the higher-specificity override here to actually take effect. */
thead th{
    background:#e9ecef;
    color:#000;
}

.text-center{
    text-align:center;
}

.text-right{
    text-align:right;
}

.header{
    margin-bottom:15px;
}

.title{
    font-size:22px;
    font-weight:bold;
}

.subtitle{
    font-size:14px;
    margin-top:4px;
}

.summary{
    margin-top:12px;
    margin-bottom:15px;
}

.summary td{
    border:none;
    padding:3px;
}

.footer{
    position:fixed;
    bottom:-15px;
    left:0;
    right:0;
    text-align:center;
    font-size:10px;
}

</style>

</head>

<body>

<table class="header" border="0">

<tr>

<td width="90" align="center">

@if(file_exists(public_path('build/images/abssrk-logo.png')))
<img src="{{ public_path('build/images/abssrk-logo.png') }}"
     style="width:90px;height:auto;">
@endif

</td>

<td class="text-center">

<div style="font-size:26px;font-weight:bold;color:#000;">
Dr. Amitava Basu Smriti Swastha Raksha Kendra
</div>



<div style="font-size:11px;margin-top:4px;">
Srayan Apartment, 19, M B Road,
                Kolkata - 700049
</div>

<div style="font-size:11px;">
Web: www.abssrk.online; Phone: 2513-7070 / 7439, 2539-2009 ; Mobile: 8685882287, 9051132429
</div>

<hr>

<div style="font-size:24px;
            font-weight:bold;
            color:#000;
            margin-top:8px;">
DOCTOR PAYABLES REGISTER
</div>

</td>

</tr>

</table>

<table border="0">

<tr>

<td width="25%"><strong>Doctor :</strong></td>
<td width="25%">{{ $doctor }}</td>
<td><strong>Transaction Type :</strong></td>
    <td>{{ $transactionType }}</td>


</tr>
<tr>
    <td><strong>Invoice Type :</strong></td>
    <td>{{ $invoiceType }}</td>

    <td><strong>Payment Status :</strong></td>
    <td>{{ $paymentStatus }}</td>
</tr>


<tr>

<td><strong>From :</strong></td>
<td>{{ $fromDate }}</td>

<td><strong>To :</strong></td>
<td>{{ $toDate }}</td>

</tr>

<tr>

<td><strong>Printed On :</strong></td>
<td>{{ $printedOn }}</td>

<td width="25%"><strong>Printed By :</strong></td>
<td width="25%">{{ $printedBy }}</td>


</tr>

</table>

<br>

<table class="summary">

<tr>

    <td><strong>Total Doctors :</strong></td>
    <td>{{ number_format($summary['total_doctors']) }}</td>
   <td><strong>Balance :</strong></td>
<td style="color:#000;font-weight:bold;">
    ₹ {{ number_format($summary['balance_amount'],2) }}
</td>
    <td><strong>Pending :</strong></td>
    <td style="color:#000;">
        ₹ {{ number_format($summary['pending_amount'],2) }}
    </td>

    <td><strong>Approved :</strong></td>
    <td style="color:#000;">
        ₹ {{ number_format($summary['approved_amount'],2) }}
    </td>

    <td><strong>Paid :</strong></td>
    <td style="color:#000;">
        ₹ {{ number_format($summary['paid_amount'],2) }}
    </td>
   
    <td><strong>Grand Payable :</strong></td>
    <td>
        ₹ {{ number_format($summary['grand_payable'],2) }}
    </td>

</tr>

</table>

<br>

<table>

<thead>

<tr>

<th >Sl</th>

<th >Payable No</th>

<th >Date</th>

<th >User</th>

<th >Invoice</th>

<th >Type</th>



<th >Patient</th>

<th >Item</th>

<th >Payable</th>

<th >Status</th>

<th >Paid</th>
    <th >Balance</th>

</tr>

</thead>

<tbody>



@foreach($groups as $group)

<tr style="background:#f0f0f0;">

    <td colspan="12">

        <strong>
         {{ $group['doctor_name'] }}
        
        </strong>

        &nbsp;&nbsp;

        Transactions :
        {{ $group['transaction_count'] }}

        &nbsp;&nbsp;

        Gross :
        ₹ {{ number_format($group['gross_amount'],2) }}

        &nbsp;&nbsp;

        Payable :
        ₹ {{ number_format($group['payable_amount'],2) }}

        &nbsp;&nbsp;

        Paid :
        ₹ {{ number_format($group['paid_amount'],2) }}

    </td>

</tr>
@foreach($group['rows'] as $i=>$row)

<tr>

    <td class="text-center">
        {{ $i + 1 }}
    </td>

    <td>
        {{ $row->payable_no }}
    </td>

    <td>
        {{ $row->payable_date }}
    </td>

    <td>
        @if(!empty($row->collection_due))
            <span style="color:#dc3545; font-weight:bold;">{{ $row->user_name }}</span>
        @else
            {{ $row->user_name }}
        @endif
    </td>

    <td>
        {{ $row->invoice_no }}
    </td>

    <td class="text-center">
        @if($row->invoice_type == 'DOCTOR_VISIT')
            CONSULTATION
        @else
            {{ $row->invoice_type }}
        @endif
    </td>

    

    <td>
        {{ $row->patient_name }}
    </td>

    <td>
        {{ $row->item_description }}
    </td>

    <td class="text-right">
        {{ number_format($row->payable_amount,2) }}
    </td>

    <td class="text-center">

        @php
            $statusStyle = match($row->payment_status){
                'PENDING'         => ['bg' => '#fff3cd', 'text' => '#000'],
                'APPROVED'        => ['bg' => '#d1ecf1', 'text' => '#000'],
                'PARTIALLY_PAID'  => ['bg' => '#cfe2ff', 'text' => '#000'],
                'PAID'            => ['bg' => '#d4edda', 'text' => '#000'],
                'CANCELLED'       => ['bg' => '#f8d7da', 'text' => '#000'],
                default           => ['bg' => '#e2e3e5', 'text' => '#000'],
            };
        @endphp

        <span style="
            display:block;
            background:{{ $statusStyle['bg'] }};
            color:{{ $statusStyle['text'] }};
            text-align:center;
            padding:2px;
        ">
            {{ $row->payment_status }}
        </span>

    </td>

    <td class="text-right">
        {{ number_format($row->paid_amount,2) }}
    </td>
    <td class="text-right">
    {{ number_format($row->balance_amount,2) }}
</td>
</tr>

@endforeach
<tr style="background:#f0f0f0;font-weight:bold;">

    <td colspan="8">
        Doctor Total
    </td>

    <td class="text-right">
        ₹ {{ number_format($group['payable_amount'],2) }}
    </td>

    <td></td>

    <td class="text-right">
        ₹ {{ number_format($group['paid_amount'],2) }}
    </td>
    <td class="text-right">
    ₹ {{ number_format($group['balance_amount'],2) }}
</td>
</tr>

<tr>
    <td colspan="12" style="border:none;height:6px;"></td>
</tr>
    

@endforeach
</tbody>
<tfoot>

<tr style="background:#e9ecef;color:#000;font-weight:bold;">

<td colspan="8">

Grand Total

</td>

<td class="text-right">

₹ {{ number_format($summary['grand_payable'],2) }}

</td>
<td></td>

<td class="text-right">

₹ {{ number_format($summary['paid_amount'],2) }}

</td>
<td class="text-right">
    ₹ {{ number_format($summary['balance_amount'],2) }}
</td>
</tr>

</tfoot>

</table>
<hr>

<div style="
text-align:center;
font-size:12px;
font-style:italic;
margin-top:8px;
">

Thank you for Choosing ABSSRK Clinic<br>

Committed to Care, Dedicated to You

</div>
<script type="text/php">
if (isset($pdf)) {

    $font = $fontMetrics->getFont("Helvetica", "normal");

    $size = 10;

    $pageText = "Page {PAGE_NUM} of {PAGE_COUNT}";

    $y = 575;

    $x = 730;

    $pdf->page_text(
        $x,
        $y,
        $pageText,
        $font,
        $size,
        array(0,0,0)
    );
}
</script>

</body>

</html>
