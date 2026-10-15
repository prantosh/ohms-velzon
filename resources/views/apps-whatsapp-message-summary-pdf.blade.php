< !DOCTYPE html >
    <html>

        <head>

            <meta charset="utf-8">

                <title>
                    WhatsApp Message Summary
                </title>

@php
function whatsappSuccessRate($sent, $failed)
{
    $attempted = $sent + $failed;
    if (!$attempted) return '0%';
    return round(($sent / $attempted) * 100) . '%';
}

// Mirrors the on-screen report: each category's headline number is its own
// Sent count (so they sum to the Sent total), with Failed/Skipped named
// underneath instead of being folded into a single raw total.
function whatsappTypeCell($stats)
{
    $stats = $stats ?? ['sent' => 0, 'failed' => 0, 'skipped' => 0];
    $notes = [];
    if (!empty($stats['failed'])) $notes[] = $stats['failed'] . ' failed';
    if (!empty($stats['skipped'])) $notes[] = $stats['skipped'] . ' skipped';
    $noteHtml = $notes ? '<div style="font-size:7px; color:#555;">' . implode(', ', $notes) . '</div>' : '';
    return '<b>' . ($stats['sent'] ?? 0) . '</b>' . $noteHtml;
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
                        margin-top: 8px;
                    }

                        table th,
                        table td {
                            border: 1px solid #000;
                            padding: 2.5px 4px;
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
                        margin-top: 60px;
                    }
                </style>

        </head>

        <body>

@include('partials.pdf-header', ['reportTitle' => 'WHATSAPP MESSAGE SUMMARY'])

<!-- =======================================================
     RANGE / TOTALS
======================================================= -->

<table class="summary-table">

<tr>
    <td width="25%" class="label-blue">From Date</td>
    <td>{{ $fromDate->format('d-m-Y (l)') }}</td>

    <td width="25%" class="label-blue">To Date</td>
    <td>{{ $toDate->format('d-m-Y (l)') }}</td>
</tr>

<tr>
    <td class="label-blue">Total Messages</td>
    <td>{{ $grandTotal['total_count'] }}</td>

    <td class="label-blue">Sent</td>
    <td>{{ $grandTotal['sent_count'] }}</td>
</tr>

<tr>
    <td class="label-blue">Failed</td>
    <td>{{ $grandTotal['failed_count'] }}</td>

    <td class="label-blue">Skipped (Auto-Send Off)</td>
    <td>{{ $grandTotal['skipped_count'] }}</td>
</tr>

<tr>
    <td class="label-blue">Success Rate</td>
    <td><b>{{ whatsappSuccessRate($grandTotal['sent_count'], $grandTotal['failed_count']) }}</b></td>

    <td class="label-blue">Printed By</td>
    <td>{{ $printedBy }}</td>
</tr>

</table>

<p style="font-size:7.5px; color:#555; margin-top:4px;">
    <b>How to read the table below:</b> Total = Sent + Failed + Skipped on every row. Each category
    column shows that category's own Sent count, so those always add up to the row's Sent total.
</p>

<!-- =======================================================
     DAY-WISE BREAKDOWN
======================================================= -->

<table>

@php $colCount = 5 + count($typeColumns); @endphp

<thead>
<tr>
    <th colspan="{{ $colCount }}" style="text-align:left; background-color:#e6e6e6;">
        Day-Wise Message Count for {{ $fromDate->format('d-m-Y') }} to {{ $toDate->format('d-m-Y') }}
    </th>
</tr>
<tr>
    <th width="14%">Date</th>
    <th width="9%">Total</th>
    <th width="9%">Sent</th>
    <th width="9%">Failed</th>
    <th width="9%">Skipped</th>
    @foreach($typeColumns as $col)
    <th>{{ $col['label'] }}</th>
    @endforeach
</tr>
</thead>

<tbody>

@forelse($rows as $row)
<tr>
    <td>{{ $row['date_fmt'] }}</td>
    <td class="text-end">{{ $row['total_count'] }}</td>
    <td class="text-end">{{ $row['sent_count'] }}</td>
    <td class="text-end">{{ $row['failed_count'] }}</td>
    <td class="text-end">{{ $row['skipped_count'] }}</td>
    @foreach($typeColumns as $col)
    <td class="text-end">{!! whatsappTypeCell($row['types'][$col['key']] ?? null) !!}</td>
    @endforeach
</tr>
@empty
<tr>
    <td colspan="{{ $colCount }}" class="text-center">No WhatsApp messages found in this date range.</td>
</tr>
@endforelse

<tr style="background-color:#f0f0f0; font-weight:bold;">
    <td>Total</td>
    <td class="text-end">{{ $grandTotal['total_count'] }}</td>
    <td class="text-end">{{ $grandTotal['sent_count'] }}</td>
    <td class="text-end">{{ $grandTotal['failed_count'] }}</td>
    <td class="text-end">{{ $grandTotal['skipped_count'] }}</td>
    @foreach($typeColumns as $col)
    <td class="text-end">{!! whatsappTypeCell($grandTotal['types'][$col['key']] ?? null) !!}</td>
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
