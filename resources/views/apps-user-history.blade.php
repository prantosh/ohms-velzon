@extends('layouts.master')

@section('title')
    User History
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

.user-info-card{
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
            <h4 class="fw-bold mb-1">User History</h4>
            <p class="text-muted mb-0">
                Select a staff user to view their day-by-day activity and login history.
            </p>
        </div>
    </div>

    <div class="card dash-card mb-4">

        <div class="card-body">

            <div class="row align-items-end g-3">

                <div class="col-md-5">
                    <label class="form-label fw-semibold">Select User</label>
                    <select id="userSelect" class="form-select">
                        <option value="">-- Select User --</option>
                        @foreach($users as $u)
                        <option value="{{ $u->id }}"
                                data-name="{{ $u->name }}"
                                data-role="{{ $u->role }}">
                            {{ $u->name }} ({{ $u->role }}) - {{ $u->mobile_no ?? 'N/A' }}
                        </option>
                        @endforeach
                    </select>
                </div>

            </div>

        </div>

    </div>

    <div id="historyWrap" style="display:none;">

        <div class="card user-info-card mb-4">

            <div class="card-body">

                <div class="row align-items-end g-3">

                    <div class="col-md-4">
                        <h5 class="mb-1"><span id="info-name"></span></h5>
                        <div class="text-muted"><span id="info-role"></span></div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">From Date</label>
                        <input type="date" id="fromDateInput" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">To Date</label>
                        <input type="date" id="toDateInput" class="form-control">
                    </div>

                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" id="btnLoad">
                            <i class="ri-refresh-line"></i>
                            Load
                        </button>
                    </div>

                </div>

            </div>

        </div>

        <!-- DAILY SUMMARY -->

        <div class="card dash-card mb-4">

            <div class="dash-section-header">
                <span class="dash-section-icon bg-primary-subtle text-primary">
                    <i class="ri-calendar-2-line"></i>
                </span>
                <h5>Daily Activity Summary</h5>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoices Created</th>
                            <th>Pending (Due)</th>
                            <th>Doctor Payments Settled</th>
                            <th>Cash Submitted (Net)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="dailySummaryBody"></tbody>
                </table>
            </div>

        </div>

        <!-- LOGIN / LOGOUT ACTIVITY -->

        <div class="card dash-card mb-4">

            <div class="dash-section-header">
                <span class="dash-section-icon bg-secondary-subtle text-secondary">
                    <i class="ri-login-circle-line"></i>
                </span>
                <h5>Login / Logout Activity</h5>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Login Time</th>
                            <th>Logout Time</th>
                            <th>IP Address</th>
                            <th>Browser</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="loginLogsBody"></tbody>
                </table>
            </div>

        </div>

    </div>

</div>

<!-- DAY DETAIL MODAL -->

<div class="modal fade" id="dayDetailModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-fullscreen-lg-down modal-xl">

        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Day Detail &mdash; <span id="detailModalDate"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="row mb-4" id="detailSummaryRow"></div>

                <h6 class="fw-bold mb-2">Transaction Ledger</h6>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice No</th>
                                <th>Category</th>
                                <th>Txn No</th>
                                <th>Patient</th>
                                <th>Time</th>
                                <th>Amount</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody id="detailLedgerBody"></tbody>
                    </table>
                </div>

                <h6 class="fw-bold mb-2">Doctor Payments Made</h6>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice No</th>
                                <th>Doctor</th>
                                <th>Settlement No</th>
                                <th>Time</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody id="detailDoctorPaymentsBody"></tbody>
                    </table>
                </div>

            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/user-history.init.js') }}"></script>

@endsection
