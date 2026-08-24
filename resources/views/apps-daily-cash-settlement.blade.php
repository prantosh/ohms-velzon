@extends('layouts.master')

@section('title')
    Daily Cash Settlement
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
        Daily Cash Settlement
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-user-search-line"></i>
            Select a user and a day to see every doctor payable settled (cash paid to the doctor) that
            day, grouped doctor-wise with a total shown after each doctor's list of invoices.
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Select User &amp; Day</h5>
            </div>

            <div class="card-body">

                <div class="row align-items-end">

                    <div class="col-md-4 mb-3">
                        <label class="form-label">User <span class="text-danger">*</span></label>
                        <select id="user_id-field" class="form-select" required>
                            <option value="">-- Select User --</option>
                            <option value="ALL">-- All Users --</option>
                            @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="text" id="date-field" class="form-control flatpickr" value="{{ date('Y-m-d') }}" required>
                    </div>

                    <div class="col-md-2 mb-3">
                        <button type="button" class="btn btn-primary" id="loadReportBtn">
                            <i class="ri-search-line"></i>
                            Load
                        </button>
                    </div>

                </div>

            </div>

        </div>

        <div class="row" id="summaryRow" style="display:none;">

            <div class="col-md-4 mb-3">
                <div class="card h-100"><div class="card-body text-center">
                    <h5 class="mb-0" id="summary-total_doctors">0</h5>
                    <small class="text-muted">Doctors Settled</small>
                </div></div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="card h-100"><div class="card-body text-center">
                    <h5 class="mb-0" id="summary-total_invoices">0</h5>
                    <small class="text-muted">Invoices Settled</small>
                </div></div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="card h-100 border border-success"><div class="card-body text-center">
                    <h5 class="mb-0 text-success" id="summary-total_amount">&#8377;0.00</h5>
                    <small class="text-muted fw-semibold">Total Amount Settled</small>
                </div></div>
            </div>

        </div>

        <div id="reportHeaderWrap" class="d-flex justify-content-between align-items-center mb-2" style="display:none;">
            <h5 class="mb-0" id="reportTitle">Daily Cash Settlement</h5>
            <a href="javascript:void(0)" id="printReportBtn" class="btn btn-success btn-sm" target="_blank">
                <i class="ri-printer-line"></i>
                Print PDF
            </a>
        </div>

        <div id="doctorGroupsWrap"></div>

        <div id="noDataWrap" class="alert alert-warning" style="display:none;">
            No doctor payables were settled for this user on this day.
        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/daily-cash-settlement.init.js') }}"></script>

@endsection
