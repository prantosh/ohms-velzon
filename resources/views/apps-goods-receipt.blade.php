@extends('layouts.master')

@section('title')
    Goods Receipt
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

<link href="{{ URL::asset('build/libs/flatpickr/flatpickr.min.css') }}" rel="stylesheet" type="text/css" />

<style>

.item-search-wrap {
    position: relative;
}

.item-suggestions {
    position: absolute;
    z-index: 1060;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    max-height: 220px;
    overflow-y: auto;
    box-shadow: 0 6px 16px rgba(0,0,0,.12);
    display: none;
}

.item-suggestions .suggestion-item {
    padding: 6px 10px;
    cursor: pointer;
    font-size: 13px;
}

.item-suggestions .suggestion-item:hover {
    background: #f1f3f9;
}

</style>

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Inventory
    @endslot

    @slot('title')
        Goods Receipt
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

                    <input type="text" id="searchInput" class="form-control form-control-sm w-auto" placeholder="Search receipt / vendor...">
                </div>

                <button type="button" class="btn btn-primary" id="btnCreateGrn">
                    <i class="ri-add-line align-bottom me-1"></i>
                    Create Goods Receipt
                </button>

            </div>

            <div class="card-body pt-0">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th width="60">Sl</th>
                                <th width="150">Receipt No</th>
                                <th width="110">Date</th>
                                <th width="150">Against PO</th>
                                <th>Vendor</th>
                                <th width="120">Action</th>
                            </tr>
                        </thead>

                        <tbody id="grnTableBody"></tbody>

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

<!-- CREATE GRN OFFCANVAS -->

<div class="offcanvas offcanvas-end" tabindex="-1" id="grnOffcanvas" style="width: 65%;">

    <div class="offcanvas-header bg-light">
        <h5 class="offcanvas-title">Create Goods Receipt</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body">

        <form id="grnForm">

            @csrf

            <div class="row mb-3">

                <div class="col-md-3">
                    <label class="form-label">Receipt Date <span class="text-danger">*</span></label>
                    <input type="text" id="receipt_date-field" class="form-control flatpickr" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Against Purchase Order</label>
                    <select id="purchase_order_id-field" class="form-select">
                        <option value="">-- Direct / Local Purchase --</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-info w-100" id="btnLoadPoItems">Load PO Items</button>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Ref No</label>
                    <input type="text" id="ref_no-field" class="form-control" maxlength="50">
                </div>

                <div class="col-md-6 mt-3">
                    <label class="form-label">Vendor Name</label>
                    <input type="text" id="vendor_name-field" class="form-control" maxlength="150">
                </div>

                <div class="col-md-6 mt-3">
                    <label class="form-label">Remarks</label>
                    <input type="text" id="remarks-field" class="form-control">
                </div>

            </div>

            <div class="table-responsive mb-2">

                <table class="table table-bordered align-middle" id="grnItemsTable">

                    <thead class="table-light">
                        <tr>
                            <th width="30%">Item</th>
                            <th width="10%">UOM</th>
                            <th width="15%">Received Qty</th>
                            <th width="15%">Rate (&#8377;)</th>
                            <th width="15%">Amount (&#8377;)</th>
                            <th width="5%"></th>
                        </tr>
                    </thead>

                    <tbody id="grnItemsBody"></tbody>

                </table>

            </div>

            <button type="button" class="btn btn-sm btn-soft-primary mb-3" id="btnAddGrnRow">
                <i class="ri-add-line"></i> Add Item
            </button>

            <div class="text-end fw-bold fs-16 mb-3">
                Total: &#8377; <span id="grnGrandTotal">0.00</span>
            </div>

            <button type="submit" class="btn btn-success">
                <i class="ri-save-line"></i> Save Goods Receipt
            </button>

        </form>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/goods-receipt.init.js') }}"></script>

@endsection
