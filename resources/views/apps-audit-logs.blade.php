@extends('layouts.master')

@section('title')
    Audit Logs
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
        Admin
    @endslot

    @slot('title')
        Audit Logs
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-shield-check-line"></i>
            Every create / update / delete / print / export action performed across the application is recorded
            here with who did it, when, from which IP, and exactly what changed.
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Filter</h5>
            </div>

            <div class="card-body">

                <div class="row align-items-end">

                    <div class="col-md-2 mb-3">
                        <label class="form-label">From Date</label>
                        <input type="text" id="from_date-field" class="form-control flatpickr">
                    </div>

                    <div class="col-md-2 mb-3">
                        <label class="form-label">To Date</label>
                        <input type="text" id="to_date-field" class="form-control flatpickr">
                    </div>

                    <div class="col-md-2 mb-3">
                        <label class="form-label">User</label>
                        <select id="user_id-field" class="form-select">
                            <option value="">-- All Users --</option>
                            @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 mb-3">
                        <label class="form-label">Module</label>
                        <select id="module_code-field" class="form-select">
                            <option value="">-- All Modules --</option>
                            @foreach($modules as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 mb-3">
                        <label class="form-label">Action</label>
                        <select id="action-field" class="form-select">
                            <option value="">-- All Actions --</option>
                            @foreach($actions as $action)
                            <option value="{{ $action }}">{{ $action }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 mb-3">
                        <label class="form-label">IP Address</label>
                        <input type="text" id="ip_address-field" class="form-control" placeholder="e.g. 192.168.1.">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Search (remarks / record / table / user)</label>
                        <input type="text" id="search-field" class="form-control" placeholder="Type to search...">
                    </div>

                    <div class="col-md-2 mb-3">
                        <button type="button" class="btn btn-primary w-100" id="applyFilterBtn">
                            <i class="ri-filter-3-line"></i>
                            Apply Filter
                        </button>
                    </div>

                </div>

            </div>

        </div>

        <div class="card">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3">

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

            </div>

            <div class="card-body pt-0">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th width="60">Sl</th>
                                <th width="130">Date/Time</th>
                                <th>User</th>
                                <th width="90">Role</th>
                                <th>Module</th>
                                <th width="100">Action</th>
                                <th>Table / Record</th>
                                <th>Remarks</th>
                                <th width="110">IP Address</th>
                                <th width="70">View</th>
                            </tr>
                        </thead>

                        <tbody id="auditTableBody"></tbody>

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

<!-- DETAIL MODAL -->

<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header bg-light p-3">
                <h5 class="modal-title">Audit Log Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body" id="detailModalBody">
                <div class="text-center text-muted py-4">Loading...</div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/audit-logs.init.js') }}"></script>

@endsection
