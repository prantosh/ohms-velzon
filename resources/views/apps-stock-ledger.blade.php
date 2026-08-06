@extends('layouts.master')

@section('title')
    Stock Ledger
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
        Stock Ledger
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3">

                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="d-flex align-items-center">
                        <label class="me-2 mb-0 fw-semibold">Show</label>
                        <select id="perPage" class="form-select form-select-sm w-auto">
                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                        <span class="ms-2">records</span>
                    </div>

                    <input type="text" id="searchInput" class="form-control form-control-sm w-auto" placeholder="Search item...">
                </div>

            </div>

            <div class="card-body pt-0">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th width="60">Sl</th>
                                <th width="110">Item Code</th>
                                <th>Item Name</th>
                                <th width="140">Category</th>
                                <th width="80">UOM</th>
                                <th width="110">Current Stock</th>
                                <th width="100">Avg Rate</th>
                                <th width="130">Stock Value (&#8377;)</th>
                                <th width="110">Action</th>
                            </tr>
                        </thead>

                        <tbody id="stockTableBody"></tbody>

                    </table>

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

</div>

<!-- LEDGER OFFCANVAS -->

<div class="offcanvas offcanvas-end" tabindex="-1" id="ledgerOffcanvas" style="width: 60%;">

    <div class="offcanvas-header bg-light">
        <h5 class="offcanvas-title" id="ledgerItemTitle">Stock Ledger</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body">

        <input type="hidden" id="ledger_item_id-field">

        <div class="row mb-3 align-items-end">

            <div class="col-md-4">
                <label class="form-label">From Date</label>
                <input type="text" id="ledger_from_date-field" class="form-control flatpickr">
            </div>

            <div class="col-md-4">
                <label class="form-label">To Date</label>
                <input type="text" id="ledger_to_date-field" class="form-control flatpickr">
            </div>

            <div class="col-md-3">
                <button type="button" class="btn btn-primary w-100" id="btnLoadLedger">Apply Filter</button>
            </div>

            <div class="col-md-1">
                <a href="javascript:void(0)" id="btnPrintLedger" class="btn btn-success w-100" style="display:none;" target="_blank" title="Print PDF">
                    <i class="ri-printer-line"></i>
                </a>
            </div>

        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <div class="border rounded p-2">
                    <small class="text-muted d-block">Opening Balance</small>
                    <span class="fw-bold" id="ledgerOpening"></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-2">
                    <small class="text-muted d-block">Closing Balance</small>
                    <span class="fw-bold" id="ledgerClosing"></span>
                </div>
            </div>
        </div>

        <div class="table-responsive">

            <table class="table table-bordered table-sm align-middle">

                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Txn No</th>
                        <th>Type</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">Value</th>
                        <th class="text-end">Balance Qty</th>
                        <th class="text-end">Balance Value</th>
                    </tr>
                </thead>

                <tbody id="ledgerTableBody"></tbody>

            </table>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/stock-ledger.init.js') }}"></script>

@endsection
