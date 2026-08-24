@extends('layouts.master')

@section('title')
    Doctor Payable Dashboard (By User)
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Admin
    @endslot

    @slot('title')
        Doctor Payable Dashboard (By User)
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-user-search-line"></i>
            Select a user to see a day-by-day summary of doctor payables generated from their invoices,
            split into Settled and Unsettled. Click "Show Detail" on any date to see every payable
            (settled and unsettled) for that day, with a total.
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Select User</h5>
            </div>

            <div class="card-body">

                <div class="row align-items-end">

                    <div class="col-md-4 mb-3">
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
                        <label class="form-label">Date Range</label>
                        <select id="range-field" class="form-select">
                            <option value="3">Last 3 Days</option>
                            <option value="7">Last 7 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                            <option value="6m">Last 6 Months</option>
                            <option value="all">All Time</option>
                        </select>
                    </div>

                    <div class="col-md-2 mb-3">
                        <button type="button" class="btn btn-primary" id="loadReportBtn">
                            <i class="ri-search-line"></i>
                            Load
                        </button>
                    </div>

                </div>

            </div>

        </div>

        <div class="row" id="summaryRow" style="display:none;">

            <div class="col-md-3 mb-3">
                <div class="card h-100"><div class="card-body text-center">
                    <h5 class="mb-0" id="summary-settled_count">0</h5>
                    <small class="text-muted" id="summary-settled_count_label">Settled Payables</small>
                </div></div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card h-100 border border-success"><div class="card-body text-center">
                    <h5 class="mb-0 text-success" id="summary-settled_amount">&#8377;0.00</h5>
                    <small class="text-muted fw-semibold" id="summary-settled_amount_label">Settled Amount</small>
                </div></div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card h-100"><div class="card-body text-center">
                    <h5 class="mb-0" id="summary-unsettled_count">0</h5>
                    <small class="text-muted" id="summary-unsettled_count_label">Unsettled Payables</small>
                </div></div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card h-100 border border-danger"><div class="card-body text-center">
                    <h5 class="mb-0 text-danger" id="summary-unsettled_amount">&#8377;0.00</h5>
                    <small class="text-muted fw-semibold" id="summary-unsettled_amount_label">Unsettled Amount</small>
                </div></div>
            </div>

        </div>

        <div class="card" id="dailySummaryCard" style="display:none;">

            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="card-title mb-0" id="dailySummaryCardTitle">Doctor Payables &mdash; Daily Summary</h5>
                <a href="javascript:void(0)" id="printDailySummaryBtn" class="btn btn-success btn-sm" target="_blank">
                    <i class="ri-printer-line"></i>
                    Print Summary PDF
                </a>
            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th width="130">Settled Payables</th>
                                <th width="150">Settled Amount (&#8377;)</th>
                                <th width="140">Unsettled Payables</th>
                                <th width="150">Unsettled Amount (&#8377;)</th>
                                <th width="120">Detail</th>
                            </tr>
                        </thead>

                        <tbody id="dailySummaryTableBody"></tbody>

                    </table>

                </div>

                <div id="noDailySummaryWrap" class="text-muted text-center py-2" style="display:none;">
                    No doctor payables found in this range.
                </div>

            </div>

        </div>

        <div id="dayDetailWrap" style="display:none;">

            <div class="card">

                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="card-title mb-0" id="dayDetailTitle">Doctor Payables Detail</h5>
                    <div class="d-flex gap-2">
                        <a href="javascript:void(0)" id="printDayDetailBtn" class="btn btn-success btn-sm" target="_blank">
                            <i class="ri-printer-line"></i>
                            Print PDF
                        </a>
                        <button type="button" class="btn btn-light btn-sm" id="backToSummaryBtn">
                            <i class="ri-arrow-left-line"></i>
                            Back to Summary
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row" id="dayDetailTotals"></div>
                </div>

            </div>

            <div class="card">

                <div class="card-header">
                    <h5 class="card-title mb-0">Settled Doctor Payables</h5>
                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-bordered align-middle">

                            <thead class="table-light">
                                <tr>
                                    <th>Payable No</th>
                                    <th>Invoice No</th>
                                    <th>Category</th>
                                    <th class="user-col" style="display:none;">User</th>
                                    <th>Doctor</th>
                                    <th>Patient</th>
                                    <th>Item</th>
                                    <th width="100">Payable (&#8377;)</th>
                                    <th width="100">Paid (&#8377;)</th>
                                    <th>Settlement No</th>
                                    <th width="100">Settled Date</th>
                                    <th width="80">Settlements</th>
                                    <th width="60">Print</th>
                                    <th width="60">Slip</th>
                                </tr>
                            </thead>

                            <tbody id="settledTableBody"></tbody>

                        </table>

                    </div>

                    <div id="noSettledWrap" class="text-muted text-center py-2" style="display:none;">
                        No settled doctor payables found for this day.
                    </div>

                </div>

            </div>

            <div class="card">

                <div class="card-header">
                    <h5 class="card-title mb-0">Unsettled Doctor Payables</h5>
                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-bordered align-middle">

                            <thead class="table-light">
                                <tr>
                                    <th>Payable No</th>
                                    <th>Invoice No</th>
                                    <th>Category</th>
                                    <th class="user-col" style="display:none;">User</th>
                                    <th>Doctor</th>
                                    <th>Patient</th>
                                    <th>Item</th>
                                    <th width="90">Gross (&#8377;)</th>
                                    <th width="90">Payable (&#8377;)</th>
                                    <th width="90">Paid (&#8377;)</th>
                                    <th width="90">Balance (&#8377;)</th>
                                    <th width="90">Status</th>
                                    <th width="130">Date</th>
                                    <th width="60">Print</th>
                                </tr>
                            </thead>

                            <tbody id="pendingTableBody"></tbody>

                        </table>

                    </div>

                    <div id="noPendingWrap" class="text-muted text-center py-2" style="display:none;">
                        No unsettled doctor payables found for this day.
                    </div>

                </div>

            </div>

        </div>

        <div id="noDataWrap" class="alert alert-warning" style="display:none;">
            Please select a user and click Load.
        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/doctor-payable-by-user.init.js') }}"></script>

@endsection
