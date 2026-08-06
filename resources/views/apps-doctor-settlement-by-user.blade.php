@extends('layouts.master')

@section('title')
    Doctor Settlement (By User)
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

<link href="{{ URL::asset('build/libs/flatpickr/flatpickr.min.css') }}" rel="stylesheet" type="text/css" />

<style>
    .doctor-group-card .card-header {
        background-color: var(--vz-light);
    }
    .settlement-table th, .settlement-table td {
        white-space: nowrap;
        font-size: 13px;
    }
</style>

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Admin
    @endslot

    @slot('title')
        Doctor Settlement (By User)
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-user-search-line"></i>
            Select a user and an invoice date to see outstanding doctor payables that came from invoices created
            by that user on that date, grouped by doctor. Settlements are created one doctor at a time &mdash;
            this reuses the same settlement engine as the regular Doctor Settlement screen.
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Select User &amp; Invoice Date</h5>
            </div>

            <div class="card-body">

                <div class="row align-items-end">

                    <div class="col-md-3 mb-3">
                        <label class="form-label">User <span class="text-danger">*</span></label>
                        <select id="user_id-field" class="form-select" required>
                            <option value="">-- Select User --</option>
                            @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Invoice Date <span class="text-danger">*</span></label>
                        <input type="text" id="invoice_date-field" class="form-control flatpickr" required>
                    </div>

                    <div class="col-md-2 mb-3">
                        <button type="button" class="btn btn-primary" id="loadBtn">
                            <i class="ri-search-line"></i>
                            Load
                        </button>
                    </div>

                </div>

            </div>

        </div>

        <div class="card" id="settlementFieldsCard" style="display:none;">

            <div class="card-header">
                <h5 class="card-title mb-0">Settlement Details (applied when you confirm any doctor below)</h5>
            </div>

            <div class="card-body">

                <div class="row g-3">

                    <div class="col-md-3">
                        <label class="form-label">Settlement Date</label>
                        <input type="text" id="settlement_date-field" class="form-control flatpickr">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Payment Mode</label>
                        <select id="payment_mode-field" class="form-select">
                            @foreach($paymentModes as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 pm-field" data-modes="BANK,CHEQUE" style="display:none;">
                        <label class="form-label">Bank Name</label>
                        <input type="text" id="bank_name-field" class="form-control">
                    </div>

                    <div class="col-md-3 pm-field" data-modes="CHEQUE" style="display:none;">
                        <label class="form-label">Cheque No</label>
                        <input type="text" id="cheque_no-field" class="form-control">
                    </div>

                    <div class="col-md-3 pm-field" data-modes="BANK,UPI" style="display:none;">
                        <label class="form-label">UTR / Transaction No</label>
                        <input type="text" id="utr_no-field" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Remarks</label>
                        <input type="text" id="remarks-field" class="form-control" placeholder="Settlement remarks...">
                    </div>

                </div>

            </div>

        </div>

        <div class="row" id="summaryRow" style="display:none;">

            <div class="col-md-4">
                <div class="card"><div class="card-body text-center">
                    <h5 class="mb-0" id="summary-total_doctors">0</h5>
                    <small class="text-muted">Doctors With Outstanding</small>
                </div></div>
            </div>

            <div class="col-md-4">
                <div class="card"><div class="card-body text-center">
                    <h5 class="mb-0" id="summary-total_invoices">0</h5>
                    <small class="text-muted">Outstanding Invoices</small>
                </div></div>
            </div>

            <div class="col-md-4">
                <div class="card"><div class="card-body text-center">
                    <h5 class="mb-0 text-danger" id="summary-total_outstanding">&#8377;0.00</h5>
                    <small class="text-muted">Total Outstanding</small>
                </div></div>
            </div>

        </div>

        <div id="doctorGroupsWrap"></div>

        <div id="noDataWrap" class="alert alert-warning" style="display:none;">
            No outstanding doctor payables found for this user's invoices on this date.
        </div>

    </div>

</div>

<!-- SETTLEMENT PREVIEW OFFCANVAS -->

<div class="offcanvas offcanvas-end" tabindex="-1" id="settlementPreviewOffcanvas" style="width: 550px;">

    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title">Confirm Doctor Settlement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body">

        <table class="table table-sm table-bordered mb-3">
            <tbody>
                <tr><th width="45%">Doctor</th><td id="pv-doctor_name"></td></tr>
                <tr><th>Settlement Date</th><td id="pv-settlement_date"></td></tr>
                <tr><th>Payment Mode</th><td id="pv-payment_mode"></td></tr>
                <tr><th>Selected Invoices</th><td id="pv-count"></td></tr>
            </tbody>
        </table>

        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Invoice No</th>
                        <th>Patient</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody id="pv-items"></tbody>
            </table>
        </div>

        <table class="table table-sm table-borderless mb-3">
            <tr class="table-success">
                <th>Net Settlement Amount</th>
                <td class="text-end fw-bold" id="pv-net">&#8377;0.00</td>
            </tr>
        </table>

        <div class="d-flex gap-2">

            <button type="button" class="btn btn-light w-50" data-bs-dismiss="offcanvas">
                Back
            </button>

            <button type="button" class="btn btn-success w-50" id="btnConfirmSettle">
                <i class="ri-checkbox-circle-line"></i>
                Confirm Settlement
            </button>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/doctor-settlement-by-user.init.js') }}"></script>

@endsection
