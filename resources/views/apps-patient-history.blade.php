@extends('layouts.master')

@section('title')
    Patient History
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

<style>

.dash-section-header{
    display:flex;
    align-items:center;
    gap:12px;
    padding:16px 20px;
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
    width:38px;
    height:38px;
    min-width:38px;
    border-radius:11px;
    font-size:18px;
}

.dash-card{
    border:none;
    border-radius:16px;
    box-shadow:0 .125rem .75rem rgba(0,0,0,.06);
    overflow:hidden;
}

.period-btn-group .btn{
    border-radius:8px !important;
    margin-right:4px;
}

.patient-info-card{
    border-radius:16px;
    border:1px solid var(--vz-border-color);
}

.history-empty{
    padding:24px;
    text-align:center;
    color:var(--vz-secondary-color);
    font-size:13.5px;
}

.print-link{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:30px;
    height:30px;
    border-radius:8px;
}

</style>

@endsection

@section('content')

<div class="container-fluid">

    <div class="row mb-3">
        <div class="col-12">
            <h4 class="fw-bold mb-1">Patient History</h4>
            <p class="text-muted mb-0">
                Search a patient by mobile number to view their complete visit and billing history.
            </p>
        </div>
    </div>

    <div class="card dash-card mb-4">

        <div class="card-body">

            <div class="row align-items-end g-3">

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Mobile Number</label>
                    <input type="text"
                           id="mobileInput"
                           class="form-control"
                           maxlength="10"
                           placeholder="Enter 10 digit mobile number">
                </div>

                <div class="col-md-2">
                    <button class="btn btn-primary w-100" id="btnSearchMobile">
                        <i class="ri-search-line"></i>
                        Search
                    </button>
                </div>

                <div class="col-md-4" id="patientSelectWrap" style="display:none;">
                    <label class="form-label fw-semibold">Select Patient</label>
                    <select id="patientSelect" class="form-select"></select>
                </div>

            </div>

            <div id="noPatientMsg" class="text-danger mt-3" style="display:none;">
                No patient found with this mobile number.
            </div>

        </div>

    </div>

    <div id="historyWrap" style="display:none;">

        <div class="card patient-info-card mb-4">

            <div class="card-body">

                <div class="row align-items-center">

                    <div class="col-md-8">
                        <h5 class="mb-1">
                            <span id="info-name"></span>
                            <button type="button" class="btn btn-sm btn-outline-primary ms-2" id="btnEditPatient" style="display:none;">
                                <i class="ri-edit-line"></i> Edit
                            </button>
                        </h5>
                        <div class="text-muted">
                            <span id="info-patient-id"></span>
                            &middot;
                            Age <span id="info-age"></span>
                            &middot;
                            <span id="info-gender"></span>
                            &middot;
                            Mobile <span id="info-mobile"></span>
                        </div>
                    </div>

                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <div class="btn-group period-btn-group" id="periodFilter" role="group">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-period="1">1M</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-period="2">2M</button>
                            <button type="button" class="btn btn-sm btn-primary" data-period="3">3M</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-period="6">6M</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-period="12">1Y</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-period="all">All</button>
                        </div>
                    </div>

                </div>

            </div>

        </div>

        <!-- MEMBERSHIP -->

        <div class="card dash-card mb-4" id="membershipCard" style="display:none;">

            <div class="dash-section-header">
                <span class="dash-section-icon bg-success-subtle text-success">
                    <i class="ri-vip-crown-2-line"></i>
                </span>
                <h5>Membership Details</h5>
            </div>

            <div class="card-body" id="membershipBody"></div>

        </div>

        <!-- APPOINTMENTS -->

        <div class="card dash-card mb-4">

            <div class="dash-section-header">
                <span class="dash-section-icon bg-primary-subtle text-primary">
                    <i class="ri-calendar-2-line"></i>
                </span>
                <h5>Doctor Appointments</h5>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Doctor</th>
                            <th>Token</th>
                            <th>Fee</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="appointmentsBody"></tbody>
                </table>
            </div>

        </div>

        <!-- DOCTOR VISIT INVOICES -->

        <div class="card dash-card mb-4">

            <div class="dash-section-header">
                <span class="dash-section-icon bg-info-subtle text-info">
                    <i class="ri-stethoscope-line"></i>
                </span>
                <h5>Doctor Visit Invoices</h5>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice No</th>
                            <th>Doctor</th>
                            <th>Paid</th>
                            <th>Due</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="doctorVisitBody"></tbody>
                </table>
            </div>

        </div>

        <!-- DIAGNOSTIC INVOICES -->

        <div class="card dash-card mb-4">

            <div class="dash-section-header">
                <span class="dash-section-icon bg-warning-subtle text-warning">
                    <i class="ri-test-tube-line"></i>
                </span>
                <h5>Diagnostic Tests</h5>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice No</th>
                            <th>Category</th>
                            <th>Tests</th>
                            <th>Paid</th>
                            <th>Due</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="diagnosticBody"></tbody>
                </table>
            </div>

        </div>

        <!-- EQUIPMENT RENTALS -->

        <div class="card dash-card mb-4">

            <div class="dash-section-header">
                <span class="dash-section-icon bg-danger-subtle text-danger">
                    <i class="ri-first-aid-kit-line"></i>
                </span>
                <h5>Equipment Rentals</h5>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice No</th>
                            <th>Equipment</th>
                            <th>Type</th>
                            <th>Paid</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="equipmentBody"></tbody>
                </table>
            </div>

        </div>

        <!-- AMBULANCE BOOKINGS -->

        <div class="card dash-card mb-4">

            <div class="dash-section-header">
                <span class="dash-section-icon bg-secondary-subtle text-secondary">
                    <i class="ri-taxi-line"></i>
                </span>
                <h5>Ambulance Bookings</h5>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice No</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Type</th>
                            <th>Paid</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="ambulanceBody"></tbody>
                </table>
            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/patient-history.init.js') }}"></script>

@endsection
