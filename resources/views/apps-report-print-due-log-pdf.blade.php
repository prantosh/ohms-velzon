<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Report Print With Due Log</title>
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
    .muted { color: #555; font-size: 7px; }
</style>
</head>
<body>

@include('partials.pdf-header', ['reportTitle' => 'REPORT PRINT WITH DUE LOG'])

<table class="summary-table">
    @foreach(array_chunk($filters, 2, true) as $pair)
    <tr>
        @foreach($pair as $label => $value)
            <td width="20%" class="label-blue">{{ $label }}</td><td>{{ $value }}</td>
        @endforeach
    </tr>
    @endforeach
    <tr>
        <td class="label-blue">Total Records</td><td>{{ $logs->count() }}</td>
        <td class="label-blue">Printed By</td><td>{{ $printedBy }}</td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th>Report Printed At</th><th>Invoice No</th><th>Patient</th><th>Test Items</th>
            <th>Total</th><th>Paid</th><th>Due</th><th>Printed By</th><th>Final Payment Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse($logs as $log)
        @php $fp = $log->final_payment ?? ['label' => '-', 'lines' => []]; @endphp
        <tr>
            <td>{{ optional($log->printed_at)->format('d-m-Y H:i') }}</td>
            <td>{{ $log->invoice_no }}</td>
            <td>{{ $log->patient_name }}<br><span class="muted">{{ $log->patient_mobile_no }}</span></td>
            <td>{{ $log->test_items }}</td>
            <td class="text-end">{{ number_format($log->total_amount, 2) }}</td>
            <td class="text-end">{{ number_format($log->paid_amount, 2) }}</td>
            <td class="text-end">{{ number_format($log->due_amount, 2) }}</td>
            <td>{{ $log->printed_by_name }}</td>
            <td><b>{{ str_replace('₹', 'Rs. ', $fp['label']) }}</b>
                @foreach($fp['lines'] as $line)<br><span class="muted">{{ str_replace('₹', 'Rs. ', $line) }}</span>@endforeach
            </td>
        </tr>
        @empty
        <tr><td colspan="9" style="text-align:center">No records.</td></tr>
        @endforelse
    </tbody>
</table>

</body>
</html>
