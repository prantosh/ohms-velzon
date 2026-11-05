@extends('layouts.master')

@section('title')
    Doctor Unavailable Dates
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/flatpickr/flatpickr.min.css') }}" rel="stylesheet" type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Appointment
    @endslot

    @slot('title')
        Doctor Unavailable Dates
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-calendar-close-line"></i>
            Dates on which a doctor's schedule has been disabled due to non-availability. No new appointments
            can be booked for that doctor on these dates. Dates are added or re-enabled from
            <b>Doctor &gt; Doctor Schedules</b>.
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Filter</h5>
            </div>

            <div class="card-body">

                <form method="GET" action="{{ route('doctor-unavailable-dates.index') }}">

                    <div class="row align-items-end">

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Doctor</label>
                            <select name="doctor_id" class="form-select">
                                <option value="">-- All Doctors --</option>
                                @foreach($doctors as $doctor)
                                <option value="{{ $doctor->id }}" @selected(request('doctor_id') == $doctor->id)>{{ $doctor->doctor_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 mb-3">
                            <label class="form-label">From Date</label>
                            <input type="text" name="from_date" class="form-control flatpickr" value="{{ request('from_date') }}">
                        </div>

                        <div class="col-md-2 mb-3">
                            <label class="form-label">To Date</label>
                            <input type="text" name="to_date" class="form-control flatpickr" value="{{ request('to_date') }}">
                        </div>

                        <div class="col-md-2 mb-3">
                            <label class="form-label">Show</label>
                            <select name="scope" class="form-select">
                                <option value="upcoming" @selected($scope === 'upcoming')>Upcoming only</option>
                                <option value="all" @selected($scope === 'all')>All (incl. past)</option>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-search-line"></i>
                                Search
                            </button>
                            <a href="{{ route('doctor-unavailable-dates.index') }}" class="btn btn-light">
                                Reset
                            </a>
                        </div>

                    </div>

                </form>

            </div>

        </div>

        <div class="card">

            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Disabled Dates</h5>
                <span class="badge bg-danger">{{ $exceptions->count() }} date(s)</span>
            </div>

            <div class="card-body">

                @if($exceptions->isEmpty())

                    <div class="alert alert-warning mb-0">
                        No disabled dates found for the selected filter.
                    </div>

                @else

                <div class="table-responsive">

                    <table class="table table-bordered table-hover align-middle mb-0">

                        <thead class="table-light">
                            <tr>
                                <th width="50">#</th>
                                <th>Doctor</th>
                                <th width="130">Disabled Date</th>
                                <th width="100">Day</th>
                                <th>Reason</th>
                                <th width="130" class="text-center">Booked Appts.</th>
                                <th width="150">Disabled By</th>
                                <th width="170">Disabled On</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($exceptions as $exception)
                            @php $isPast = $exception->exception_date->lt(\Carbon\Carbon::today()); @endphp
                            <tr class="{{ $isPast ? 'text-muted' : '' }}">
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $exception->doctor->doctor_name ?? ('Doctor #' . $exception->doctor_id) }}</td>
                                <td>
                                    {{ $exception->exception_date->format('d-m-Y') }}
                                    @if($exception->exception_date->isToday())
                                        <span class="badge bg-warning text-dark ms-1">Today</span>
                                    @elseif($isPast)
                                        <span class="badge bg-light text-muted ms-1">Past</span>
                                    @endif
                                </td>
                                <td>{{ $exception->exception_date->format('l') }}</td>
                                <td>{{ $exception->reason ?: '—' }}</td>
                                <td class="text-center">
                                    @if($exception->booked_count > 0)
                                        <span class="badge bg-danger" title="Booked appointments still on this date -- reassign or cancel them">
                                            {{ $exception->booked_count }}
                                        </span>
                                    @else
                                        <span class="badge bg-success">0</span>
                                    @endif
                                </td>
                                <td>{{ $exception->creator->name ?? '—' }}</td>
                                <td>{{ $exception->created_at ? $exception->created_at->format('d-m-Y h:i A') : '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>

                    </table>

                </div>

                @endif

            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

@endsection
