@extends('layouts.master')

@section('title')
    Diagnostic Test Report Dashboard
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
        Diagnostic Test Report
    @endslot

    @slot('title')
        Test Report Dashboard
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="card-body">

                <div class="row g-2 align-items-end mb-3">

                    <div class="col-md-3">
                        <label class="form-label mb-1">Search</label>
                        <input type="text" id="searchInput" class="form-control form-control-sm"
                               placeholder="Invoice no / patient / mobile">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-1">Category</label>
                        <select id="categoryFilter" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="PATHOLOGY">Pathology</option>
                            <option value="NON_PATHOLOGY">Non-Pathology</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-1">Payment Status</label>
                        <select id="paymentStatusFilter" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="Paid">Paid</option>
                            <option value="Partial">Partial</option>
                            <option value="Due">Due</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-1">Result Status</label>
                        <select id="resultStatusFilter" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="N/A">N/A</option>
                            <option value="Pending">No Report Prepared</option>
                            <option value="Partial">Prepared Partially</option>
                            <option value="Confirmation Pending">Confirmation Pending</option>
                            <option value="Complete">Completed</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-1">Delivery</label>
                        <select id="deliveryFilter" class="form-select form-select-sm">
                            <option value="not_delivered" selected>Not Delivered</option>
                            <option value="delivered">Delivered</option>
                            <option value="all">All</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-1">Date Range</label>
                        <select id="rangeFilter" class="form-select form-select-sm">
                            <option value="3" selected>Last 3 days</option>
                            <option value="5">Last 5 days</option>
                            <option value="7">Last 7 days</option>
                            <option value="15">Last 15 days</option>
                            <option value="30">Last 30 days</option>
                            <option value="all">All</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-1">Show</label>
                        <select id="perPage" class="form-select form-select-sm">
                            <option value="10">10</option>
                            <option value="15" selected>15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>

                    <div class="col-md-1">
                        <button type="button" class="btn btn-sm btn-light w-100" id="resetFiltersBtn">
                            Reset
                        </button>
                    </div>

                </div>

                <div class="table-responsive">

                    <table class="table table-bordered align-middle" id="reportTable">

                        <thead class="table-light">

                            <tr>
                                <th>Invoice No</th>
                                <th>Patient</th>
                                <th>Category</th>
                                <th>Invoice Date</th>
                                <th>Tests</th>
                                <th>Result Status</th>
                                <th>Payment Status</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Due</th>
                                <th>Actions</th>
                            </tr>

                        </thead>

                        <tbody id="reportTableBody">
                        </tbody>

                    </table>

                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">

                    <div id="pagination-info"></div>

                    <div class="d-flex align-items-center gap-2">

                        <button class="btn btn-sm btn-primary" id="prevPage">
                            Previous
                        </button>

                        <span id="pageNumber">
                            Page 1
                        </span>

                        <button class="btn btn-sm btn-primary" id="nextPage">
                            Next
                        </button>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<div class="modal fade" id="printItemsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">Print Reports</h5>
                    <small class="text-muted" id="printItemsInvoiceInfo"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">

                <div class="alert alert-info py-2 mb-3 d-none" id="printItemsSummary"></div>

                <div class="table-responsive">

                    <table class="table table-bordered align-middle mb-0">

                        <thead class="table-light">
                            <tr>
                                <th style="width: 1%">
                                    <input type="checkbox" class="form-check-input" id="printItemsSelectAll"
                                           title="Select all confirmed items">
                                </th>
                                <th>Item</th>
                                <th>Status</th>
                                <th>Prepared By</th>
                                <th>Prepared Date &amp; Time</th>
                                <th>Confirmed By</th>
                                <th>Confirmed Date &amp; Time</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>

                        <tbody id="printItemsBody">
                        </tbody>

                    </table>

                </div>

                <small class="text-muted d-block mt-2">
                    Only items whose report is confirmed can be selected. Items that share one report
                    (e.g. several tests on one Pathology report) are printed together.
                </small>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="printItemsPrintBtn" disabled>
                    <i class="ri-printer-line"></i> Print Selected
                </button>
            </div>

        </div>
    </div>
</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/test-report-dashboard.init.js') }}"></script>

@endsection
