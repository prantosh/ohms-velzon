@extends('layouts.master')

@section('title')
    User Invoice Reconciliation
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
        User Invoice Reconciliation
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-user-search-line"></i>
            Select a user and date to see every invoice that user generated that day, across all billing modules,
            with settlement status for Doctor Visit invoices &mdash; useful for reconciling a user's day against
            what has since been paid out to doctors.
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Select User &amp; Date</h5>
            </div>

            <div class="card-body">

                <div class="row align-items-end">

                    <div class="col-md-4 mb-3">
                        <label class="form-label">User <span class="text-danger">*</span></label>
                        <select id="user_id-field" class="form-select" required>
                            <option value="">-- Select User --</option>
                            @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
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
                    <small class="text-muted">Total Invoices</small>
                </div></div>
            </div>

            <div class="col-md-2">
                <div class="card"><div class="card-body text-center">
                    <h5 class="mb-0" id="summary-total_amount">&#8377;0.00</h5>
                    <small class="text-muted">Total Collection</small>
                </div></div>
            </div>

            <div class="col-md-2">
                <div class="card"><div class="card-body text-center">
                    <h5 class="mb-0" id="summary-doc_invoice_count">0</h5>
                    <small class="text-muted">Doctor Visits</small>
                </div></div>
            </div>

            <div class="col-md-2">
                <div class="card"><div class="card-body text-center">
                    <h5 class="mb-0 text-success" id="summary-doc_settled_count">0</h5>
                    <small class="text-muted">DOC Settled</small>
                </div></div>
            </div>

            <div class="col-md-2">
                <div class="card"><div class="card-body text-center">
                    <h5 class="mb-0 text-danger" id="summary-doc_not_settled_count">0</h5>
                    <small class="text-muted">DOC Not Settled</small>
                </div></div>
            </div>

            <div class="col-md-2">
                <div class="card"><div class="card-body text-center">
                    <h5 class="mb-0 text-danger" id="summary-doc_total_balance">&#8377;0.00</h5>
                    <small class="text-muted">DOC Balance</small>
                </div></div>
            </div>

        </div>

        <div class="card" id="paymentModeCard" style="display:none;">

            <div class="card-header">
                <h5 class="card-title mb-0">Collection by Payment Mode</h5>
            </div>

            <div class="card-body">
                <div class="row text-center" id="paymentModeRow"></div>
            </div>

        </div>

        <div class="card" id="listCard" style="display:none;">

            <div class="card-header">
                <h5 class="card-title mb-0">Invoices</h5>
            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th>Invoice No</th>
                                <th>Type</th>
                                <th>Patient / Party</th>
                                <th width="110">Time</th>
                                <th width="100">Amount (&#8377;)</th>
                                <th width="90">Mode</th>
                                <th width="100">Payable (&#8377;)</th>
                                <th width="100">Settled (&#8377;)</th>
                                <th width="100">Balance (&#8377;)</th>
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
            No invoices found for this user on this date.
        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/user-invoice-report.init.js') }}"></script>

@endsection
