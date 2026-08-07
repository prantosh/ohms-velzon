@extends('layouts.master')

@section('title')
    Test Report Template Master
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
        Test Report Template Master
    @endslot

@endcomponent

<script id="extraFieldTypesData" type="application/json">{!! json_encode($extraFieldTypes) !!}</script>

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-information-line"></i>
            Choose which parameters this template should carry -- for each one, pick a starting value from its
            master list (still editable). At report entry, staff pick this template by test type and it fills
            Remarks plus every parameter configured here in one go; everything stays editable per patient.
        </div>

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
                   placeholder="Search title / test type">
        </div>

    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap">

        <button type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#showModal"
                id="addTemplateBtn">

            <i class="ri-add-line align-bottom me-1"></i>
            Add Template

        </button>

    </div>

</div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle"
                           id="templateTable">

                        <thead class="table-light">

                            <tr>

                            <th width="50">
                                <input type="checkbox">
                            </th>

                            <th>Title</th>
                            <th>Test Type</th>
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

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content">

            <div class="modal-header bg-light p-3">

                <h5 class="modal-title" id="modal-title">
                    Add Template
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

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Title
                                <span class="text-danger">*</span>
                            </label>

                            <input type="text"
                                   id="title-field"
                                   class="form-control"
                                   maxlength="150"
                                   placeholder="e.g. Normal Microscopy"
                                   required>

                        </div>

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Test Type
                                <span class="text-danger">*</span>
                            </label>

                            <select id="item_code_sub-field" class="form-select" required>
                                <option value="">-- Select Test --</option>
                                @foreach($testTypes as $testType)
                                <option value="{{ $testType->item_code_sub }}">{{ $testType->item_description_sub }}</option>
                                @endforeach
                            </select>

                        </div>

                        <div class="col-md-12 mb-3">

                            <label class="form-label">
                                Remarks
                            </label>

                            <textarea id="remarks-field"
                                      class="form-control"
                                      rows="2"></textarea>

                        </div>

                        <div class="col-md-12 mb-3">

                            <label class="form-label">
                                Parameters
                            </label>

                            <div id="templateParamsContainer" class="d-flex flex-wrap gap-2 align-items-start">

                                <div class="template-param-add-wrap position-relative">
                                    <button class="btn btn-sm btn-soft-primary add-template-param-btn" type="button">
                                        <i class="ri-add-line"></i> Add Parameter
                                    </button>
                                    <ul class="dropdown-menu template-param-menu" style="max-height: 420px; overflow-y: auto; z-index: 1065;"></ul>
                                </div>

                            </div>

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

                    <button type="submit" class="btn btn-success" id="add-btn">Save Template</button>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/test-report-template.init.js') }}"></script>

@endsection
