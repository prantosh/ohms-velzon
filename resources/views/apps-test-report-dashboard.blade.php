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
                        <label class="form-label mb-1">Delivery Status</label>
                        <select id="deliveryStatusFilter" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="Delivered">Delivered</option>
                            <option value="Pending">Not Delivered</option>
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
                                <th>Report Delivered</th>
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

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/test-report-dashboard.init.js') }}"></script>

@endsection
