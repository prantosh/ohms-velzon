@extends('layouts.master')

@section('title')
    Expenditure Category Master
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Master Management
    @endslot

    @slot('title')
        Expenditure Category Master
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

                    <input type="text" id="searchInput" class="form-control form-control-sm w-auto" placeholder="Search category...">

                </div>

                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#showModal">
                    <i class="ri-add-line align-bottom me-1"></i>
                    Add Expenditure Category
                </button>

            </div>

            <div class="card-body pt-0">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th width="60">Sl</th>
                                <th>Description</th>
                                <th width="150">Created On</th>
                                <th width="120">Action</th>
                            </tr>
                        </thead>

                        <tbody id="categoryTableBody"></tbody>

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
                <h5 class="modal-title" id="modal-title">Add Expenditure Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form class="tablelist-form">

                @csrf

                <input type="hidden" id="edit-id">

                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <input type="text" id="description-field" class="form-control" maxlength="150" required>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success" id="add-btn">Save Category</button>
                </div>

            </form>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/expenditure-category.init.js') }}"></script>

@endsection
