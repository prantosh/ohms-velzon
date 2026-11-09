@extends('layouts.master')

@section('title')
    Invoice Doctor Correction
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
        Invoice Doctor Correction
    @endslot
@endcomponent

<div class="alert alert-warning">
    <i class="ri-shield-keyhole-line"></i>
    Add or correct the performing doctor on a saved diagnostic invoice. The doctor's payable is taken from
    <b>Doctor Test Payment Master</b> for the selected doctor and test; for <b>manual categories</b> the doctor amount
    is typed here (Dental Manual items without a rule list all Dental doctors). The patient's total, paid and due amounts
    do not change -- only the clinic / doctor split. The doctor is paid through <b>Doctor Settlement</b> by the
    user who collected the invoice's final payment, once nothing is due.
</div>

<div class="card">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold">Invoice No / Patient Mobile No</label>
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
    <div class="card-header"><h5 class="card-title mb-0">Diagnostic invoices for this mobile number</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle table-mobile-cards mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Patient</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Due</th>
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

        <div id="cancelledWarning" class="alert alert-danger" style="display:none;">This invoice is cancelled.</div>

        <div class="row g-3 mb-3 small">
            <div class="col-6 col-md-3"><div class="text-muted">Patient</div><div class="fw-semibold" id="invPatient"></div></div>
            <div class="col-6 col-md-2"><div class="text-muted">Invoice Date</div><div class="fw-semibold" id="invDate"></div></div>
            <div class="col-4 col-md-2"><div class="text-muted">Total</div><div class="fw-semibold" id="invTotal"></div></div>
            <div class="col-4 col-md-1"><div class="text-muted">Paid</div><div class="fw-semibold" id="invPaid"></div></div>
            <div class="col-4 col-md-1"><div class="text-muted">Due</div><div class="fw-semibold" id="invDue"></div></div>
            <div class="col-6 col-md-1"><div class="text-muted">Doctor Share</div><div class="fw-semibold" id="invDoctorShare"></div></div>
            <div class="col-6 col-md-2"><div class="text-muted">Settles via user</div><div class="fw-semibold" id="invSettledBy"></div></div>
        </div>

        <div id="dueInfo" class="alert alert-info py-2" style="display:none;">
            <i class="ri-information-line"></i>
            This invoice still has a due amount. The doctor's payable can be settled only after the patient pays in full.
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle table-mobile-cards mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Test</th>
                        <th class="text-end">Amount</th>
                        <th>Current Doctor</th>
                        <th class="text-end">Doctor Payable</th>
                        <th>Payable Status</th>
                        <th style="min-width:260px;">New Doctor (rate)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="linesBody"></tbody>
            </table>
        </div>

    </div>
</div>

@endsection

@section('script')
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/invoice-doctor-correction.init.js') }}?v={{ @filemtime(public_path('build/js/pages/invoice-doctor-correction.init.js')) }}"></script>
@endsection
