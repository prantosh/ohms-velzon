@extends('layouts.master')

@section('title')
    Doctor Payment Report (Visit and Test)
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
        Doctor
    @endslot

    @slot('title')
        Doctor Payment Report (Visit and Test)
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-money-rupee-circle-line"></i>
            Select a user, doctor and date to see every invoice that user billed for that doctor on that day &mdash;
            with doctor fees and clinic charge (amount received from the patient minus the amount payable to the doctor)
            broken down per item.
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Select User, Doctor &amp; Date</h5>
            </div>

            <div class="card-body">

                <div class="row align-items-end">

                    <div class="col-md-3 mb-3">
                        <label class="form-label">User <span class="text-danger">*</span></label>
                        <select id="user_id-field" class="form-select" required>
                            <option value="">-- Select User --</option>
                            <option value="ALL">-- All Users --</option>
                            @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Doctor <span class="text-danger">*</span></label>
                        <select id="doctor_id-field" class="form-select" required>
                            <option value="">-- Select Doctor --</option>
                            <option value="ALL">-- All Doctors --</option>
                            @foreach($doctors as $doctor)
                            <option value="{{ $doctor->id }}">{{ $doctor->doctor_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="text" id="date-field" class="form-control flatpickr" required>
                    </div>

                    <div class="col-md-2 mb-3">
                        <button type="button" class="btn btn-primary" id="loadReportBtn">
                            <i class="ri-search-line"></i>
                            Load Report
                        </button>
                    </div>

                    <div class="col-md-2 mb-3">
                        <a href="javascript:void(0)" id="printReportBtn" class="btn btn-success" style="display:none;" target="_blank">
                            <i class="ri-printer-line"></i>
                            Print PDF
                        </a>
                    </div>

                </div>

            </div>

        </div>

        <div class="row" id="summaryRow" style="display:none;">

            <div class="col-md-3 mb-3">
                <div class="card h-100"><div class="card-body text-center">
                    <h5 class="mb-0" id="summary-total_items">0</h5>
                    <small class="text-muted">Items</small>
                </div></div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card h-100"><div class="card-body text-center">
                    <h5 class="mb-0" id="summary-total_gross">&#8377;0.00</h5>
                    <small class="text-muted">Total Received</small>
                </div></div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card h-100 border border-danger"><div class="card-body text-center">
                    <h5 class="mb-0 text-danger" id="summary-total_doctor_fees">&#8377;0.00</h5>
                    <small class="text-muted fw-semibold">Total Doctor Fees</small>
                </div></div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card h-100 border border-success"><div class="card-body text-center">
                    <h5 class="mb-0 text-success" id="summary-total_clinic_charge">&#8377;0.00</h5>
                    <small class="text-muted fw-semibold">Total Clinic Charge</small>
                </div></div>
            </div>

        </div>

        <div class="card" id="listCard" style="display:none;">

            <div class="card-header">
                <h5 class="card-title mb-0">Doctor Payment Detail</h5>
            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th>Invoice No</th>
                                <th class="user-col" style="display:none;">User</th>
                                <th class="doctor-col" style="display:none;">Doctor</th>
                                <th>Patient Name</th>
                                <th width="110">Card No</th>
                                <th>Item Description</th>
                                <th width="90">Time</th>
                                <th width="110">Doctor Fees (&#8377;)</th>
                                <th width="110">Clinic Charge (&#8377;)</th>
                                <th width="130">Settlement No</th>
                            </tr>
                        </thead>

                        <tbody id="listTableBody"></tbody>

                    </table>

                </div>

            </div>

        </div>

        <div id="noDataWrap" class="alert alert-warning" style="display:none;">
            No invoices found for this user and doctor on this date.
        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/doctor-payment-report.init.js') }}"></script>

@endsection
