@extends('layouts.master')

@section('title')
    Monthly Reconciliation Report
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
        Monthly Reconciliation Report
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-file-list-3-line"></i>
            Category-wise (item) cash / non-cash income, refund and doctor-payment reconciliation for a selected month.
            Refunds and doctor payments are always physically disbursed in cash. "Doctor Payment (Non-Cash Source)"
            is informational only &mdash; it is already included within Doctor Payment Cash, not a separate deduction.
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Select Month &amp; Year</h5>
            </div>

            <div class="card-body">

                <div class="row align-items-end">

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Month <span class="text-danger">*</span></label>
                        <select id="month-field" class="form-select" required>
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Year <span class="text-danger">*</span></label>
                        <select id="year-field" class="form-select" required></select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <button type="button" class="btn btn-primary w-100" id="loadReportBtn">
                            <i class="ri-search-line"></i>
                            Load
                        </button>
                    </div>

                </div>

            </div>

        </div>

        <div class="d-flex justify-content-end mb-2">
            <a href="javascript:void(0)" id="printReportBtn" class="btn btn-success" style="display:none;" target="_blank">
                <i class="ri-printer-line"></i>
                Print PDF
            </a>
        </div>

        <div class="row" id="totalsRow" style="display:none;">

            <div class="col-md-4 mb-3">
                <div class="card h-100 border border-success"><div class="card-body text-center">
                    <h5 class="mb-0 text-success" id="total-deposit_cash">&#8377;0.00</h5>
                    <small class="text-muted fw-semibold">Total Deposit in Cash</small>
                </div></div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="card h-100 border border-primary"><div class="card-body text-center">
                    <h5 class="mb-0 text-primary" id="total-total_income">&#8377;0.00</h5>
                    <small class="text-muted fw-semibold">Total Income in Month</small>
                </div></div>
            </div>

        </div>

        <div class="card" id="reportCard" style="display:none;">

            <div class="card-header">
                <h5 class="card-title mb-0">Category-wise Reconciliation</h5>
            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th>Name of Category</th>
                                <th width="110">Received Cash</th>
                                <th width="110">Received Card / Non-Cash</th>
                                <th width="110">Refund Cash</th>
                                <th width="110">Doctor Payment Cash</th>
                                <th width="130">Deposit in Cash</th>
                                <th width="150">Doctor Payment (Non-Cash Source)</th>
                                <th width="130">Total Income</th>
                            </tr>
                        </thead>

                        <tbody id="reportTableBody"></tbody>

                    </table>

                </div>

            </div>

        </div>

        <div id="noDataWrap" class="alert alert-warning" style="display:none;">
            No activity found for the selected month.
        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/monthly-reconciliation-report.init.js') }}"></script>

@endsection
