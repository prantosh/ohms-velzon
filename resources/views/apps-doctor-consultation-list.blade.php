@extends('layouts.master')

@section('title')
    Doctor Consultation List
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

<link href="{{ URL::asset('build/libs/flatpickr/flatpickr.min.css') }}" rel="stylesheet" type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Appointment
    @endslot

    @slot('title')
        Doctor Consultation List
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-file-list-3-line"></i>
            Select a doctor and date to generate the patient list for that day's consultation, showing whether
            each patient's invoice has already been prepared. Print this list to submit before consultation begins.
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Select Doctor &amp; Date</h5>
            </div>

            <div class="card-body">

                <div class="row align-items-end">

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Doctor <span class="text-danger">*</span></label>
                        <select id="doctor_id-field" class="form-select" required>
                            <option value="">-- Select Doctor --</option>
                            @foreach($doctors as $doctor)
                            <option value="{{ $doctor->id }}">{{ $doctor->doctor_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="text" id="date-field" class="form-control flatpickr" required>
                    </div>

                    <div class="col-md-2 mb-3">
                        <button type="button" class="btn btn-primary" id="loadListBtn">
                            <i class="ri-search-line"></i>
                            Load List
                        </button>
                    </div>

                    <div class="col-md-3 mb-3">
                        <a href="javascript:void(0)" id="printListBtn" class="btn btn-success" style="display:none;" target="_blank">
                            <i class="ri-printer-line"></i>
                            Print List
                        </a>
                    </div>

                </div>

            </div>

        </div>

        <div class="card" id="listCard" style="display:none;">

            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0" id="listTitle">Patient List</h5>
                <div>
                    <span class="badge bg-success" id="preparedCountBadge"></span>
                    <span class="badge bg-danger" id="notPreparedCountBadge"></span>
                </div>
            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th width="70">Token</th>
                                <th>Patient Name</th>
                                <th width="120">Mobile No</th>
                                <th width="90">Age/Gender</th>
                                <th width="110">Time</th>
                                <th width="160">Invoice Status</th>
                            </tr>
                        </thead>

                        <tbody id="listTableBody"></tbody>

                    </table>

                </div>

            </div>

        </div>

        <div id="noDataWrap" class="alert alert-warning" style="display:none;">
            No appointments found for this doctor on this date.
        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/doctor-consultation-list.init.js') }}"></script>

@endsection
