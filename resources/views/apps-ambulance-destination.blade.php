@extends('layouts.master')

@section('title')
    Ambulance Destination Master
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Ambulance Rental
    @endslot

    @slot('title')
        Ambulance Destination Master
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">

                <div class="d-flex align-items-center gap-3 flex-wrap">

                    <div class="d-flex align-items-center">

                        <label class="me-2 mb-0 fw-semibold">Show</label>

                        <select id="perPage" class="form-select form-select-sm w-auto">

                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>

                        </select>

                        <span class="ms-2">records</span>

                    </div>

                    <div class="d-flex align-items-center">

                        <input type="text"
                               id="searchText"
                               class="form-control form-control-sm"
                               placeholder="Search destination...">

                    </div>

                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">

                    <button type="button"
                            class="btn btn-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#showModal"
                            id="addBtn">

                        <i class="ri-add-line align-bottom me-1"></i>
                        Add Destination

                    </button>

                </div>

            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle" id="destinationTable">

                        <thead class="table-light">

                            <tr>
                                <th width="60">Sl</th>
                                <th width="120">Code</th>
                                <th>Destination Name</th>
                                <th width="110">Fare (AC)</th>
                                <th width="110">Fare (Non-AC)</th>
                                <th width="100">Status</th>
                                <th width="150">Created By</th>
                                <th width="150">Created Date</th>
                                <th width="100">Action</th>
                            </tr>

                        </thead>

                        <tbody></tbody>

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

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header bg-light p-3">

                <h5 class="modal-title" id="modal-title">Add Ambulance Destination</h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

            </div>

            <form class="tablelist-form">

                @csrf

                <input type="hidden" id="edit-id">

                <div class="modal-body">

                    <div class="row">

                        <div class="col-md-4 mb-3">

                            <label class="form-label">Destination Code</label>

                            <input type="text" id="destination_code-field" class="form-control" readonly>

                        </div>

                        <div class="col-md-8 mb-3">

                            <label class="form-label">
                                Destination Name
                                <span class="text-danger">*</span>
                            </label>

                            <input type="text" id="destination_name-field" class="form-control" maxlength="150" required>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Fare (AC)
                                <span class="text-danger">*</span>
                            </label>

                            <input type="number" id="fare_ac-field" class="form-control" min="0" step="0.01" required>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Fare (Non-AC)
                                <span class="text-danger">*</span>
                            </label>

                            <input type="number" id="fare_nonac-field" class="form-control" min="0" step="0.01" required>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">Status</label>

                            <select id="status-field" class="form-select">

                                <option value="ACTIVE">ACTIVE</option>
                                <option value="INACTIVE">INACTIVE</option>

                            </select>

                        </div>

                        <div class="col-md-12 mb-3">

                            <label class="form-label">Remarks</label>

                            <textarea id="remarks-field" class="form-control" rows="3"></textarea>

                        </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>

                    <button type="submit" class="btn btn-success" id="add-btn">Save Destination</button>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/ambulance-destination.init.js') }}"></script>

@endsection
