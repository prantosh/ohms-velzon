@extends('layouts.master')

@section('title')
    Cash Adjustments
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
        Cash Adjustments
    @endslot
@endcomponent

<div class="alert alert-warning">
    <i class="ri-shield-keyhole-line"></i>
    A <b>cash deficit</b> appears when a user pays out more cash (doctor settlements, refunds) than they collected that day.
    It is <b>not</b> recovered from the user's later collections. Record here <b>why</b> it is legitimate -- cash handed back by
    Accounts, a bank withdrawal, or reimbursement of money the user paid -- ideally against the earlier deposit it relates to.
    The entry is dated to the deficit day, shows on that day's cash submission slip, and is not revenue.
    If the payout never really happened, cancel the doctor settlement instead.
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title mb-0">Pending Deficits</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered align-middle table-mobile-cards mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Deficit Date</th>
                        <th class="text-end">Pending Deficit</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="deficitBody"></tbody>
            </table>
        </div>
    </div>
</div>

<div class="card" id="formCard" style="display:none;">
    <div class="card-header"><h5 class="card-title mb-0">Post Adjustment</h5></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">User / Deficit Date</label>
                <input type="text" id="adjTarget" class="form-control" readonly>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Pending Deficit</label>
                <input type="text" id="adjDeficit" class="form-control" readonly>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Adjustment Amount</label>
                <input type="number" id="adjAmount" class="form-control" step="0.01" min="0.01">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Source of the cash</label>
                <select id="adjSource" class="form-select">
                    @foreach($sources as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Adjusted against deposit of (date)</label>
                <input type="date" id="adjRefDate" class="form-control" max="{{ now()->toDateString() }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Reference (slip / voucher no)</label>
                <input type="text" id="adjRefNote" class="form-control" maxlength="255">
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold">Remarks <span class="text-danger">*</span></label>
                <input type="text" id="adjRemarks" class="form-control" maxlength="500" placeholder="Why this payout is legitimate / who confirmed it">
            </div>
        </div>
        <div class="mt-3">
            <button type="button" class="btn btn-success" id="adjSave"><i class="ri-check-line"></i> Post Adjustment</button>
            <button type="button" class="btn btn-light" id="adjCancel">Cancel</button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title mb-0">Recent Adjustments</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered align-middle table-mobile-cards mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Adj. No</th>
                        <th>User</th>
                        <th>Deficit Date</th>
                        <th class="text-end">Amount</th>
                        <th>Source</th>
                        <th>Reference / Remarks</th>
                        <th>Approved By</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="recentBody"></tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@section('script')
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/cash-adjustment.init.js') }}?v={{ @filemtime(public_path('build/js/pages/cash-adjustment.init.js')) }}"></script>
@endsection
