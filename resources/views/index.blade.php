@extends('layouts.master')
@section('title')
    @lang('translation.dashboards')
@endsection

@section('content')

<style>
.live-users-bar{
    display:flex;
    align-items:center;
    background:var(--vz-card-bg, #fff);
    border:1px solid var(--vz-border-color, #e9ebec);
    border-radius:6px;
    padding:6px 12px;
    margin-bottom:16px;
    overflow:hidden;
}
.live-users-bar .live-users-label{
    flex:0 0 auto;
    display:flex;
    align-items:center;
    gap:6px;
    font-weight:600;
    font-size:13px;
    padding-right:12px;
    margin-right:12px;
    border-right:1px solid var(--vz-border-color, #e9ebec);
    white-space:nowrap;
}
.live-users-bar .live-dot{
    width:8px;
    height:8px;
    border-radius:50%;
    background:#2ecc71;
    box-shadow:0 0 0 rgba(46,204,113,0.6);
    animation:live-dot-pulse 1.6s infinite;
}
@keyframes live-dot-pulse{
    0%{ box-shadow:0 0 0 0 rgba(46,204,113,0.6); }
    70%{ box-shadow:0 0 0 6px rgba(46,204,113,0); }
    100%{ box-shadow:0 0 0 0 rgba(46,204,113,0); }
}
.live-users-track-wrap{
    flex:1 1 auto;
    overflow:hidden;
    white-space:nowrap;
}
.live-users-track{
    display:inline-flex;
    align-items:center;
    animation:live-users-scroll 25s linear infinite;
}
.live-users-track:hover{
    animation-play-state:paused;
}
@keyframes live-users-scroll{
    0%{ transform:translateX(0); }
    100%{ transform:translateX(-50%); }
}
.live-user-chip{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:3px 14px 3px 3px;
    margin-right:10px;
    background:var(--vz-light, #f3f6f9);
    border-radius:20px;
}
.live-user-chip img{
    width:26px;
    height:26px;
    border-radius:50%;
    object-fit:cover;
}
.live-user-chip span{
    font-size:13px;
    font-weight:500;
    white-space:nowrap;
}
</style>

<div class="container-fluid">

<div class="row mb-4 align-items-center dash-page-header">
    <div class="col-md-9">
        <h4 class="fw-bold mb-1">
            Clinic & Diagnostic Dashboard
        </h4>
        <p class="text-muted mb-0">
            <i class="ri-calendar-event-line align-middle me-1"></i>
            {{ \Carbon\Carbon::today()->format('l, d F Y') }} &mdash; here's what's happening today.
        </p>
    </div>
    <div class="col-md-3">
        <div id="liveUsersBar" class="live-users-bar" style="display:none;">
            <div class="live-users-label">
                <span class="live-dot"></span>
                Live Now
            </div>
            <div class="live-users-track-wrap">
                <div id="liveUsersTrack" class="live-users-track"></div>
            </div>
        </div>
    </div>
</div>



<div class="card dash-card mb-4">

    <div class="dash-section-header">
        <span class="dash-section-icon bg-primary-subtle text-primary">
            <i class="ri-file-list-3-line"></i>
        </span>
        <h5>Today's Invoices By Me</h5>
    </div>

    <div class="card-body">

        <div class="row g-3 row-cols-xl-5 row-cols-lg-3 row-cols-md-2 row-cols-sm-1">

            @foreach($myInvoiceSummary as $tile)

            <div class="col">

                <div class="card stat-card h-100 mb-0">

                    <div class="card-body">

                        <div class="d-flex align-items-center justify-content-between mb-3">

                            <span class="stat-icon-badge bg-{{ $tile['color'] }}-subtle text-{{ $tile['color'] }}">
                                <i class="{{ $tile['icon'] }}"></i>
                            </span>

                            <span class="stat-label text-muted">
                                {{ $tile['label'] }}
                            </span>

                        </div>

                        <div class="d-flex align-items-end justify-content-between">

                            <div>
                                <div class="stat-value">
                                    {{ $tile['count'] }}
                                </div>
                                <small class="text-muted">Transactions</small>
                            </div>

                            <div class="text-end">
                                <div class="fw-bold text-{{ $tile['color'] }}">
                                    ₹ {{ number_format($tile['amount'], 0) }}
                                </div>
                                <small class="text-muted">{{ $tile['amount_caption'] }}</small>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

            @endforeach

        </div>

    </div>
</div>

    <div class="card dash-card mb-4">

<div class="dash-section-header">
    <span class="dash-section-icon bg-warning-subtle text-warning">
        <i class="ri-flashlight-line"></i>
    </span>
    <h5>Quick Actions</h5>
</div>

<div class="card-body">

<div class="row g-4">


    <div class="col-lg-2 col-md-4 col-sm-6">

        <a href="{{ route('doctor-appointments.index') }}"
           class="text-decoration-none">

            <div class="card border shadow-sm h-100 quick-card">

                <div class="card-body text-center">

                    <div class="avatar-lg mx-auto mb-3">

                        <div class="avatar-title bg-primary rounded-circle fs-1">
                            <i class="ri-stethoscope-fill"></i>
                        </div>

                    </div>

                    <h5 class="fs-15 mb-0">
                        Doctor Appointment
                    </h5>

                </div>

            </div>

        </a>

    </div>
     <div class="col-lg-2 col-md-4 col-sm-6">

        <a href="/doctor-visit-invoice"
           class="text-decoration-none">

            <div class="card border shadow-sm h-100 quick-card">

                <div class="card-body text-center">

                    <div class="avatar-lg mx-auto mb-3">

                        <div class="avatar-title bg-success rounded-circle fs-1">
                            <i class="ri-stethoscope-fill"></i>
                        </div>

                    </div>

                    <h5 class="fs-15 mb-0">
                        Doctor Visit
                    </h5>

                </div>

            </div>

        </a>

    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">

        <a href="/diagnostic-invoice"
           class="text-decoration-none">

            <div class="card border shadow-sm h-100 quick-card">

                <div class="card-body text-center">

                    <div class="avatar-lg mx-auto mb-3">

                        <div class="avatar-title bg-info rounded-circle fs-1">
                            <i class="ri-test-tube-fill"></i>
                        </div>

                    </div>

                    <h5 class="fs-15 mb-0">
                        Diagnostic
                    </h5>

                </div>

            </div>

        </a>

    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">

        <a href="/doctor-settlement-by-user"
           class="text-decoration-none">

            <div class="card border shadow-sm h-100 quick-card">

                <div class="card-body text-center">

                    <div class="avatar-lg mx-auto mb-3">

                        <div class="avatar-title bg-warning rounded-circle fs-1">
                            <i class="ri-gas-station-line"></i>
                        </div>

                    </div>

                    <h5 class="fs-15 mb-0">
                        Doctor Settlement
                    </h5>

                </div>

            </div>

        </a>

    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">

        <a href="/equipment-rental"
           class="text-decoration-none">

            <div class="card border shadow-sm h-100 quick-card">

                <div class="card-body text-center">

                    <div class="avatar-lg mx-auto mb-3">

                        <div class="avatar-title bg-danger rounded-circle fs-1">
                            <i class="ri-book-line"></i>
                        </div>

                    </div>

                    <h5 class="fs-15 mb-0">
                        Oxygen Rent
                    </h5>

                </div>

            </div>

        </a>

    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">

        <a href="/patient-history"
           class="text-decoration-none">

            <div class="card border shadow-sm h-100 quick-card">

                <div class="card-body text-center">

                    <div class="avatar-lg mx-auto mb-3">

                        <div class="avatar-title bg-dark rounded-circle fs-1">
                            <i class="ri-nurse-line"></i>
                        </div>

                    </div>

                    <h5 class="fs-15 mb-0">
                        Patient History
                    </h5>

                </div>

            </div>

        </a>

    </div>




</div>

</div>

</div>

<div class="card dash-card mb-4">

    <div class="dash-section-header">
        <span class="dash-section-icon bg-info-subtle text-info">
            <i class="ri-service-line"></i>
        </span>
        <h5>Today's Services</h5>
    </div>

    <div class="card-body">

        <div class="row g-3 row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-xl-8">

    @php
        $services = [
            ['label' => 'Patients',   'value' => $totalPatientsToday, 'icon' => 'ri-group-line',       'color' => 'primary'],
            ['label' => 'USG',        'value' => $todayUSG,           'icon' => 'ri-pulse-line',       'color' => 'success'],
            ['label' => 'X-Ray',      'value' => $todayXray,          'icon' => 'ri-scan-line',        'color' => 'danger'],
            ['label' => 'Pathology',  'value' => $todayPathology,     'icon' => 'ri-flask-line',       'color' => 'info'],
            ['label' => 'Endoscopy',  'value' => $todayEndoscopy,     'icon' => 'ri-capsule-line',     'color' => 'warning'],
            ['label' => 'Dental',     'value' => $todayDental,        'icon' => 'ri-tooth-line',       'color' => 'secondary'],
            ['label' => 'Eye',        'value' => $todayEye,           'icon' => 'ri-eye-line',         'color' => 'dark'],
            ['label' => 'Cardiology', 'value' => $todayCardiology,    'icon' => 'ri-heart-pulse-line', 'color' => 'primary'],
        ];
    @endphp

    @foreach($services as $service)

    <div class="col">
        <div class="card service-tile h-100 mb-0">
            <div class="card-body text-center">
                <span class="service-icon bg-{{ $service['color'] }}-subtle text-{{ $service['color'] }}">
                    <i class="{{ $service['icon'] }}"></i>
                </span>
                <h3 class="mb-0 fw-bold">{{ $service['value'] }}</h3>
                <small class="text-muted">{{ $service['label'] }}</small>
            </div>
        </div>
    </div>

    @endforeach

             </div>

    </div>

</div>

<div class="row g-3 mb-4">

    @php
        $summaryTiles = [
            ['label' => 'Doctors Today',      'value' => $todayDoctorCount,   'icon' => 'ri-user-heart-line',     'color' => 'primary'],
            ['label' => 'Bookings Today',     'value' => $todayBookingCount,  'icon' => 'ri-calendar-check-line', 'color' => 'success'],
            ['label' => 'Doctors Scheduled',  'value' => $futureDoctorCount,  'icon' => 'ri-stethoscope-line',    'color' => 'info'],
            ['label' => 'Future Bookings',    'value' => $futureBookingCount, 'icon' => 'ri-file-list-3-line',    'color' => 'danger'],
        ];
    @endphp

    @foreach($summaryTiles as $tile)

    <div class="col-md-3">

        <div class="card summary-tile h-100 mb-0">

            <div class="card-body d-flex align-items-center gap-3">

                <span class="summary-icon bg-{{ $tile['color'] }}-subtle text-{{ $tile['color'] }}">
                    <i class="{{ $tile['icon'] }}"></i>
                </span>

                <div>
                    <h2 class="mb-0 fw-bold">{{ $tile['value'] }}</h2>
                    <small class="text-muted">{{ $tile['label'] }}</small>
                </div>

            </div>

        </div>

    </div>

    @endforeach

</div>


    <div class="card dash-card mb-4">

<div class="dash-section-header">
    <span class="dash-section-icon bg-success-subtle text-success">
        <i class="ri-calendar-2-line"></i>
    </span>
    <h5>Doctor Wise Appointment Schedule</h5>
</div>


<div class="card-body">

@php
    $columnColors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'dark'];
    $doctorColor = $columnColors[0];
    $mobileColor = $columnColors[1 % count($columnColors)];

    $dayColumnColors = [];
    $col = 2;
    foreach ($scheduleDays as $idx => $day) {
        $bookedColor = $columnColors[$col % count($columnColors)];
        $col++;
        // Today's "Patients Booked" / "Patients Confirmed" are a single
        // logical pair (the only day with two sub-columns) -- same color,
        // not two different ones from the palette.
        $confirmedColor = $idx === 0 ? $bookedColor : null;
        $dayColumnColors[$idx] = ['booked' => $bookedColor, 'confirmed' => $confirmedColor];
    }
@endphp

 <table class="table table-hover align-middle w-100 mb-0">

<thead>

<tr>

<th rowspan="2" class="align-middle bg-{{ $doctorColor }}-subtle text-{{ $doctorColor }}">Doctor</th>

<th rowspan="2" class="align-middle bg-{{ $mobileColor }}-subtle text-{{ $mobileColor }}">Mobile</th>

@foreach($scheduleDays as $index => $day)

    <th colspan="{{ $index === 0 ? 2 : 1 }}" class="text-center bg-{{ $dayColumnColors[$index]['booked'] }}-subtle text-{{ $dayColumnColors[$index]['booked'] }}">
        {{ $day['label'] }}<br>
        <small>{{ $day['display_date'] }}</small>
    </th>

@endforeach

</tr>

<tr>

@foreach($scheduleDays as $index => $day)

    <th class="text-center bg-{{ $dayColumnColors[$index]['booked'] }}-subtle text-{{ $dayColumnColors[$index]['booked'] }}">Patients Booked</th>

    @if($index === 0)
    <th class="text-center bg-{{ $dayColumnColors[$index]['confirmed'] }}-subtle text-{{ $dayColumnColors[$index]['confirmed'] }}">Patients Confirmed</th>
    @endif

@endforeach

</tr>

</thead>

<tbody>

@forelse($doctorAppointments as $row)

<tr>

<td class="bg-{{ $doctorColor }}-subtle">
    <span class="fw-bold text-{{ $doctorColor }}">
        {{ $row['doctor_name'] }}
    </span>
</td>

<td class="bg-{{ $mobileColor }}-subtle">
    {{ $row['mobile_no'] }}
</td>

@foreach($row['days'] as $index => $day)

    <td class="text-center bg-{{ $dayColumnColors[$index]['booked'] }}-subtle">
        @if($day['booked'] > 0)
        <span class="badge bg-{{ $dayColumnColors[$index]['booked'] }} fs-6">
            {{ $day['booked'] }}
        </span>
        @endif
    </td>

    @if($index === 0)
    <td class="text-center bg-{{ $dayColumnColors[$index]['confirmed'] }}-subtle">
        @if($row['confirmed_today'] > 0)
        <span class="badge bg-{{ $dayColumnColors[$index]['confirmed'] }} fs-6">
            {{ $row['confirmed_today'] }}
        </span>
        @endif
    </td>
    @endif

@endforeach

</tr>

@empty

<tr>
    <td colspan="{{ 2 + $scheduleDays->count() + 1 }}" class="text-center text-muted">
        No upcoming appointments.
    </td>
</tr>

@endforelse

</tbody>

<tfoot>

<tr>

<th rowspan="2" class="align-middle bg-{{ $doctorColor }}-subtle text-{{ $doctorColor }}">Doctor</th>

<th rowspan="2" class="align-middle bg-{{ $mobileColor }}-subtle text-{{ $mobileColor }}">Mobile</th>

@foreach($scheduleDays as $index => $day)

    <th colspan="{{ $index === 0 ? 2 : 1 }}" class="text-center bg-{{ $dayColumnColors[$index]['booked'] }}-subtle text-{{ $dayColumnColors[$index]['booked'] }}">
        {{ $day['label'] }}<br>
        <small>{{ $day['display_date'] }}</small>
    </th>

@endforeach

</tr>

<tr>

@foreach($scheduleDays as $index => $day)

    <th class="text-center bg-{{ $dayColumnColors[$index]['booked'] }}-subtle text-{{ $dayColumnColors[$index]['booked'] }}">Patients Booked</th>

    @if($index === 0)
    <th class="text-center bg-{{ $dayColumnColors[$index]['confirmed'] }}-subtle text-{{ $dayColumnColors[$index]['confirmed'] }}">Patients Confirmed</th>
    @endif

@endforeach

</tr>

</tfoot>

</table>

</div>

</div>


<div class="card dash-card mb-4">

<div class="dash-section-header">
    <span class="dash-section-icon bg-secondary-subtle text-secondary">
        <i class="ri-exchange-dollar-line"></i>
    </span>
    <h5>Recent Transactions</h5>
</div>

<div class="card-body">

<table class="table table-striped align-middle mb-0">

<thead>

<tr>

<th>Date</th>

<th>Invoice</th>

<th>Patient</th>

<th>Amount</th>

<th>Mode</th>

</tr>

</thead>

<tbody>

@foreach($recentTransactions as $row)

<tr>

<td>{{ $row->transaction_date }}</td>

<td>{{ $row->invoice_reference }}</td>

<td>{{ $row->patient_name }}</td>

<td class="text-end">
₹ {{ number_format($row->received_amount,2) }}
</td>

<td>{{ $row->payment_mode }}</td>

</tr>

@endforeach

</tbody>

</table>

</div>

</div>




 <div class="card dash-card mb-4">

<div class="dash-section-header">
    <span class="dash-section-icon bg-danger-subtle text-danger">
        <i class="ri-bar-chart-grouped-line"></i>
    </span>
    <h5>Today's Invoice Trend</h5>
</div>

<div class="card-body">

<div id="invoiceChart"></div>

</div>

</div>



@endsection


@section('script')

<script src="{{ URL::asset('build/libs/apexcharts/apexcharts.min.js') }}"></script>
<script src="{{ URL::asset('build/js/app.js') }}"></script>

<script>

var options = {

    series: [{
        name: 'Invoices',
        data: [
            @foreach($todayInvoices as $row)
                {{ $row->total_count }},
            @endforeach
        ]
    }],

    chart: {
        height: 350,
        type: 'bar',
        toolbar: { show: false }
    },

    plotOptions: {
        bar: {
            borderRadius: 6,
            columnWidth: '45%'
        }
    },

    dataLabels: { enabled: false },

    colors: ['#0d6efd'],

    xaxis: {
    categories: [
        @foreach($todayInvoices as $row)
            '{{ str_replace('_', ' ', $row->invoice_type) }}',
        @endforeach
    ]
}

};

new ApexCharts(
    document.querySelector("#invoiceChart"),
    options
).render();

</script>

<script>
(function () {

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function loadLiveUsers() {

        fetch('{{ route('liveUsers') }}')
            .then(res => res.json())
            .then(data => {

                var bar = document.getElementById('liveUsersBar');
                var track = document.getElementById('liveUsersTrack');
                var wrap = track.parentElement;

                if (!data.status || !data.users || !data.users.length) {
                    bar.style.display = 'none';
                    return;
                }

                var chips = data.users.map(function (user) {
                    return '<span class="live-user-chip">' +
                        '<img src="' + escapeHtml(user.avatar_url) + '" alt="' + escapeHtml(user.name) + '">' +
                        '<span>' + escapeHtml(user.name) + '</span>' +
                        '</span>';
                }).join('');

                // Only duplicate (for the seamless CSS scroll loop) once the
                // chips actually overflow this container and need to
                // scroll -- measured directly rather than a fixed user
                // count, since this bar is a narrow 1/4-width column (not
                // the full-width bar it originally was), so the same
                // number of users can overflow here when it wouldn't have
                // before.
                track.style.animation = 'none';
                track.innerHTML = chips;
                bar.style.display = 'flex';

                var needsScroll = track.scrollWidth > wrap.clientWidth;

                if (needsScroll) {
                    track.innerHTML = chips + chips;
                    track.style.animation = '';
                }
            })
            .catch(function () {});
    }

    loadLiveUsers();
    setInterval(loadLiveUsers, 5 * 60 * 1000);

})();
</script>

<style>

.page-content{
    background:var(--vz-body-bg);
    min-height:100vh;
}

.dash-page-header h4{
    letter-spacing:.2px;
}

.dash-card{
    border:none;
    border-radius:16px;
    box-shadow:0 .125rem .75rem rgba(0,0,0,.06);
    overflow:hidden;
}

.dash-section-header{
    display:flex;
    align-items:center;
    gap:12px;
    padding:18px 22px;
    border-bottom:1px solid var(--vz-border-color);
}

.dash-section-header h5{
    margin:0;
    font-weight:700;
}

.dash-section-icon{
    display:flex;
    align-items:center;
    justify-content:center;
    width:40px;
    height:40px;
    min-width:40px;
    border-radius:12px;
    font-size:19px;
}

.stat-card{
    border-radius:16px;
    border:1px solid var(--vz-border-color);
    transition:transform .25s ease, box-shadow .25s ease;
}

.stat-card:hover{
    transform:translateY(-4px);
    box-shadow:0 .75rem 1.5rem rgba(0,0,0,.08);
}

.stat-icon-badge{
    width:46px;
    height:46px;
    border-radius:13px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:21px;
}

.stat-value{
    font-size:1.85rem;
    font-weight:800;
    line-height:1.1;
}

.stat-label{
    font-size:11.5px;
    text-transform:uppercase;
    letter-spacing:.5px;
    font-weight:700;
}

.quick-card{
    border-radius:16px !important;
    transition:transform .25s ease, box-shadow .25s ease;
}

.quick-card:hover{
    transform:translateY(-6px);
    box-shadow:0 1rem 2rem rgba(0,0,0,.10) !important;
}

.quick-card .avatar-title{
    transition:transform .25s ease;
}

.quick-card:hover .avatar-title{
    transform:scale(1.1);
}

.service-tile{
    border-radius:14px;
    border:1px solid var(--vz-border-color);
    transition:transform .2s ease, box-shadow .2s ease;
}

.service-tile:hover{
    transform:translateY(-3px);
    box-shadow:0 .5rem 1rem rgba(0,0,0,.08);
}

.service-icon{
    width:42px;
    height:42px;
    border-radius:11px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:19px;
    margin:0 auto 10px;
}

.summary-tile{
    border-radius:16px;
    border:1px solid var(--vz-border-color);
    transition:transform .25s ease, box-shadow .25s ease;
}

.summary-tile:hover{
    transform:translateY(-3px);
    box-shadow:0 .5rem 1.25rem rgba(0,0,0,.08);
}

.summary-icon{
    width:52px;
    height:52px;
    min-width:52px;
    border-radius:14px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:24px;
}

</style>
@endsection
