@extends('layouts.master')

@section('title')
    Doctor Visit Payment Report
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
        Doctor
    @endslot

    @slot('title')
        Doctor Visit Payment Report
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-money-rupee-circle-line"></i>
            Select a doctor and date to see the settlement/payment status of that day's Doctor Visit (DOC-prefixed)
            invoices &mdash; whether each visit's payment to the doctor has been settled, and if so, under which
            settlement voucher.
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Select Doctor &amp; Date</h5>
            </div>

            <div class="card-body">

                <div class="row align-items-end">

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Doctor <span class="text-danger">*</span></label>
                        <select id="doctor_id-field" class="form-select" required>
                            <option value="">-- Select Doctor --</option>
                            @foreach($doctors as $doctor)
                            <option value="{{ $doctor->id }}">{{ $doctor->doctor_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="text" id="date-field" class="form-control flatpickr" required>
                    </div>

                    <div class="col-md-2 mb-3">
                        <button type="button" class="btn btn-primary" id="loadReportBtn">
                            <i class="ri-search-line"></i>
                            Load Report
                        </button>
                    </div>

                    <div class="col-md-3 mb-3">
                        <a href="javascript:void(0)" id="printReportBtn" class="btn btn-success" style="display:none;" target="_blank">
                            <i class="ri-printer-line"></i>
                            Print Report
                        </a>
                    </div>

                </div>

            </div>

        </div>

        <div class="row" id="summaryRow" style="display:none;">

            <div class="col-md-2">
                <div class="card"><div class="card-body text-center">
                    <h5 class="mb-0" id="summary-total_invoices">0</h5>
                    <small class="text-muted">Total Visits</small>
                </div></div>
            </div>

            <div class="col-md-2">
                <div class="card"><div class="card-body text-center">
                    <h5 class="mb-0 text-success" id="summary-settled_count">0</h5>
                    <small class="text-muted">Settled</small>
                </div></div>
            </div>

            <div class="col-md-2">
                <div class="card"><div class="card-body text-center">
                    <h5 class="mb-0 text-danger" id="summary-not_settled_count">0</h5>
                    <small class="text-muted">Not Settled</small>
                </div></div>
            </div>

            <div class="col-md-2">
                <div class="card"><div class="card-body text-center">
                    <h5 class="mb-0" id="summary-total_payable">&#8377;0.00</h5>
                    <small class="text-muted">Total Payable</small>
                </div></div>
            </div>

            <div class="col-md-2">
                <div class="card"><div class="card-body text-center">
                    <h5 class="mb-0 text-success" id="summary-total_settled_amount">&#8377;0.00</h5>
                    <small class="text-muted">Total Settled</small>
                </div></div>
            </div>

            <div class="col-md-2">
                <div class="card"><div class="card-body text-center">
                    <h5 class="mb-0 text-danger" id="summary-total_balance">&#8377;0.00</h5>
                    <small class="text-muted">Balance</small>
                </div></div>
            </div>

        </div>

        <div class="card" id="listCard" style="display:none;">

            <div class="card-header">
                <h5 class="card-title mb-0">Doctor Visit Invoices</h5>
            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th>Invoice No</th>
                                <th>Patient Name</th>
                                <th width="110">Time</th>
                                <th width="120">Payable (&#8377;)</th>
                                <th width="120">Settled (&#8377;)</th>
                                <th width="120">Balance (&#8377;)</th>
                                <th>Settlement No</th>
                                <th width="120">Status</th>
                            </tr>
                        </thead>

                        <tbody id="listTableBody"></tbody>

                    </table>

                </div>

            </div>

        </div>

        <div id="noDataWrap" class="alert alert-warning" style="display:none;">
            No Doctor Visit (DOC-prefixed) invoices found for this doctor on this date.
        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/doctor-visit-payment-report.init.js') }}"></script>

@endsection
