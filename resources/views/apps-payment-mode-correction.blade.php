@extends('layouts.master')

@section('title')
    Payment Mode Correction
@endsection

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')

@component('components.breadcrumb')
    @slot('li_1')
        Admin
    @endslot
    @slot('title')
        Payment Mode Correction
    @endslot
@endcomponent

<div class="alert alert-warning">
    <i class="ri-shield-keyhole-line"></i>
    Correct the payment mode (<b>Cash / Card / UPI</b>) of a collection that was recorded wrongly. The amount, date and
    operator do not change. The invoice, the daily transaction record and the operator's <b>cash in hand</b> for that day
    are all updated together, and the change is written to the audit log. Invoice PDFs already sent to the patient are not changed.
</div>

<div class="card">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold">Invoice No / Transaction No / Patient Mobile No</label>
                <input type="text" id="searchInput" class="form-control" placeholder="e.g. LAB/1126/103/0001 or 9876543210">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-primary w-100" id="searchBtn">
                    <i class="ri-search-line"></i> Search
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card" id="listCard" style="display:none;">
    <div class="card-header"><h5 class="card-title mb-0">Invoices for this mobile number</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle table-mobile-cards mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Patient</th>
                        <th class="text-end">Paid</th>
                        <th>Mode</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="listBody"></tbody>
            </table>
        </div>
    </div>
</div>

<div class="card" id="invoiceCard" style="display:none;">
    <div class="card-header">
        <h5 class="card-title mb-0">Invoice <span id="invNo"></span></h5>
    </div>
    <div class="card-body">

        <div id="cancelledWarning" class="alert alert-danger" style="display:none;">This invoice is cancelled -- its payment mode cannot be changed.</div>

        <div class="row g-3 mb-3 small">
            <div class="col-6 col-md-4"><div class="text-muted">Patient</div><div class="fw-semibold" id="invPatient"></div></div>
            <div class="col-6 col-md-2"><div class="text-muted">Invoice Date</div><div class="fw-semibold" id="invDate"></div></div>
            <div class="col-4 col-md-2"><div class="text-muted">Total</div><div class="fw-semibold" id="invTotal"></div></div>
            <div class="col-4 col-md-2"><div class="text-muted">Paid</div><div class="fw-semibold" id="invPaid"></div></div>
            <div class="col-4 col-md-2"><div class="text-muted">Due</div><div class="fw-semibold" id="invDue"></div></div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle table-mobile-cards mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Transaction No</th>
                        <th>Date / Time</th>
                        <th>Collected By</th>
                        <th>Remarks</th>
                        <th class="text-end">Amount</th>
                        <th>Mode</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="paymentsBody"></tbody>
            </table>
        </div>

    </div>
</div>

{{-- Change modal --}}
<div class="modal fade" id="modeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Payment Mode</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 small">
                    <div><span class="text-muted">Transaction:</span> <b id="mTxn"></b></div>
                    <div><span class="text-muted">Amount:</span> <b id="mAmount"></b></div>
                    <div><span class="text-muted">Recorded as:</span> <b id="mCurrent"></b></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Correct mode</label>
                    <select id="mMode" class="form-select">
                        @foreach($modes as $mode)
                            <option value="{{ $mode }}">{{ $mode }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3" id="mRefWrap" style="display:none;">
                    <label class="form-label fw-semibold">Reference (last 4 digits)</label>
                    <input type="text" id="mRef" class="form-control" maxlength="4" inputmode="numeric" pattern="\d{4}" placeholder="4-digit code">
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                    <textarea id="mReason" class="form-control" rows="2" maxlength="500" placeholder="Why is this being corrected?"></textarea>
                </div>
                <div class="alert alert-info py-2 mt-3 mb-0 small" id="mCashNote" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="mSave">Save</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/payment-mode-correction.init.js') }}?v={{ @filemtime(public_path('build/js/pages/payment-mode-correction.init.js')) }}"></script>
@endsection
