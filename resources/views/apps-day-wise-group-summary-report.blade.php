@extends('layouts.master')

@section('title')
    Day-wise Group Summary Report
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ URL::asset('build/libs/flatpickr/flatpickr.min.css') }}" rel="stylesheet" type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')
    @slot('li_1')
        Admin
    @endslot
    @slot('title')
        Day-wise Group Summary Report
    @endslot
@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-file-list-3-line"></i>
            Day-wise, user-wise and Reporting-Group-wise (Pathology, Dental, etc.) cash collection, non-cash collection,
            refund, doctor payable, amount paid to doctors and <b>Cash to Deposit</b>
            (cash collected &minus; refunds &minus; cash paid to doctors) for any date range.
            Leave User / Group on "All" to include everyone.
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Filters</h5>
            </div>
            <div class="card-body">
                <div class="row align-items-end">

                    <div class="col-md-2 mb-3">
                        <label class="form-label">From Date <span class="text-danger">*</span></label>
                        <input type="text" id="from-date" class="form-control flatpickr" required>
                    </div>

                    <div class="col-md-2 mb-3">
                        <label class="form-label">To Date <span class="text-danger">*</span></label>
                        <input type="text" id="to-date" class="form-control flatpickr" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">User</label>
                        <select id="user-id" class="form-select">
                            <option value="">All Users</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Group</label>
                        <select id="group" class="form-select">
                            <option value="">All Groups</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->group_name }}</option>
                            @endforeach
                            <option value="unassigned">Unassigned (No Reporting Group)</option>
                        </select>
                    </div>

                    <div class="col-md-2 mb-3">
                        <button type="button" class="btn btn-primary w-100" id="loadReportBtn">
                            <i class="ri-search-line"></i> Load
                        </button>
                    </div>

                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mb-2">
            <a href="javascript:void(0)" id="printReportBtn" class="btn btn-success" style="display:none;" target="_blank">
                <i class="ri-printer-line"></i> Print PDF
            </a>
        </div>

        <div class="row" id="totalsRow" style="display:none;">
            @foreach([
                'collection_cash' => ['Collection Cash', ''],
                'collection_noncash' => ['Collection Non-Cash', ''],
                'refund' => ['Refund', 'text-danger'],
                'doctor_payable' => ['Doctor Payable', ''],
                'paid_to_doctor' => ['Paid to Doctor', ''],
                'cash_to_deposit' => ['Cash to Deposit', 'text-success'],
            ] as $key => [$label, $cls])
            <div class="col mb-3">
                <div class="card h-100 {{ $key === 'cash_to_deposit' ? 'border border-success' : '' }}"><div class="card-body text-center">
                    <h5 class="mb-0 {{ $cls }}" id="total-{{ $key }}">&#8377;0.00</h5>
                    <small class="text-muted">{{ $label }}</small>
                </div></div>
            </div>
            @endforeach
        </div>

        <div class="card" id="reportCard" style="display:none;">
            <div class="card-header">
                <h5 class="card-title mb-0">Day-wise Summary</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Group</th>
                                <th width="110" class="text-end">Collection Cash</th>
                                <th width="110" class="text-end">Collection Non-Cash</th>
                                <th width="100" class="text-end">Refund</th>
                                <th width="110" class="text-end">Doctor Payable</th>
                                <th width="110" class="text-end">Paid to Doctor</th>
                                <th width="120" class="text-end">Cash to Deposit</th>
                            </tr>
                        </thead>
                        <tbody id="reportTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="noDataWrap" class="alert alert-warning" style="display:none;">
            No activity found for the selected filters.
        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/day-wise-group-summary-report.init.js') }}?v={{ filemtime(public_path('build/js/pages/day-wise-group-summary-report.init.js')) }}"></script>

@endsection
