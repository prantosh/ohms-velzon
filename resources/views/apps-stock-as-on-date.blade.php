@extends('layouts.master')

@section('title')
    Stock As On Date
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
        Stock As On Date
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-file-list-3-line"></i>
            Shows every item's closing stock quantity and value as of a chosen date, computed from opening balance
            plus all Goods Receipts and Stock Issues up to and including that date. Use <b>Any Date</b> for a
            custom cutoff, or <b>Year-End (31 Mar)</b> as a one-click shortcut.
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Select Cutoff Date &amp; Filters</h5>
            </div>

            <div class="card-body">

                <div class="row align-items-end">

                    <div class="col-md-3 mb-3">
                        <label class="form-label d-block">Date Mode</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="date_mode" id="dateModeAny" value="any" checked>
                            <label class="form-check-label" for="dateModeAny">Any Date</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="date_mode" id="dateModeYearEnd" value="year_end">
                            <label class="form-check-label" for="dateModeYearEnd">Year-End (31 Mar)</label>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3" id="anyDateWrap">
                        <label class="form-label">As On Date <span class="text-danger">*</span></label>
                        <input type="text" id="as_on_date-field" class="form-control flatpickr" required>
                    </div>

                    <div class="col-md-3 mb-3" id="yearEndWrap" style="display:none;">
                        <label class="form-label">Financial Year Ending 31 March <span class="text-danger">*</span></label>
                        <select id="year_end-field" class="form-select"></select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Category</label>
                        <select id="category_id-field" class="form-select">
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                            @endforeach
                        </select>
                    </div>

                </div>

                <div class="row align-items-end">

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Search Item</label>
                        <input type="text" id="search-field" class="form-control" placeholder="Item code or name">
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

        <div class="d-flex justify-content-end mb-2">
            <a href="javascript:void(0)" id="printBtn" class="btn btn-success" style="display:none;" target="_blank">
                <i class="ri-printer-line"></i>
                Print PDF
            </a>
        </div>

        <div class="row" id="totalsRow" style="display:none;">

            <div class="col-md-6 mb-3">
                <div class="card h-100"><div class="card-body text-center">
                    <h5 class="mb-0" id="total-qty">0</h5>
                    <small class="text-muted">Total Closing Qty</small>
                </div></div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="card h-100 border border-success"><div class="card-body text-center">
                    <h5 class="mb-0 text-success" id="total-value">&#8377;0.00</h5>
                    <small class="text-muted fw-semibold">Total Closing Value</small>
                </div></div>
            </div>

        </div>

        <div class="card" id="resultCard" style="display:none;">

            <div class="card-header">
                <h5 class="card-title mb-0">Stock As On <span id="asOnDateLabel"></span></h5>
            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th width="70">UOM</th>
                                <th width="120">Closing Qty</th>
                                <th width="120">Eff. Rate</th>
                                <th width="140">Closing Value</th>
                            </tr>
                        </thead>

                        <tbody id="resultTableBody"></tbody>

                    </table>

                </div>

            </div>

        </div>

        <div id="noDataWrap" class="alert alert-warning" style="display:none;">
            No items found for the selected filters.
        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/stock-as-on-date.init.js') }}"></script>

@endsection
