@extends('layouts.master')

@section('title')
    Additional Discount Report
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
        Additional Discount Report
    @endslot
@endcomponent

<div class="alert alert-info">
    <i class="ri-discount-percent-line"></i>
    Every <b>additional (manual) discount</b> given across diagnostic, doctor visit, rental and other invoices, with the
    approving authority and the operator who raised the invoice. Cancelled invoices are excluded from totals.
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title mb-0">Filters</h5></div>
    <div class="card-body">
        <div class="row align-items-end">

            <div class="col-md-2 mb-3">
                <label class="form-label">From Date <span class="text-danger">*</span></label>
                <input type="text" id="from_date-field" class="form-control flatpickr" required>
            </div>

            <div class="col-md-2 mb-3">
                <label class="form-label">To Date <span class="text-danger">*</span></label>
                <input type="text" id="to_date-field" class="form-control flatpickr" required>
            </div>

            <div class="col-md-3 mb-3">
                <label class="form-label">Invoice Type</label>
                <select id="invoice_type-field" class="form-select">
                    <option value="ALL">All</option>
                    @foreach($invoiceTypes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3 mb-3">
                <label class="form-label">Approved By</label>
                <select id="approver_id-field" class="form-select">
                    <option value="">All</option>
                    @foreach($approvers as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2 mb-3">
                <label class="form-label">Operator</label>
                <select id="operator_id-field" class="form-select">
                    <option value="">All</option>
                    @foreach($operators as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="include_cancelled-field">
                    <label class="form-check-label" for="include_cancelled-field">Show cancelled invoices (not in totals)</label>
                </div>
            </div>

            <div class="col-md-2 mb-3">
                <button type="button" class="btn btn-primary" id="loadBtn"><i class="ri-search-line"></i> Load</button>
            </div>

            <div class="col-md-2 mb-3">
                <a href="javascript:void(0)" id="printBtn" class="btn btn-success" style="display:none;" target="_blank">
                    <i class="ri-printer-line"></i> Print PDF
                </a>
            </div>

        </div>
    </div>
</div>

<div class="row" id="totalsRow" style="display:none;">
    <div class="col-md-4 mb-3">
        <div class="card h-100"><div class="card-body text-center">
            <h5 class="mb-0" id="sum-invoices">0</h5><small class="text-muted">Invoices</small>
        </div></div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100"><div class="card-body text-center">
            <h5 class="mb-0" id="sum-lines">0</h5><small class="text-muted">Discounted Lines</small>
        </div></div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100 border border-danger"><div class="card-body text-center">
            <h5 class="mb-0 text-danger" id="sum-total">&#8377;0.00</h5><small class="text-muted fw-semibold">Total Additional Discount</small>
        </div></div>
    </div>
</div>

<div class="row" id="breakupRow" style="display:none;">
    <div class="col-md-4 mb-3">
        <div class="card h-100"><div class="card-header"><h6 class="mb-0">By Approver</h6></div>
        <div class="card-body p-0"><table class="table table-sm mb-0"><tbody id="byApproverBody"></tbody></table></div></div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100"><div class="card-header"><h6 class="mb-0">By Operator</h6></div>
        <div class="card-body p-0"><table class="table table-sm mb-0"><tbody id="byOperatorBody"></tbody></table></div></div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100"><div class="card-header"><h6 class="mb-0">By Invoice Type</h6></div>
        <div class="card-body p-0"><table class="table table-sm mb-0"><tbody id="byTypeBody"></tbody></table></div></div>
    </div>
</div>

<div class="card" id="detailCard" style="display:none;">
    <div class="card-header"><h5 class="card-title mb-0">Discount Detail</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th width="95">Date</th>
                        <th>Invoice No</th>
                        <th>Type</th>
                        <th>Patient</th>
                        <th>Item</th>
                        <th width="95" class="text-end">Rate (&#8377;)</th>
                        <th width="70" class="text-end">Disc %</th>
                        <th width="100" class="text-end">Discount (&#8377;)</th>
                        <th>Approved By</th>
                        <th>Operator</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody id="detailBody"></tbody>
            </table>
        </div>
    </div>
</div>

<div id="noDataWrap" class="alert alert-warning" style="display:none;">
    No additional discounts found for the selected filters.
</div>

@endsection

@section('script')
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/additional-discount-report.init.js') }}?v={{ filemtime(public_path('build/js/pages/additional-discount-report.init.js')) }}"></script>
@endsection
