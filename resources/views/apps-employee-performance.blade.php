@extends('layouts.master')

@section('title')
    Employee Performance Dashboard
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
        Employee Performance Dashboard
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-line-chart-line"></i>
            Cash/non-cash collections, refunds and doctor payments are counted against whichever employee
            <em>created</em> the invoice or recorded the settlement -- same convention as the Daily Cash Submission
            Report, just summarized over a date range instead of a single day.
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Select Duration</h5>
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

                    <div class="col-md-2 mb-3">
                        <button type="button" class="btn btn-primary w-100" id="loadReportBtn">
                            <i class="ri-search-line"></i>
                            Load Report
                        </button>
                    </div>

                    <div class="col-md-3 mb-3">
                        <a href="javascript:void(0)" id="printAllBtn" class="btn btn-success w-100" style="display:none;" target="_blank">
                            <i class="ri-printer-line"></i>
                            Print All-Employees Summary
                        </a>
                    </div>

                </div>

            </div>

        </div>

        <div class="card" id="listCard" style="display:none;">

            <div class="card-header">
                <h5 class="card-title mb-0">Employee-Wise Performance</h5>
            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Role</th>
                                <th class="text-end">Invoices</th>
                                <th class="text-end">Cash Collected</th>
                                <th class="text-end">Non-Cash Collected</th>
                                <th class="text-end">Refund</th>
                                <th class="text-end">Payment To Doctor</th>
                                <th class="text-end">Net Deposit</th>
                                <th width="90"></th>
                            </tr>
                        </thead>

                        <tbody id="employeeTableBody"></tbody>

                        <tfoot>
                            <tr class="table-light fw-semibold">
                                <td colspan="2">Total</td>
                                <td class="text-end" id="total-invoice_count">0</td>
                                <td class="text-end" id="total-cash_collected">&#8377;0.00</td>
                                <td class="text-end" id="total-non_cash_collected">&#8377;0.00</td>
                                <td class="text-end" id="total-cash_refunded">&#8377;0.00</td>
                                <td class="text-end" id="total-cash_paid_to_doctors">&#8377;0.00</td>
                                <td class="text-end" id="total-net_cash_to_deposit">&#8377;0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>

                    </table>

                </div>

            </div>

        </div>

        <div id="noDataWrap" class="alert alert-warning" style="display:none;">
            No staff users found.
        </div>

    </div>

</div>

<!-- DETAIL MODAL -->

<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header bg-light p-3">
                <h5 class="modal-title" id="detailModalTitle">Employee Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="row mb-3">

                    <div class="col-md-4 col-xl-2 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0 text-success" id="detail-cash_collected">&#8377;0.00</h5>
                            <small class="text-muted">Cash Collected</small>
                        </div></div>
                    </div>

                    <div class="col-md-4 col-xl-2 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0 text-info" id="detail-non_cash_collected">&#8377;0.00</h5>
                            <small class="text-muted">Non-Cash Collected</small>
                        </div></div>
                    </div>

                    <div class="col-md-4 col-xl-2 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0 text-danger" id="detail-cash_refunded">&#8377;0.00</h5>
                            <small class="text-muted">Cash Refunded</small>
                        </div></div>
                    </div>

                    <div class="col-md-4 col-xl-2 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0 text-danger" id="detail-cash_paid_to_doctors">&#8377;0.00</h5>
                            <small class="text-muted">Cash Paid To Doctors</small>
                        </div></div>
                    </div>

                    <div class="col-md-4 col-xl-2 mb-3">
                        <div class="card h-100 border border-success"><div class="card-body text-center">
                            <h5 class="mb-0 text-success" id="detail-net_cash_to_deposit">&#8377;0.00</h5>
                            <small class="text-muted fw-semibold">Net Cash To Deposit</small>
                        </div></div>
                    </div>

                    <div class="col-md-4 col-xl-2 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0" id="detail-invoice_count">0</h5>
                            <small class="text-muted">Invoices</small>
                        </div></div>
                    </div>

                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Doctor Payments Made (Cash)</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Invoice No</th>
                                        <th>Doctor</th>
                                        <th>Settlement No</th>
                                        <th width="120">Time</th>
                                        <th width="150" class="text-end">Amount (&#8377;)</th>
                                    </tr>
                                </thead>
                                <tbody id="detailDoctorPaymentTableBody"></tbody>
                            </table>
                        </div>
                        <div id="detailNoDoctorPaymentWrap" class="text-muted text-center py-2" style="display:none;">
                            No cash payments made to doctors by this employee in this period.
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Group-Wise Cash Deposit Breakdown</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Reporting Group</th>
                                        <th width="200" class="text-end">Amount (&#8377;)</th>
                                    </tr>
                                </thead>
                                <tbody id="detailGroupBreakdownTableBody"></tbody>
                                <tfoot>
                                    <tr class="table-light fw-semibold">
                                        <td>Total</td>
                                        <td id="detailGroupBreakdownTotal" class="text-end">&#8377;0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Full Ledger</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
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
                                <tbody id="detailLedgerTableBody"></tbody>
                            </table>
                        </div>
                        <div id="detailNoLedgerWrap" class="text-muted text-center py-2" style="display:none;">
                            No transactions found for this employee in this period.
                        </div>
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <a href="javascript:void(0)" id="printDetailBtn" class="btn btn-success" target="_blank">
                    <i class="ri-printer-line"></i>
                    Print Statement
                </a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/employee-performance.init.js') }}"></script>

@endsection
