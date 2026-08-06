@extends('layouts.master')

@section('title')
    Inventory Item Master
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Inventory
    @endslot

    @slot('title')
        Inventory Item Master
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

                    <select id="categoryFilter" class="form-select form-select-sm w-auto">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                        @endforeach
                    </select>

                </div>

                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#showModal">
                    <i class="ri-add-line align-bottom me-1"></i>
                    Add Item
                </button>

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
                                <th width="100">Stock</th>
                                <th width="100">Avg Rate</th>
                                <th width="110">Status</th>
                                <th width="120">Action</th>
                            </tr>
                        </thead>

                        <tbody id="itemTableBody"></tbody>

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

<!-- MODAL -->

<div class="modal fade" id="showModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header bg-light p-3">
                <h5 class="modal-title" id="modal-title">Add Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form class="tablelist-form">

                @csrf

                <input type="hidden" id="edit-id">

                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label">Item Name <span class="text-danger">*</span></label>
                        <input type="text" id="item_name-field" class="form-control" maxlength="150" required>
                    </div>

                    <div class="row">

                        <div class="col-md-6 mb-3">
                            <label class="form-label">UOM <span class="text-danger">*</span></label>
                            <input type="text" id="uom-field" class="form-control" maxlength="20" placeholder="e.g. PIECE, LITRE, BOX" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select id="inventory_category_id-field" class="form-select">
                                <option value="">-- None --</option>
                                @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 mb-3" id="openingStockWrap">
                            <label class="form-label">Opening Stock</label>
                            <input type="number" step="0.01" min="0" id="opening_stock-field" class="form-control" value="0">
                        </div>

                        <div class="col-md-6 mb-3" id="openingValueWrap">
                            <label class="form-label">Opening Value (&#8377;)</label>
                            <input type="number" step="0.01" min="0" id="opening_value-field" class="form-control" value="0">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select id="status-field" class="form-select">
                                <option value="ACTIVE">ACTIVE</option>
                                <option value="INACTIVE">INACTIVE</option>
                            </select>
                        </div>

                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success" id="add-btn">Save Item</button>
                </div>

            </form>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/inventory-item.init.js') }}"></script>

@endsection
