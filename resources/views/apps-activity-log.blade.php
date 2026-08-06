@extends('layouts.master')

@section('title')
    Activity Log
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
        Billing
    @endslot

    @slot('title')
        Activity Log
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Filter</h5>
            </div>

            <div class="card-body">

                <div class="row align-items-end">

                    <div class="col-md-3 mb-3">
                        <label class="form-label">From Date</label>
                        <input type="text" id="from_date-field" class="form-control flatpickr">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">To Date</label>
                        <input type="text" id="to_date-field" class="form-control flatpickr">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">User</label>
                        <select id="user_id-field" class="form-select">
                            <option value="">-- All Employees / Supervisors / Admins --</option>
                            @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <button type="button" class="btn btn-primary" id="applyFilterBtn">
                            <i class="ri-filter-3-line"></i>
                            Apply Filter
                        </button>
                    </div>

                </div>

            </div>

        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Login Activity</h5>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 pt-0">

                <div class="d-flex align-items-center">
                    <label class="me-2 mb-0 fw-semibold">Show</label>
                    <select id="loginPerPage" class="form-select form-select-sm w-auto">
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
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
                                <th>User</th>
                                <th>Role</th>
                                <th>Login Time</th>
                                <th>Logout Time</th>
                                <th width="110">Status</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>

                        <tbody id="loginTableBody"></tbody>

                    </table>

                    <div class="d-flex justify-content-between align-items-center mt-3">

                        <div id="login-pagination-info"></div>

                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-sm btn-primary" id="loginPrevPage">Previous</button>
                            <span id="loginPageNumber">Page 1</span>
                            <button class="btn btn-sm btn-primary" id="loginNextPage">Next</button>
                        </div>

                    </div>

                </div>

            </div>

        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Audit / Action Activity</h5>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 pt-0">

                <div class="d-flex align-items-center gap-3 flex-wrap">

                    <div class="d-flex align-items-center">
                        <label class="me-2 mb-0 fw-semibold">Show</label>
                        <select id="auditPerPage" class="form-select form-select-sm w-auto">
                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                        <span class="ms-2">records</span>
                    </div>

                    <select id="module_code-field" class="form-select form-select-sm w-auto">
                        <option value="">-- All Modules --</option>
                        @foreach($modules as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    <select id="action-field" class="form-select form-select-sm w-auto">
                        <option value="">-- All Actions --</option>
                        @foreach($actions as $action)
                        <option value="{{ $action }}">{{ $action }}</option>
                        @endforeach
                    </select>

                    <input type="text" id="auditSearchInput" class="form-control form-control-sm w-auto" placeholder="Search remarks / record / invoice no...">

                </div>

            </div>

            <div class="card-body pt-0">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th width="60">Sl</th>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Module</th>
                                <th width="100">Action</th>
                                <th>Table / Record</th>
                                <th>Invoice No</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>

                        <tbody id="auditTableBody"></tbody>

                    </table>

                    <div class="d-flex justify-content-between align-items-center mt-3">

                        <div id="audit-pagination-info"></div>

                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-sm btn-primary" id="auditPrevPage">Previous</button>
                            <span id="auditPageNumber">Page 1</span>
                            <button class="btn btn-sm btn-primary" id="auditNextPage">Next</button>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/activity-log.init.js') }}"></script>

@endsection
