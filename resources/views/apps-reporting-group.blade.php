@extends('layouts.master')

@section('title')
    Reporting Group Master
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
        Reporting Group Master
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-group-line"></i>
            Reporting groups are used for cash deposit reconciliation &mdash; each accountant is responsible for one
            group, and receives cash collected against the item codes assigned to that group. An item code can
            belong to only one reporting group at a time.
        </div>

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

                    <input type="text" id="searchInput" class="form-control form-control-sm w-auto" placeholder="Search group name...">

                </div>

                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#showModal">
                    <i class="ri-add-line align-bottom me-1"></i>
                    Add Reporting Group
                </button>

            </div>

            <div class="card-body pt-0">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th width="60">Sl</th>
                                <th width="200">Group Name</th>
                                <th>Item Codes</th>
                                <th>Remarks</th>
                                <th width="100">Status</th>
                                <th width="120">Action</th>
                            </tr>
                        </thead>

                        <tbody id="groupTableBody"></tbody>

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

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content">

            <div class="modal-header bg-light p-3">
                <h5 class="modal-title" id="modal-title">Add Reporting Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form class="tablelist-form">

                @csrf

                <input type="hidden" id="edit-id">

                <div class="modal-body">

                    <div class="row">

                        <div class="col-md-8 mb-3">
                            <label class="form-label">Group Name <span class="text-danger">*</span></label>
                            <input type="text" id="group_name-field" class="form-control" maxlength="150" required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select id="status-field" class="form-select">
                                <option value="ACTIVE" selected>Active</option>
                                <option value="INACTIVE">Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea id="remarks-field" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label">Item Codes <span class="text-danger">*</span></label>
                            <div class="border rounded p-2" style="max-height: 260px; overflow-y: auto;">
                                @foreach($itemMasters as $item)
                                <div class="form-check">
                                    <input class="form-check-input item-code-checkbox" type="checkbox" value="{{ $item->item_code }}" id="item-{{ $item->item_code }}">
                                    <label class="form-check-label" for="item-{{ $item->item_code }}">
                                        {{ $item->item_name }} <small class="text-muted">({{ $item->item_code }} &middot; {{ $item->item_type }})</small>
                                    </label>
                                </div>
                                @endforeach
                            </div>
                            <small class="text-muted">Select one or more item codes to include in this group. Each item code can belong to only one group.</small>
                        </div>

                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success" id="add-btn">Save Group</button>
                </div>

            </form>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/reporting-group.init.js') }}"></script>

@endsection
