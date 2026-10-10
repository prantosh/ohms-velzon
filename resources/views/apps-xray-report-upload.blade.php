@extends('layouts.master')

@section('title')
    Upload X-Ray Report
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Diagnostic Test
    @endslot

    @slot('title')
        Upload X-Ray Report
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="card-body">

                <div class="row g-2 align-items-end mb-3">

                    <div class="col-md-4">
                        <label class="form-label mb-1">Search</label>
                        <input type="text" id="searchInput" class="form-control form-control-sm"
                               placeholder="Invoice no / patient / mobile">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-1">Report</label>
                        <select id="uploadStatusFilter" class="form-select form-select-sm">
                            <option value="all" selected>All</option>
                            <option value="not_uploaded">Not Uploaded</option>
                            <option value="uploaded">Uploaded</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-1">Date Range</label>
                        <select id="rangeFilter" class="form-select form-select-sm">
                            <option value="3">Last 3 days</option>
                            <option value="7" selected>Last 7 days</option>
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

                    <div class="col-md-2">
                        <button type="button" class="btn btn-sm btn-light w-100" id="resetFiltersBtn">
                            Reset
                        </button>
                    </div>

                </div>

                <input type="file" id="xrayFileInput" accept="application/pdf,.pdf" class="d-none">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle" id="xrayTable">

                        <thead class="table-light">

                            <tr>
                                <th style="width: 1%">Upload</th>
                                <th>Invoice No</th>
                                <th>Patient</th>
                                <th>Invoice Date</th>
                                <th>X-Ray Test(s)</th>
                                <th>Payment</th>
                                <th>Report</th>
                                <th>View</th>
                            </tr>

                        </thead>

                        <tbody id="xrayTableBody">
                        </tbody>

                    </table>

                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">

                    <div id="pagination-info"></div>

                    <div class="d-flex align-items-center gap-2">

                        <button class="btn btn-sm btn-primary" id="prevPage">Previous</button>

                        <span id="pageNumber">Page 1</span>

                        <button class="btn btn-sm btn-primary" id="nextPage">Next</button>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/xray-report-upload.init.js') }}"></script>

@endsection
