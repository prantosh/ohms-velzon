@extends('layouts.master')

@section('title')
    Stock Issue
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
        Stock Issue
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

                    <input type="text" id="searchInput" class="form-control form-control-sm w-auto" placeholder="Search issue / issued to...">
                </div>

                <button type="button" class="btn btn-primary" id="btnCreateIssue">
                    <i class="ri-add-line align-bottom me-1"></i>
                    Issue Stock
                </button>

            </div>

            <div class="card-body pt-0">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th width="60">Sl</th>
                                <th width="150">Issue No</th>
                                <th width="110">Date</th>
                                <th>Issued To</th>
                                <th width="120">Action</th>
                            </tr>
                        </thead>

                        <tbody id="issueTableBody"></tbody>

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

<!-- CREATE ISSUE OFFCANVAS -->

<div class="offcanvas offcanvas-end" tabindex="-1" id="issueOffcanvas" style="width: 65%;">

    <div class="offcanvas-header bg-light">
        <h5 class="offcanvas-title">Issue Stock</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body">

        <form id="issueForm">

            @csrf

            <div class="row mb-3">

                <div class="col-md-4">
                    <label class="form-label">Issue Date <span class="text-danger">*</span></label>
                    <input type="text" id="issue_date-field" class="form-control flatpickr" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Issued To <span class="text-danger">*</span></label>
                    <input type="text" id="issued_to_name-field" class="form-control" maxlength="150" placeholder="Staff / department name" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Remarks</label>
                    <input type="text" id="remarks-field" class="form-control">
                </div>

            </div>

            <div class="table-responsive mb-2">

                <table class="table table-bordered align-middle" id="issueItemsTable">

                    <thead class="table-light">
                        <tr>
                            <th width="45%">Item</th>
                            <th width="15%">UOM</th>
                            <th width="15%">Available Stock</th>
                            <th width="20%">Issue Qty</th>
                            <th width="5%"></th>
                        </tr>
                    </thead>

                    <tbody id="issueItemsBody"></tbody>

                </table>

            </div>

            <button type="button" class="btn btn-sm btn-soft-primary mb-3" id="btnAddIssueRow">
                <i class="ri-add-line"></i> Add Item
            </button>

            <button type="submit" class="btn btn-success">
                <i class="ri-save-line"></i> Save Stock Issue
            </button>

        </form>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/stock-issue.init.js') }}"></script>

@endsection
