@extends('layouts.master')

@section('title')
    Purchase Orders
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
    position: fixed;
    z-index: 1060;
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
        Purchase Orders
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

                    <input type="text" id="searchInput" class="form-control form-control-sm w-auto" placeholder="Search PO / vendor...">

                    <select id="statusFilter" class="form-select form-select-sm w-auto">
                        <option value="">All Status</option>
                        <option value="OPEN">Open</option>
                        <option value="CLOSED">Closed</option>
                        <option value="CANCELLED">Cancelled</option>
                    </select>

                </div>

                <button type="button" class="btn btn-primary" id="btnCreatePo">
                    <i class="ri-add-line align-bottom me-1"></i>
                    Create Purchase Order
                </button>

            </div>

            <div class="card-body pt-0">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th width="60">Sl</th>
                                <th width="150">PO No</th>
                                <th width="110">PO Date</th>
                                <th>Vendor</th>
                                <th width="120">Value (&#8377;)</th>
                                <th width="110">Status</th>
                                <th width="120">Action</th>
                            </tr>
                        </thead>

                        <tbody id="poTableBody"></tbody>

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

<!-- CREATE PO OFFCANVAS -->

<div class="offcanvas offcanvas-end" tabindex="-1" id="poOffcanvas" style="width: 65%;">

    <div class="offcanvas-header bg-light">
        <h5 class="offcanvas-title">Create Purchase Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body">

        <form id="poForm">

            @csrf

            <div class="row mb-3">

                <div class="col-md-3">
                    <label class="form-label">PO Date <span class="text-danger">*</span></label>
                    <input type="text" id="po_date-field" class="form-control flatpickr" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Vendor Name <span class="text-danger">*</span></label>
                    <input type="text" id="vendor_name-field" class="form-control" maxlength="150" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Payment Term</label>
                    <input type="text" id="payment_term-field" class="form-control" maxlength="50" placeholder="e.g. CREDIT / ADVANCE">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Requisition Ref</label>
                    <input type="text" id="requisition_ref-field" class="form-control" maxlength="50">
                </div>

                <div class="col-md-3 mt-3">
                    <label class="form-label">Requisition Date</label>
                    <input type="text" id="requisition_date-field" class="form-control flatpickr">
                </div>

                <div class="col-md-9 mt-3">
                    <label class="form-label">Note</label>
                    <input type="text" id="note-field" class="form-control">
                </div>

            </div>

            <div class="table-responsive mb-2">

                <table class="table table-bordered align-middle" id="poItemsTable">

                    <thead class="table-light">
                        <tr>
                            <th width="30%">Item</th>
                            <th width="10%">UOM</th>
                            <th width="12%">Qty</th>
                            <th width="14%">Rate (&#8377;)</th>
                            <th width="10%">GST %</th>
                            <th width="15%">Amount (&#8377;)</th>
                            <th width="5%"></th>
                        </tr>
                    </thead>

                    <tbody id="poItemsBody"></tbody>

                </table>

            </div>

            <button type="button" class="btn btn-sm btn-soft-primary mb-3" id="btnAddPoRow">
                <i class="ri-add-line"></i> Add Item
            </button>

            <div class="text-end fw-bold fs-16 mb-3">
                Total: &#8377; <span id="poGrandTotal">0.00</span>
            </div>

            <button type="submit" class="btn btn-success">
                <i class="ri-save-line"></i> Save Purchase Order
            </button>

        </form>

    </div>

</div>

<!-- VIEW PO MODAL -->

<div class="modal fade" id="viewPoModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header bg-light">
                <h5 class="modal-title">Purchase Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body" id="viewPoBody"></div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/purchase-order.init.js') }}"></script>

@endsection
