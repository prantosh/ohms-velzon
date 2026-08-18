@extends('layouts.master')

@section('title')
    Item Wise Report
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
        Item Wise Report
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-file-list-3-line"></i>
            <b>All Items Summary</b> covers every item code billed in the selected period -- receivable, received
            (cash/non-cash), settled and deposited. Receivable/Settled use the invoice date; Received / Refund /
            Doctor Payment use their own transaction date, so the two sides may not always line up for invoices near
            either edge of the range. Pick an item below (or click a row's view-detail icon) to drill into
            <b>Item Detail</b> (every matching invoice) and <b>Item Summary</b> (that item's activity by date and by
            test/doctor).
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Select Period &amp; Item</h5>
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

                    <div class="col-md-3 mb-3">
                        <button type="button" class="btn btn-primary w-100" id="loadReportBtn">
                            <i class="ri-search-line"></i>
                            Load All Items Summary
                        </button>
                    </div>

                </div>

                <div class="row align-items-end">

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Item</label>
                        <select id="item_code-field" class="form-select">
                            <option value="">-- Select an Item --</option>
                            @foreach($itemMasters as $master)
                                <option value="{{ $master->item_code }}">{{ $master->item_name }} ({{ $master->item_code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <button type="button" class="btn btn-outline-primary w-100" id="loadItemBtn">
                            <i class="ri-search-line"></i>
                            Load Item Detail &amp; Summary
                        </button>
                    </div>

                </div>

            </div>

        </div>

        <ul class="nav nav-tabs nav-tabs-custom mb-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#allItemsSummaryTab" role="tab">
                    <i class="ri-dashboard-2-line align-middle me-1"></i> All Items Summary
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#detailTab" role="tab">
                    <i class="ri-list-check-2 align-middle me-1"></i> Item Detail
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#summaryTab" role="tab">
                    <i class="ri-bar-chart-2-line align-middle me-1"></i> Item Summary
                </a>
            </li>
        </ul>

        <div class="tab-content">

            <!-- =======================================================
                 TAB 1: ALL ITEMS SUMMARY
            ======================================================= -->

            <div class="tab-pane active" id="allItemsSummaryTab" role="tabpanel">

                <div class="d-flex justify-content-end mb-2">
                    <a href="javascript:void(0)" id="printAllItemsSummaryBtn" class="btn btn-success" style="display:none;" target="_blank">
                        <i class="ri-printer-line"></i>
                        Print PDF
                    </a>
                </div>

                <div class="row" id="allItemsTotalsRow" style="display:none;">

                    <div class="col-md-2 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0" id="allitems-invoice_count">0</h5>
                            <small class="text-muted">Invoices</small>
                        </div></div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0" id="allitems-receivable">&#8377;0.00</h5>
                            <small class="text-muted">Receivable</small>
                        </div></div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0" id="allitems-received_total">&#8377;0.00</h5>
                            <small class="text-muted">Received (Total)</small>
                        </div></div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <div class="card h-100 border border-success"><div class="card-body text-center">
                            <h5 class="mb-0 text-success" id="allitems-settled_amount">&#8377;0.00</h5>
                            <small class="text-muted fw-semibold">Settled</small>
                        </div></div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <div class="card h-100 border border-primary"><div class="card-body text-center">
                            <h5 class="mb-0 text-primary" id="allitems-deposited">&#8377;0.00</h5>
                            <small class="text-muted fw-semibold">Deposited</small>
                        </div></div>
                    </div>

                </div>

                <div class="card" id="allItemsSummaryCard" style="display:none;">

                    <div class="card-header">
                        <h5 class="card-title mb-0">Item Wise Summary</h5>
                    </div>

                    <div class="card-body">

                        <div class="table-responsive">

                            <table class="table table-bordered align-middle">

                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th width="80">Code</th>
                                        <th width="80">Invoices</th>
                                        <th width="110">Receivable</th>
                                        <th width="100">Received Cash</th>
                                        <th width="100">Received Non-Cash</th>
                                        <th width="100">Received Total</th>
                                        <th width="100">Settled</th>
                                        <th width="90">Refund Cash</th>
                                        <th width="110">Doctor Payment Cash</th>
                                        <th width="110">Deposited</th>
                                        <th width="70"></th>
                                    </tr>
                                </thead>

                                <tbody id="allItemsSummaryTableBody"></tbody>

                            </table>

                        </div>

                    </div>

                </div>

                <div id="allItemsNoDataWrap" class="alert alert-warning" style="display:none;">
                    No activity found for the selected period.
                </div>

            </div>

            <!-- =======================================================
                 TAB 2: ITEM DETAIL (merged)
            ======================================================= -->

            <div class="tab-pane" id="detailTab" role="tabpanel">

                <div class="d-flex justify-content-end mb-2">
                    <a href="javascript:void(0)" id="printDetailBtn" class="btn btn-success" style="display:none;" target="_blank">
                        <i class="ri-printer-line"></i>
                        Print PDF
                    </a>
                </div>

                <div class="row" id="detailTotalsRow" style="display:none;">

                    <div class="col-md-3 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0" id="detail-invoice_count">0</h5>
                            <small class="text-muted">Total Invoices</small>
                        </div></div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0" id="detail-amount">&#8377;0.00</h5>
                            <small class="text-muted">Billed Amount</small>
                        </div></div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card h-100 border border-success"><div class="card-body text-center">
                            <h5 class="mb-0 text-success" id="detail-settled_amount">&#8377;0.00</h5>
                            <small class="text-muted fw-semibold">Settled Amount</small>
                        </div></div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0" id="detail-item_name">-</h5>
                            <small class="text-muted">Item</small>
                        </div></div>
                    </div>

                </div>

                <div class="card" id="detailCard" style="display:none;">

                    <div class="card-header">
                        <h5 class="card-title mb-0" id="detailCardTitle">Invoice Detail</h5>
                    </div>

                    <div class="card-body">

                        <div class="table-responsive">

                            <table class="table table-bordered align-middle">

                                <thead class="table-light">
                                    <tr>
                                        <th width="120">Invoice No</th>
                                        <th width="95">Invoice Date</th>
                                        <th>Patient Name</th>
                                        <th>Test(s) / Item(s)</th>
                                        <th width="110">Amount (&#8377;)</th>
                                        <th width="100">Payment Mode</th>
                                        <th width="85">Status</th>
                                        <th width="85">Settled?</th>
                                        <th width="60">Print</th>
                                    </tr>
                                </thead>

                                <tbody id="detailTableBody"></tbody>

                            </table>

                        </div>

                    </div>

                </div>

                <div id="detailNoDataWrap" class="alert alert-warning" style="display:none;">
                    No invoices found for this item in the selected period.
                </div>

            </div>

            <!-- =======================================================
                 TAB 3: ITEM SUMMARY
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
                            <h5 class="mb-0" id="summary-invoice_count">0</h5>
                            <small class="text-muted">Total Invoices</small>
                        </div></div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="card h-100 border border-success"><div class="card-body text-center">
                            <h5 class="mb-0 text-success" id="summary-amount">&#8377;0.00</h5>
                            <small class="text-muted fw-semibold">Total Amount</small>
                        </div></div>
                    </div>

                </div>

                <div class="card" id="summaryByDateCard" style="display:none;">

                    <div class="card-header">
                        <h5 class="card-title mb-0">By Date</h5>
                    </div>

                    <div class="card-body">

                        <div class="table-responsive">

                            <table class="table table-bordered align-middle">

                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th width="120">Invoices</th>
                                        <th width="150">Amount (&#8377;)</th>
                                    </tr>
                                </thead>

                                <tbody id="summaryByDateTableBody"></tbody>

                            </table>

                        </div>

                    </div>

                </div>

                <div class="card" id="summaryBySubItemCard" style="display:none;">

                    <div class="card-header">
                        <h5 class="card-title mb-0">By Test / Doctor</h5>
                    </div>

                    <div class="card-body">

                        <div class="table-responsive">

                            <table class="table table-bordered align-middle">

                                <thead class="table-light">
                                    <tr>
                                        <th>Description</th>
                                        <th width="120">Count</th>
                                        <th width="150">Amount (&#8377;)</th>
                                    </tr>
                                </thead>

                                <tbody id="summaryBySubItemTableBody"></tbody>

                            </table>

                        </div>

                    </div>

                </div>

                <div id="summaryNoDataWrap" class="alert alert-warning" style="display:none;">
                    No invoices found for this item in the selected period.
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

<script src="{{ URL::asset('build/js/pages/item-wise-report.init.js') }}"></script>

@endsection
