<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Service Summary Report</title>
    <style>
        @page { margin: 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #000; }
        h3 { margin: 0 0 4px 0; text-align: center; }
        .sub { text-align: center; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #000; padding: 3px 5px; text-align: center; }
        th { background: #eee; }
        td.l, th.l { text-align: left; }
        tfoot td { font-weight: bold; background: #f4f4f4; }
        .foot { margin-top: 10px; font-size: 8px; }
    </style>
</head>
<body>
    <h3>Service Summary Report (Date-wise Count of Appointments, Visits and Services)</h3>
    <div class="sub">
        {{ $fromDate->format('d-m-Y') }} to {{ $toDate->format('d-m-Y') }}
        @if($filterText) <br>{{ $filterText }} @endif
    </div>

    <table>
        <thead>
            <tr>
                <th class="l">Date</th>
                @foreach($columns as $c)<th>{{ $c['label'] }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr>
                    <td class="l">{{ \Carbon\Carbon::parse($r['date'])->format('d-m-Y') }}</td>
                    @foreach($columns as $c)<td>{{ $r[$c['key']] ?? 0 }}</td>@endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) + 1 }}">No activity found.</td></tr>
            @endforelse
        </tbody>
        @if(count($rows))
        <tfoot>
            <tr>
                <td class="l">Total</td>
                @foreach($columns as $c)<td>{{ $totals[$c['key']] }}</td>@endforeach
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="foot">Printed by {{ $printedBy }} on {{ now()->format('d-m-Y H:i') }}</div>
</body>
</html>
