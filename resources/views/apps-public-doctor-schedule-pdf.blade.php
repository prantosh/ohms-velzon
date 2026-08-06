<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>Doctor Schedule</title>

<style>

@page {
    margin-top: 1in;
    margin-right: 30px;
    margin-bottom: 40px;
    margin-left: 30px;
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

.group-heading {
    color: #fff;
    font-weight: bold;
    font-size: 13px;
    padding: 6px 10px;
    margin-top: 16px;
    border-radius: 3px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 6px;
    table-layout: fixed;
}

table th,
table td {
    border: 1px solid #999;
    padding: 5px;
    vertical-align: top;
    word-wrap: break-word;
}

thead th {
    color: #000;
    font-weight: bold;
    text-align: center;
    font-size: 10px;
}

.entry {
    padding: 3px 0;
    border-bottom: 1px dashed #bbb;
}

.entry:last-child {
    border-bottom: none;
}

.entry-doctor {
    font-weight: bold;
    display: block;
}

.entry-time {
    color: #333;
    font-size: 8px;
    display: block;
}

.no-entry {
    color: #aaa;
    text-align: center;
}

.footer-note {
    margin-top: 4px;
    font-size: 9px;
    color: #555;
    text-align: right;
}

</style>

</head>

<body>

@include('partials.pdf-header', ['reportTitle' => 'DOCTOR WEEKLY SCHEDULE', 'headerColor' => '#003399', 'compact' => true])

<div class="footer-note">Generated On: {{ $generatedOn }}</div>

@php
    // Consistent colour per weekday, used for every group's calendar.
    // DomPDF doesn't reliably support 8-digit RGBA hex, so header/body
    // shades are defined explicitly rather than via alpha transparency.
    $dayColors = [
        'monday'    => ['header' => '#cfe2ff', 'body' => '#eaf2ff'],
        'tuesday'   => ['header' => '#d4edda', 'body' => '#eef8f0'],
        'wednesday' => ['header' => '#fff3cd', 'body' => '#fffaea'],
        'thursday'  => ['header' => '#ffe5d0', 'body' => '#fff4ea'],
        'friday'    => ['header' => '#e2d9f3', 'body' => '#f3eefa'],
        'saturday'  => ['header' => '#fcd8e8', 'body' => '#fdeef4'],
        'sunday'    => ['header' => '#d1ecf1', 'body' => '#eaf7f9'],
    ];

    // Cycled per specialisation group heading, for visual variety.
    $groupColors = ['#003399', '#0ab39c', '#f06548', '#7239ea', '#f7b84b', '#299cdb'];
    $groupIndex = 0;
@endphp

@forelse($groups as $category => $days)

<div class="group-heading" style="background: {{ $groupColors[$groupIndex % count($groupColors)] }};">
    {{ $category }}
</div>

<table>
    <thead>
        <tr>
            @foreach($dayOrder as $day)
            <th style="background: {{ $dayColors[$day]['header'] }};" width="{{ round(100 / count($dayOrder), 2) }}%">
                {{ ucfirst($day) }}
            </th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        <tr>
            @foreach($dayOrder as $day)
            <td style="background: {{ $dayColors[$day]['body'] }};">
                @forelse($days[$day] as $entry)
                <div class="entry">
                    <span class="entry-doctor">{{ $entry['doctor_name'] }}</span>
                    <span class="entry-time">{{ $entry['time'] }}</span>
                </div>
                @empty
                <span class="no-entry">&mdash;</span>
                @endforelse
            </td>
            @endforeach
        </tr>
    </tbody>
</table>

@php $groupIndex++; @endphp

@empty

<p style="margin-top:20px; color:#555;">No doctor schedules are available at this time.</p>

@endforelse

</body>

</html>
