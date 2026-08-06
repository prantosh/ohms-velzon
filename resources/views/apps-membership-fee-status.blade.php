@extends('layouts.master')

@section('title')
    Membership Fee Payment Status
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Billing
    @endslot

    @slot('title')
        Membership Fee Payment Status
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
                            <option value="100">100</option>
                        </select>
                        <span class="ms-2">records</span>
                    </div>

                    <select id="role-field" class="form-select form-select-sm w-auto">
                        <option value="">-- All Roles --</option>
                        <option value="Member">Member</option>
                        <option value="Admin">Admin</option>
                        <option value="Supervisor">Supervisor</option>
                    </select>

                    <select id="status-field" class="form-select form-select-sm w-auto">
                        <option value="">-- All Status --</option>
                        <option value="up_to_date">Up To Date</option>
                        <option value="overdue">Overdue</option>
                    </select>

                    <input type="text" id="searchInput" class="form-control form-control-sm w-auto" placeholder="Search name / mobile...">

                </div>

            </div>

            <div class="card-body pt-0">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th width="60">Sl</th>
                                <th>Name</th>
                                <th width="120">Mobile No</th>
                                <th width="110">Role</th>
                                <th width="140">Last Paid Month</th>
                                <th width="140">Next Due Month</th>
                                <th width="120">Months Overdue</th>
                                <th width="130">Due Amount (&#8377;)</th>
                                <th width="120">Status</th>
                            </tr>
                        </thead>

                        <tbody id="statusTableBody"></tbody>

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

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/membership-fee-status.init.js') }}"></script>

@endsection
