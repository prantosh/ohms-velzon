@extends('layouts.master')

@section('title')
    Test Extra Field Master
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Diagnostic Test
    @endslot

    @slot('title')
        Test Extra Field Master
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">

    <div class="d-flex align-items-center gap-3 flex-wrap">

        <div class="d-flex align-items-center">

            <label class="me-2 mb-0 fw-semibold">
                Show
            </label>

            <select id="perPage"
                    class="form-select form-select-sm w-auto">

                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>

            </select>

            <span class="ms-2">
                records
            </span>

        </div>

        <div>
            <input type="text"
                   id="searchInput"
                   class="form-control form-control-sm"
                   placeholder="Search extra field">
        </div>

    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap">

        <button type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#showModal"
                id="addFieldTypeBtn">

            <i class="ri-add-line align-bottom me-1"></i>
            Add Extra Field

        </button>

    </div>

</div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle"
                           id="fieldTypeTable">

                        <thead class="table-light">

                            <tr>

                            <th width="50">
                                <input type="checkbox">
                            </th>

                            <th>Field Name</th>
                            <th width="120">Input Type</th>
                            <th width="140">Source Master</th>
                            <th width="100">Sort Order</th>
                            <th width="120">Status</th>
                            <th width="120">Action</th>

                            </tr>

                        </thead>

                        <tbody>

                        </tbody>

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

<div class="modal fade"
     id="showModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header bg-light p-3">

                <h5 class="modal-title" id="modal-title">
                    Add Extra Field
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <form class="tablelist-form" enctype="multipart/form-data">

                @csrf

                <input type="hidden" id="edit-id">

                <div class="modal-body">

                    <div class="row">

                        <div class="col-md-12 mb-3">

                            <label class="form-label">
                                Field Name
                                <span class="text-danger">*</span>
                            </label>

                            <input type="text"
                                   id="field_name-field"
                                   class="form-control"
                                   maxlength="100"
                                   required>

                        </div>

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Input Type
                            </label>

                            <select id="input_type-field" class="form-select">
                                <option value="TEXT">Single Line (TEXT)</option>
                                <option value="TEXTAREA">Multi Line (TEXTAREA)</option>
                                <option value="SELECT">Dropdown from a Master (SELECT)</option>
                            </select>

                        </div>

                        <div class="col-md-6 mb-3" id="source_master-wrap" style="display:none;">

                            <label class="form-label">
                                Source Master
                                <span class="text-danger">*</span>
                            </label>

                            <select id="source_master-field" class="form-select">
                                <option value="">Select Master</option>
                                <option value="instrument">Instrument Master</option>
                                <option value="kit">Kit Master</option>
                                <option value="note">Note Master</option>
                                <option value="microscopy">Microscopy Master</option>
                                <option value="impression">Impression Master</option>
                            </select>

                        </div>

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Sort Order
                            </label>

                            <input type="number"
                                   id="sort_order-field"
                                   class="form-control"
                                   min="0"
                                   value="0">

                        </div>

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Status
                            </label>

                            <select id="status-field" class="form-select">
                                <option value="ACTIVE">ACTIVE</option>
                                <option value="INACTIVE">INACTIVE</option>
                            </select>

                        </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>

                    <button type="submit" class="btn btn-success" id="add-btn">Save Extra Field</button>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/test-extra-field-type.init.js') }}"></script>

@endsection
