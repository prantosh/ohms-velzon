<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Additional Discount Report</title>
<style>
    @page { margin-top: 1in; margin-right: 40px; margin-bottom: 40px; margin-left: 40px; }
    @page :first { margin-top: 0.4in; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #000; margin: 0; padding: 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table th, table td { border: 1px solid #000; padding: 2.5px 4px; vertical-align: top; }
    .summary-table th, .summary-table td { border: 1px solid #ccc; }
    .text-end { text-align: right; }
    .label-blue { font-weight: bold; }
    table thead th { font-weight: bold; text-align: center; background-color: #e6e6e6; }
    .cancelled { text-decoration: line-through; color: #777; }
</style>
</head>
<body>

@include('partials.pdf-header', ['reportTitle' => 'ADDITIONAL DISCOUNT REPORT'])

<table class="summary-table">
    <tr>
        <td width="20%" class="label-blue">From Date</td><td>{{ $fromDate->format('d-m-Y') }}</td>
        <td width="20%" class="label-blue">To Date</td><td>{{ $toDate->format('d-m-Y') }}</td>
    </tr>
    @foreach(array_chunk($filters, 2, true) as $pair)
    <tr>
        @foreach($pair as $label => $value)
            <td class="label-blue">{{ $label }}</td><td>{{ $value }}</td>
        @endforeach
        @if(count($pair) < 2)<td></td><td></td>@endif
    </tr>
    @endforeach
    <tr>
        <td class="label-blue">Invoices / Lines</td><td>{{ $summary['invoices'] }} / {{ $summary['lines'] }}</td>
        <td class="label-blue">Total Additional Discount</td><td><b>Rs. {{ number_format($summary['total_discount'], 2) }}</b></td>
    </tr>
    <tr>
        <td class="label-blue">Printed By</td><td colspan="3">{{ $printedBy }}</td>
    </tr>
</table>

<table class="summary-table">
    <thead><tr><th>By Approver</th><th>By Operator</th><th>By Invoice Type</th></tr></thead>
    <tr>
        @foreach(['by_approver', 'by_operator', 'by_type'] as $key)
        <td width="33%">
            @foreach($summary[$key] as $item)
                {{ $item['name'] }} ({{ $item['count'] }}): <b>Rs. {{ number_format($item['total'], 2) }}</b><br>
            @endforeach
        </td>
        @endforeach
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th width="55">Date</th><th>Invoice No</th><th>Type</th><th>Patient</th><th>Item</th>
            <th width="50">Rate</th><th width="30">%</th><th width="55">Discount</th>
            <th>Approved By</th><th>Operator</th><th>Remarks</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $r)
        <tr class="{{ $r->is_cancelled ? 'cancelled' : '' }}">
            <td>{{ $r->date_fmt }}</td>
            <td>{{ $r->invoice_no }}{{ $r->is_cancelled ? ' (Cancelled)' : '' }}</td>
            <td>{{ $r->type_label }}</td>
            <td>{{ $r->patient_name }}</td>
            <td>{{ $r->item_description }}</td>
            <td class="text-end">{{ number_format($r->rate, 2) }}</td>
            <td class="text-end">{{ (float) $r->additional_discount_percent > 0 ? (float) $r->additional_discount_percent : '-' }}</td>
            <td class="text-end">{{ number_format($r->discount_total, 2) }}</td>
            <td>{{ $r->approver_name }}</td>
            <td>{{ $r->operator_name }}</td>
            <td>{{ $r->discount_approval_remarks }}</td>
        </tr>
        @endforeach
        <tr>
            <td colspan="7" class="text-end"><b>Total (excluding cancelled)</b></td>
            <td class="text-end"><b>{{ number_format($summary['total_discount'], 2) }}</b></td>
            <td colspan="3"></td>
        </tr>
    </tbody>
</table>

</body>
</html>
