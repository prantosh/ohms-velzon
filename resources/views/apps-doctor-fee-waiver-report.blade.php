@extends('layouts.master')

@section('title')
    Doctor Fee Waiver Report
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
        Reports
    @endslot

    @slot('title')
        Doctor Fee Waiver Report
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-hand-heart-line"></i>
            Doctor visits whose consultation fee was waived (exempted by the doctor), by the date the waiver was recorded.
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Select Period</h5>
            </div>

            <div class="card-body">

                <div class="row align-items-end">

                    <div class="col-md-3 mb-3">
                        <label class="form-label">From Date <span class="text-danger">*</span></label>
                        <input type="text" id="from_date-field" class="form-control flatpickr" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">To Date <span class="text-danger">*</span></label>
                        <input type="text" id="to_date-field" class="form-control flatpickr" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Doctor</label>
                        <select id="doctor_id-field" class="form-select">
                            <option value="">All Doctors</option>
                            @foreach($doctors as $doctor)
                                <option value="{{ $doctor->id }}">{{ $doctor->doctor_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 mb-3 d-flex gap-2">
                        <button type="button" class="btn btn-primary flex-fill" id="loadReportBtn">
                            <i class="ri-search-line"></i>
                            Load
                        </button>
                        <a href="javascript:void(0)" id="printBtn" class="btn btn-success flex-fill" style="display:none;" target="_blank">
                            <i class="ri-printer-line"></i>
                            Print PDF
                        </a>
                    </div>

                </div>

            </div>

        </div>

        <div class="row" id="totalsRow" style="display:none;">

            <div class="col-md-6 mb-3">
                <div class="card h-100"><div class="card-body text-center">
                    <h5 class="mb-0" id="total-count">0</h5>
                    <small class="text-muted">Visits Waived</small>
                </div></div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="card h-100 border border-success"><div class="card-body text-center">
                    <h5 class="mb-0 text-success" id="total-amount">&#8377;0.00</h5>
                    <small class="text-muted fw-semibold">Total Fee Waived</small>
                </div></div>
            </div>

        </div>

        <div class="card" id="reportCard" style="display:none;">

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th width="50">SL</th>
                                <th>Invoice No</th>
                                <th width="100">Visit Date</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th width="90">Fee</th>
                                <th width="110">Fee Waived</th>
                                <th>Waived By</th>
                                <th width="150">Waived At</th>
                                <th>Reason</th>
                            </tr>
                        </thead>

                        <tbody id="reportTableBody"></tbody>

                    </table>

                </div>

            </div>

        </div>

        <div id="noDataWrap" class="alert alert-warning" style="display:none;">
            No waived doctor visits found for the selected period.
        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/doctor-fee-waiver-report.init.js') }}"></script>

@endsection
