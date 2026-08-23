@extends('layouts.master')

@section('title')
    Doctor History
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

.doctor-info-card{
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
            <h4 class="fw-bold mb-1">Doctor History</h4>
            <p class="text-muted mb-0">
                Select a doctor to view their appointments, consultation invoices, diagnostic test involvement, and settlements.
            </p>
        </div>
    </div>

    <div class="card dash-card mb-4">

        <div class="card-body">

            <div class="row align-items-end g-3">

                <div class="col-md-5">
                    <label class="form-label fw-semibold">Select Doctor</label>
                    <select id="doctorSelect" class="form-select">
                        <option value="">-- Select Doctor --</option>
                        @foreach($doctors as $d)
                        <option value="{{ $d->id }}"
                                data-doctor-name="{{ $d->doctor_name }}"
                                data-doctor-code="{{ $d->doctor_code }}"
                                data-qualification="{{ $d->qualification }}"
                                data-specialisation="{{ $d->specialisation }}">
                            {{ $d->doctor_name }} ({{ $d->specialisation ?? 'N/A' }})
                        </option>
                        @endforeach
                    </select>
                </div>

            </div>

        </div>

    </div>

    <div id="historyWrap" style="display:none;">

        <div class="card doctor-info-card mb-4">

            <div class="card-body">

                <div class="row align-items-center">

                    <div class="col-md-8">
                        <h5 class="mb-1">
                            <span id="info-name"></span>
                        </h5>
                        <div class="text-muted">
                            <span id="info-code"></span>
                            &middot;
                            <span id="info-qualification"></span>
                            &middot;
                            <span id="info-specialisation"></span>
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

        <!-- APPOINTMENTS -->

        <div class="card dash-card mb-4">

            <div class="dash-section-header">
                <span class="dash-section-icon bg-primary-subtle text-primary">
                    <i class="ri-calendar-2-line"></i>
                </span>
                <h5>Appointments</h5>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Mobile</th>
                            <th>Token</th>
                            <th>Fee</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="appointmentsBody"></tbody>
                </table>
            </div>

        </div>

        <!-- DOCTOR VISIT (CONSULTATION) INVOICES -->

        <div class="card dash-card mb-4">

            <div class="dash-section-header">
                <span class="dash-section-icon bg-info-subtle text-info">
                    <i class="ri-stethoscope-line"></i>
                </span>
                <h5>Doctor Visit (Consultation) Invoices</h5>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice No</th>
                            <th>Patient</th>
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

        <!-- DIAGNOSTIC TEST INVOLVEMENT -->

        <div class="card dash-card mb-4">

            <div class="dash-section-header">
                <span class="dash-section-icon bg-warning-subtle text-warning">
                    <i class="ri-test-tube-line"></i>
                </span>
                <h5>Diagnostic Test Involvement</h5>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice No</th>
                            <th>Patient</th>
                            <th>Test</th>
                            <th>Test Amount</th>
                            <th>Doctor's Share</th>
                            <th>Invoice Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="diagnosticBody"></tbody>
                </table>
            </div>

        </div>

        <!-- SETTLEMENTS / PAYMENTS RECEIVED -->

        <div class="card dash-card mb-4">

            <div class="dash-section-header">
                <span class="dash-section-icon bg-success-subtle text-success">
                    <i class="ri-wallet-3-line"></i>
                </span>
                <h5>Settlements / Payments Received</h5>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Settlement No</th>
                            <th>Gross Amount</th>
                            <th>Deduction</th>
                            <th>Net Amount</th>
                            <th>Payment Mode</th>
                        </tr>
                    </thead>
                    <tbody id="settlementsBody"></tbody>
                </table>
            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/doctor-activity-history.init.js') }}"></script>

@endsection
