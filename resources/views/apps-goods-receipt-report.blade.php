@extends('layouts.master')

@section('title')
    Goods Receipt Report
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
        Inventory
    @endslot

    @slot('title')
        Goods Receipt Report
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-file-list-3-line"></i>
            Shows every Goods Receipt line within a date range.
            <b>Detail</b> lists each GRN line. <b>Summary</b> totals quantity/amount by item for the same period.
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Select Period</h5>
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
                            Load
                        </button>
                    </div>

                </div>

            </div>

        </div>

        <ul class="nav nav-tabs nav-tabs-custom mb-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#detailTab" role="tab">
                    <i class="ri-list-check-2 align-middle me-1"></i> Detail
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#summaryTab" role="tab">
                    <i class="ri-bar-chart-2-line align-middle me-1"></i> Summary
                </a>
            </li>
        </ul>

        <div class="tab-content">

            <!-- =======================================================
                 DETAIL TAB
            ======================================================= -->

            <div class="tab-pane active" id="detailTab" role="tabpanel">

                <div class="d-flex justify-content-end mb-2">
                    <a href="javascript:void(0)" id="printDetailBtn" class="btn btn-success" style="display:none;" target="_blank">
                        <i class="ri-printer-line"></i>
                        Print PDF
                    </a>
                </div>

                <div class="row" id="detailTotalsRow" style="display:none;">

                    <div class="col-md-6 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0" id="detail-qty">0</h5>
                            <small class="text-muted">Total Qty</small>
                        </div></div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="card h-100 border border-success"><div class="card-body text-center">
                            <h5 class="mb-0 text-success" id="detail-amount">&#8377;0.00</h5>
                            <small class="text-muted fw-semibold">Total Amount</small>
                        </div></div>
                    </div>

                </div>

                <div class="card" id="detailCard" style="display:none;">

                    <div class="card-header">
                        <h5 class="card-title mb-0">Goods Receipt Detail</h5>
                    </div>

                    <div class="card-body">

                        <div class="table-responsive">

                            <table class="table table-bordered align-middle">

                                <thead class="table-light">
                                    <tr>
                                        <th width="110">GRN No</th>
                                        <th width="100">Receipt Date</th>
                                        <th>Vendor</th>
                                        <th width="100">PO Ref</th>
                                        <th>Item Code</th>
                                        <th>Item Name</th>
                                        <th width="70">UOM</th>
                                        <th width="100">Qty Received</th>
                                        <th width="100">Rate</th>
                                        <th width="110">Amount</th>
                                    </tr>
                                </thead>

                                <tbody id="detailTableBody"></tbody>

                            </table>

                        </div>

                    </div>

                </div>

                <div id="detailNoDataWrap" class="alert alert-warning" style="display:none;">
                    No goods receipt lines found for the selected period.
                </div>

            </div>

            <!-- =======================================================
                 SUMMARY TAB
            ======================================================= -->

            <div class="tab-pane" id="summaryTab" role="tabpanel">

                <div class="d-flex justify-content-end mb-2">
                    <a href="javascript:void(0)" id="printSummaryBtn" class="btn btn-success" style="display:none;" target="_blank">
                        <i class="ri-printer-line"></i>
                        Print PDF
                    </a>
                </div>

                <div class="row" id="summaryTotalsRow" style="display:none;">

                    <div class="col-md-6 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0" id="summary-qty">0</h5>
                            <small class="text-muted">Total Qty</small>
                        </div></div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="card h-100 border border-success"><div class="card-body text-center">
                            <h5 class="mb-0 text-success" id="summary-amount">&#8377;0.00</h5>
                            <small class="text-muted fw-semibold">Total Amount</small>
                        </div></div>
                    </div>

                </div>

                <div class="card" id="summaryCard" style="display:none;">

                    <div class="card-header">
                        <h5 class="card-title mb-0">By Item</h5>
                    </div>

                    <div class="card-body">

                        <div class="table-responsive">

                            <table class="table table-bordered align-middle">

                                <thead class="table-light">
                                    <tr>
                                        <th>Item Code</th>
                                        <th>Item Name</th>
                                        <th width="70">UOM</th>
                                        <th width="100">GRN Count</th>
                                        <th width="110">Qty</th>
                                        <th width="140">Amount</th>
                                    </tr>
                                </thead>

                                <tbody id="summaryTableBody"></tbody>

                            </table>

                        </div>

                    </div>

                </div>

                <div id="summaryNoDataWrap" class="alert alert-warning" style="display:none;">
                    No goods receipt lines found for the selected period.
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

<script src="{{ URL::asset('build/js/pages/goods-receipt-report.init.js') }}"></script>

@endsection
