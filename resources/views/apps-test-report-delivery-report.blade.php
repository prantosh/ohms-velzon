@extends('layouts.master')

@section('title')
    Test Report Delivery Log
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
        Test Report Delivery Log
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="card-body">

                <ul class="nav nav-tabs mb-3" id="deliveryLogTabs">

                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#deliveredPane">
                            Delivered
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#undeliveredPane">
                            Undelivered <span class="badge bg-warning-subtle text-warning ms-1" id="undeliveredCount">0</span>
                        </a>
                    </li>

                </ul>

                <div class="tab-content">

                    <!-- =====================================================
                         DELIVERED -- the append-only test_report_deliveries
                         log (every "mark as delivered" action).
                    ===================================================== -->

                    <div class="tab-pane fade show active" id="deliveredPane">

                        <div class="alert alert-info">
                            <i class="ri-file-list-3-line"></i>
                            Every time a diagnostic report is marked as delivered it is logged here, along with the
                            payment position at that exact moment -- an invoice delivered more than once (e.g. unmarked
                            and re-delivered) appears once per delivery.
                        </div>

                        <div class="row g-2 align-items-end mb-2">

                            <div class="col-md-3">
                                <label class="form-label mb-1">Search</label>
                                <input type="text" id="searchInput" class="form-control form-control-sm"
                                       placeholder="Invoice no / patient / mobile">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label mb-1">User (Delivered By)</label>
                                <select id="userFilter" class="form-select form-select-sm">
                                    <option value="ALL">-- All Users --</option>
                                    @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-1">From Date</label>
                                <input type="text" id="fromDateFilter" class="form-control form-control-sm flatpickr">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-1">To Date</label>
                                <input type="text" id="toDateFilter" class="form-control form-control-sm flatpickr">
                            </div>

                            <div class="col-md-1">
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

                        <div class="d-flex justify-content-between align-items-center mb-2">

                            <!-- Quick day-range shortcuts -- fill in From/To
                                 Date above rather than being a separate
                                 server-side filter, so a manual custom range
                                 still works afterwards. -->
                            <div class="btn-group btn-group-sm" id="dayRangeButtons" role="group">
                                <button type="button" class="btn btn-outline-primary" data-days="3">3 Days</button>
                                <button type="button" class="btn btn-outline-primary" data-days="5">5 Days</button>
                                <button type="button" class="btn btn-outline-primary" data-days="15">15 Days</button>
                                <button type="button" class="btn btn-outline-primary" data-days="30">30 Days</button>
                                <button type="button" class="btn btn-outline-primary active" data-days="all">All</button>
                            </div>

                            <a href="javascript:void(0)" id="printReportBtn" class="btn btn-success btn-sm" target="_blank">
                                <i class="ri-printer-line"></i>
                                Print PDF
                            </a>

                        </div>

                        <div class="table-responsive">

                            <table class="table table-bordered align-middle" id="reportTable">

                                <thead class="table-light">

                                    <tr>
                                        <th>Invoice No</th>
                                        <th>Invoice Date</th>
                                        <th>Patient</th>
                                        <th>Mobile Number</th>
                                        <th>Type</th>
                                        <th>Delivered By</th>
                                        <th>Delivered At</th>
                                        <th>Payment Status</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end">Paid</th>
                                        <th class="text-end">Due</th>
                                        <th>X-Ray Report</th>
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

                    <!-- =====================================================
                         UNDELIVERED -- reuses the Test Report Dashboard's
                         own "Not Delivered" data/action (same invoices,
                         same toggle-delivered endpoint) rather than a
                         separate implementation, so the two screens can
                         never disagree about what counts as undelivered.
                    ===================================================== -->

                    <div class="tab-pane fade" id="undeliveredPane">

                        <div class="alert alert-warning">
                            <i class="ri-error-warning-line"></i>
                            Diagnostic invoices still owing an in-house and/or outsourced report delivery, oldest first.
                            In-house and outsourced tests on the same invoice are tracked and delivered independently.
                            Outsourced reports go through an extra "Received from Lab" step before they can be
                            marked delivered, since receiving and delivering may be done by different staff.
                        </div>

                        <div class="row g-2 align-items-end mb-2">

                            <div class="col-md-4">
                                <label class="form-label mb-1">Search</label>
                                <input type="text" id="undeliveredSearchInput" class="form-control form-control-sm"
                                       placeholder="Invoice no / patient / mobile">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-1">Show</label>
                                <select id="undeliveredPerPage" class="form-select form-select-sm">
                                    <option value="10">10</option>
                                    <option value="15" selected>15</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                            </div>

                            <div class="col-md-1">
                                <button type="button" class="btn btn-sm btn-light w-100" id="undeliveredResetFiltersBtn">
                                    Reset
                                </button>
                            </div>

                        </div>

                        <div class="table-responsive">

                            <table class="table table-bordered align-middle" id="undeliveredTable">

                                <thead class="table-light">

                                    <tr>
                                        <th>Invoice No</th>
                                        <th>Invoice Date</th>
                                        <th>Patient</th>
                                        <th>Mobile Number</th>
                                        <th>Payment Status</th>
                                        <th class="text-end">Due</th>
                                        <th>In-House Status</th>
                                        <th>Outsourced Status</th>
                                        <th>Actions</th>
                                    </tr>

                                </thead>

                                <tbody id="undeliveredTableBody">
                                </tbody>

                            </table>

                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3">

                            <div id="undelivered-pagination-info"></div>

                            <div class="d-flex align-items-center gap-2">

                                <button class="btn btn-sm btn-primary" id="undeliveredPrevPage">
                                    Previous
                                </button>

                                <span id="undeliveredPageNumber">
                                    Page 1
                                </span>

                                <button class="btn btn-sm btn-primary" id="undeliveredNextPage">
                                    Next
                                </button>

                            </div>

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

<script src="{{ URL::asset('build/js/pages/test-report-delivery-report.init.js') }}"></script>

@endsection
