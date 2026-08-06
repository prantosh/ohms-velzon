@extends('layouts.master')

@section('title')
    All Invoices Report
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
        Billing
    @endslot

    @slot('title')
        All Invoices Report
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Select Date</h5>
            </div>

            <div class="card-body">

                <div class="row align-items-end">

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Invoice Date <span class="text-danger">*</span></label>
                        <input type="text" id="invoice_date-field" class="form-control flatpickr" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Search</label>
                        <input type="text" id="search-field" class="form-control" placeholder="Invoice No / Patient Name / Mobile">
                    </div>

                    <div class="col-md-2 mb-3">
                        <button type="button" class="btn btn-primary w-100" id="loadReportBtn">
                            <i class="ri-search-line"></i>
                            Load
                        </button>
                    </div>

                </div>

            </div>

        </div>

        <ul class="nav nav-tabs nav-tabs-custom mb-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#allTab" role="tab" data-tab="all">
                    All Invoices <span class="badge bg-primary-subtle text-primary ms-1" id="badge-all">0</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#pendingTab" role="tab" data-tab="pending">
                    Pending Invoices <span class="badge bg-warning-subtle text-warning ms-1" id="badge-pending">0</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#cancelledTab" role="tab" data-tab="cancelled">
                    Cancelled Invoices <span class="badge bg-danger-subtle text-danger ms-1" id="badge-cancelled">0</span>
                </a>
            </li>
        </ul>

        <div class="tab-content">

            @foreach(['all' => 'allTab', 'pending' => 'pendingTab', 'cancelled' => 'cancelledTab'] as $tabKey => $tabId)

            <div class="tab-pane @if($tabKey === 'all') active @endif" id="{{ $tabId }}" role="tabpanel">

                <div class="card">

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3">

                        <div class="d-flex align-items-center">
                            <label class="me-2 mb-0 fw-semibold">Show</label>
                            <select class="form-select form-select-sm w-auto perPage-field" data-tab="{{ $tabKey }}">
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <span class="ms-2">records</span>
                        </div>

                    </div>

                    <div class="card-body pt-0">

                        <div class="table-responsive">

                            <table class="table table-bordered align-middle">

                                <thead class="table-light">
                                    <tr>
                                        <th width="60">Sl</th>
                                        <th width="100">Invoice Date</th>
                                        <th width="120">Invoice No</th>
                                        <th width="130">Type</th>
                                        <th>Patient</th>
                                        <th width="90">Mobile</th>
                                        <th>Doctor</th>
                                        <th width="100">Total</th>
                                        <th width="100">Paid</th>
                                        <th width="100">Due</th>
                                        <th width="90">Payment Mode</th>
                                        <th width="90">Status</th>
                                        <th>Created By</th>
                                        <th width="60">Print</th>
                                    </tr>
                                </thead>

                                <tbody id="tableBody-{{ $tabKey }}"></tbody>

                            </table>

                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3">

                            <div id="pagination-info-{{ $tabKey }}"></div>

                            <div class="d-flex align-items-center gap-2">
                                <button class="btn btn-sm btn-primary prevPage-btn" data-tab="{{ $tabKey }}">Previous</button>
                                <span id="pageNumber-{{ $tabKey }}">Page 1</span>
                                <button class="btn btn-sm btn-primary nextPage-btn" data-tab="{{ $tabKey }}">Next</button>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

            @endforeach

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/all-invoices-report.init.js') }}"></script>

@endsection
