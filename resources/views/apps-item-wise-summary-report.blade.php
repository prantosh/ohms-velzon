@extends('layouts.master')

@section('title')
    Item Wise Summary Report
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
        Report
    @endslot

    @slot('title')
        Item Wise Summary Report
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-file-list-3-line"></i>
            <b>Summary</b> covers every item code billed in the selected period, with amount receivable
            (billed), amount received (cash + non-cash, split), settled amount (belonging to fully-paid
            invoices only) and amount deposited to accounts (cash collected minus cash refunds minus cash
            paid to doctors, plus all non-cash received). Receivable/Settled use the invoice date; Received /
            Refund / Doctor Payment use their own transaction date, so the two sides may not always line up
            for invoices near either edge of the range. Use <b>Detail</b> to drill into a single item code's
            invoice-level rows.
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

                    <div class="col-md-3 mb-3">
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
                <a class="nav-link active" data-bs-toggle="tab" href="#summaryTab" role="tab">
                    <i class="ri-bar-chart-2-line align-middle me-1"></i> Summary (All Items)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#detailTab" role="tab">
                    <i class="ri-list-check-2 align-middle me-1"></i> Detail (Single Item)
                </a>
            </li>
        </ul>

        <div class="tab-content">

            <!-- =======================================================
                 SUMMARY TAB
            ======================================================= -->

            <div class="tab-pane active" id="summaryTab" role="tabpanel">

                <div class="d-flex justify-content-end mb-2">
                    <a href="javascript:void(0)" id="printSummaryBtn" class="btn btn-success" style="display:none;" target="_blank">
                        <i class="ri-printer-line"></i>
                        Print PDF
                    </a>
                </div>

                <div class="row" id="summaryTotalsRow" style="display:none;">

                    <div class="col-md-2 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0" id="summary-invoice_count">0</h5>
                            <small class="text-muted">Invoices</small>
                        </div></div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0" id="summary-receivable">&#8377;0.00</h5>
                            <small class="text-muted">Receivable</small>
                        </div></div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0" id="summary-received_total">&#8377;0.00</h5>
                            <small class="text-muted">Received (Total)</small>
                        </div></div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <div class="card h-100 border border-success"><div class="card-body text-center">
                            <h5 class="mb-0 text-success" id="summary-settled_amount">&#8377;0.00</h5>
                            <small class="text-muted fw-semibold">Settled</small>
                        </div></div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <div class="card h-100 border border-primary"><div class="card-body text-center">
                            <h5 class="mb-0 text-primary" id="summary-deposited">&#8377;0.00</h5>
                            <small class="text-muted fw-semibold">Deposited</small>
                        </div></div>
                    </div>

                </div>

                <div class="card" id="summaryCard" style="display:none;">

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

                                <tbody id="summaryTableBody"></tbody>

                            </table>

                        </div>

                    </div>

                </div>

                <div id="summaryNoDataWrap" class="alert alert-warning" style="display:none;">
                    No activity found for the selected period.
                </div>

            </div>

            <!-- =======================================================
                 DETAIL TAB
            ======================================================= -->

            <div class="tab-pane" id="detailTab" role="tabpanel">

                <div class="row align-items-end mb-3">

                    <div class="col-md-4">
                        <label class="form-label">Item <span class="text-danger">*</span></label>
                        <select id="item_code-field" class="form-select">
                            <option value="">-- Select an Item --</option>
                            @foreach($itemMasters as $item)
                            <option value="{{ $item->item_code }}">{{ $item->item_name }} ({{ $item->item_code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <button type="button" class="btn btn-primary" id="loadDetailBtn">
                            <i class="ri-search-line"></i>
                            Load Detail
                        </button>
                    </div>

                </div>

                <div class="d-flex justify-content-end mb-2">
                    <a href="javascript:void(0)" id="printDetailBtn" class="btn btn-success" style="display:none;" target="_blank">
                        <i class="ri-printer-line"></i>
                        Print PDF
                    </a>
                </div>

                <div class="row" id="detailTotalsRow" style="display:none;">

                    <div class="col-md-4 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0" id="detail-invoice_count">0</h5>
                            <small class="text-muted">Invoices</small>
                        </div></div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0" id="detail-amount">&#8377;0.00</h5>
                            <small class="text-muted">Billed Amount</small>
                        </div></div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border border-success"><div class="card-body text-center">
                            <h5 class="mb-0 text-success" id="detail-settled_amount">&#8377;0.00</h5>
                            <small class="text-muted fw-semibold">Settled Amount</small>
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
                                        <th width="90">Invoice Date</th>
                                        <th width="110">Invoice No</th>
                                        <th>Patient</th>
                                        <th>Item / Sub-Item</th>
                                        <th width="100">Amount</th>
                                        <th width="100">Payment Mode</th>
                                        <th width="90">Settled?</th>
                                        <th width="70">Print</th>
                                    </tr>
                                </thead>

                                <tbody id="detailTableBody"></tbody>

                            </table>

                        </div>

                    </div>

                </div>

                <div id="detailNoDataWrap" class="alert alert-warning" style="display:none;">
                    No invoices found for the selected item and period.
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

<script src="{{ URL::asset('build/js/pages/item-wise-summary-report.init.js') }}"></script>

@endsection
