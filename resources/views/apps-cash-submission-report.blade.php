@extends('layouts.master')

@section('title')
    Daily Cash Submission Report
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
        Admin
    @endslot

    @slot('title')
        Daily Cash Submission Report
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-cash-line"></i>
            Select a user and date to compute how much cash that user needs to submit to accounts for the day &mdash;
            cash collected on invoices, minus cash refunds, minus any doctor consultation fee paid out in cash
            (even if the original invoice itself was collected online).
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Select User &amp; Date</h5>
            </div>

            <div class="card-body">

                <div class="row align-items-end">

                    <div class="col-md-6 mb-3">
                        <label class="form-label">User <span class="text-danger">*</span></label>
                        <select id="user_id-field" class="form-select" required>
                            <option value="">-- Select User --</option>
                            @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="text" id="date-field" class="form-control flatpickr" required>
                    </div>

                </div>

            </div>

        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

            <ul class="nav nav-tabs nav-tabs-custom mb-0" id="cashReportTabs" role="tablist">

                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#detailTabPane" role="tab" id="detailTabLink">
                        <i class="ri-file-list-3-line align-middle me-1"></i>
                        Load Detail Transaction
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#summaryTabPane" role="tab" id="summaryTabLink">
                        <i class="ri-bar-chart-box-line align-middle me-1"></i>
                        Load Category Wise Summary
                    </a>
                </li>

            </ul>

            <div>
                <a href="javascript:void(0)" id="printDetailBtn" class="btn btn-success" style="display:none;" target="_blank">
                    <i class="ri-printer-line"></i>
                    Print Detail Transaction
                </a>
                <a href="javascript:void(0)" id="printSummaryBtn" class="btn btn-success" style="display:none;" target="_blank">
                    <i class="ri-printer-line"></i>
                    Print Category Wise Summary
                </a>
            </div>

        </div>

        <div class="tab-content">

        <div class="tab-pane active" id="detailTabPane" role="tabpanel">

        <div class="row" id="summaryRow" style="display:none;">

            <div class="col-md-4 col-xl-2 mb-3">
                <div class="card h-100"><div class="card-body text-center">
                    <h5 class="mb-0 text-success" id="summary-cash_collected">&#8377;0.00</h5>
                    <small class="text-muted">Cash Collected</small>
                </div></div>
            </div>

            <div class="col-md-4 col-xl-2 mb-3">
                <div class="card h-100"><div class="card-body text-center">
                    <h5 class="mb-0 text-info" id="summary-non_cash_collected">&#8377;0.00</h5>
                    <small class="text-muted">Non-Cash Collected (Online/Card/UPI)</small>
                </div></div>
            </div>

            <div class="col-md-4 col-xl-2 mb-3">
                <div class="card h-100"><div class="card-body text-center">
                    <h5 class="mb-0 text-danger" id="summary-cash_refunded">&#8377;0.00</h5>
                    <small class="text-muted">Cash Refunded</small>
                </div></div>
            </div>

            <div class="col-md-4 col-xl-2 mb-3">
                <div class="card h-100"><div class="card-body text-center">
                    <h5 class="mb-0 text-danger" id="summary-cash_paid_to_doctors">&#8377;0.00</h5>
                    <small class="text-muted">Cash Paid To Doctors</small>
                </div></div>
            </div>

            <div class="col-md-4 col-xl-2 mb-3">
                <div class="card h-100 border border-success"><div class="card-body text-center">
                    <h5 class="mb-0 text-success" id="summary-net_cash_to_deposit">&#8377;0.00</h5>
                    <small class="text-muted fw-semibold">Net Cash To Deposit</small>
                </div></div>
            </div>

        </div>

        <div class="row" id="detailRow" style="display:none;">

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Doctor Payments Made (Cash) By This User On This Date</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Invoice No</th>
                                        <th>Doctor</th>
                                        <th>Settlement No</th>
                                        <th width="90">Time</th>
                                        <th width="120">Amount (&#8377;)</th>
                                    </tr>
                                </thead>
                                <tbody id="doctorPaymentTableBody"></tbody>
                            </table>
                        </div>
                        <div id="noDoctorPaymentWrap" class="text-muted text-center py-2" style="display:none;">
                            No cash payments made to doctors by this user on this date.
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Group-Wise Cash Deposit Breakdown</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Reporting Group</th>
                                        <th width="150">Amount To Deposit (&#8377;)</th>
                                    </tr>
                                </thead>
                                <tbody id="groupBreakdownTableBody"></tbody>
                                <tfoot>
                                    <tr class="table-light fw-semibold">
                                        <td>Total</td>
                                        <td id="groupBreakdownTotal">&#8377;0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="card" id="listCard" style="display:none;">

            <div class="card-header">
                <h5 class="card-title mb-0">Cash Ledger</h5>
            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th>Invoice No</th>
                                <th>Category</th>
                                <th>Transaction No</th>
                                <th>Txn From/To</th>
                                <th width="100">Invoice Date</th>
                                <th width="90">Time</th>
                                <th width="120">Amount (&#8377;)</th>
                                <th width="100">Type</th>
                            </tr>
                        </thead>

                        <tbody id="listTableBody"></tbody>

                    </table>

                </div>

            </div>

        </div>

        <div id="noDataWrap" class="alert alert-warning" style="display:none;">
            No cash transactions found for this user on this date.
        </div>

        </div>

        <div class="tab-pane" id="summaryTabPane" role="tabpanel">

        <div class="card" id="categorySummaryCard" style="display:none;">

            <div class="card-header">
                <h5 class="card-title mb-0">Category Wise Summary</h5>
            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle mb-0">

                        <thead class="table-light">
                            <tr>
                                <th>Category</th>
                                <th width="130">Collection (Cash)</th>
                                <th width="130">Collection (Non-Cash)</th>
                                <th width="130">Collection (Total)</th>
                                <th width="110">Refund</th>
                                <th width="140">Payment To Doctor</th>
                                <th width="140">Amount To Deposit</th>
                            </tr>
                        </thead>

                        <tbody id="categorySummaryTableBody"></tbody>

                        <tfoot>
                            <tr class="table-light fw-bold">
                                <td>Grand Total</td>
                                <td class="text-end" id="catsummary-total-cash_collected">0.00</td>
                                <td class="text-end" id="catsummary-total-non_cash_collected">0.00</td>
                                <td class="text-end" id="catsummary-total-total_collected">0.00</td>
                                <td class="text-end" id="catsummary-total-refund">0.00</td>
                                <td class="text-end" id="catsummary-total-doctor_payment">0.00</td>
                                <td class="text-end" id="catsummary-total-amount_to_deposit">0.00</td>
                            </tr>
                        </tfoot>

                    </table>

                </div>

            </div>

        </div>

        </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/cash-submission-report.init.js') }}"></script>

@endsection
