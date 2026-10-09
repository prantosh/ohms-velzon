@extends('layouts.master')

@section('title')
    Rest Test Result Entry
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

<link href="{{ URL::asset('build/libs/ckeditor5/browser/ckeditor5.css') }}"
      rel="stylesheet"
      type="text/css" />

<style>

.result-status-Pending { background:#f1963380; color:#7a4b00; }
.result-status-Partial { background:#299cdb33; color:#0f5f8c; }
.result-status-Complete { background:#0ab39c33; color:#03816f; }

/* CKEditor's table row/column toolbar renders as a balloon panel appended
   outside the Bootstrap modal (to a shared ck-body container on <body>),
   with a default z-index of 1000 -- lower than Bootstrap 5's own
   .modal (1055), so the modal painted on top of it, making the balloon
   present but invisible and unclickable (same fix as the USG Report page). */
.ck-balloon-panel {
    z-index: 10000 !important;
}

</style>

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Diagnostic Test
    @endslot

    @slot('title')
        Rest Test Result Entry
    @endslot

@endcomponent

<!-- DASHBOARD -->

<div class="card">

    <div class="card-header">
        <h5 class="card-title mb-0">Non-Pathology Invoices (X-Ray, Dental, Vaccination, ECG, Misc, etc.)</h5>
    </div>

    <div class="card-body">

        <div class="row g-2 align-items-end mb-3">

            <div class="col-md-4">

                <label class="form-label fw-semibold">Search</label>

                <input type="text"
                       id="dashSearchInput"
                       class="form-control"
                       placeholder="Invoice No / Patient Name / Mobile">

            </div>

            <div class="col-md-8">

                <label class="form-label fw-semibold d-block">Period</label>

                <div class="btn-group" role="group" id="rangeButtons">

                    <button type="button" class="btn btn-outline-primary" data-range="1">Last 1 Day</button>
                    <button type="button" class="btn btn-outline-primary active" data-range="3">Last 3 Days</button>
                    <button type="button" class="btn btn-outline-primary" data-range="7">Last 7 Days</button>
                    <button type="button" class="btn btn-outline-primary" data-range="30">Last 30 Days</button>
                    <button type="button" class="btn btn-outline-primary" data-range="all">All</button>

                </div>

            </div>

        </div>

        <div class="table-responsive">

            <table class="table table-bordered align-middle">

                <thead class="table-light">
                    <tr>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Mobile</th>
                        <th>Category</th>
                        <th>Test Description</th>
                        <th class="text-center">Test Count</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" width="140">Action</th>
                    </tr>
                </thead>

                <tbody id="dashTableBody"></tbody>

            </table>

        </div>

        <div id="dashNoDataWrap" class="text-muted text-center py-4" style="display:none;">
            No Non-Pathology invoices found for the selected filters.
        </div>

        <div class="d-flex justify-content-between align-items-center mt-2">

            <span id="dashPaginationInfo" class="text-muted small"></span>

            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="dashPrevPage">
                    <i class="ri-arrow-left-s-line"></i> Prev
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="dashNextPage">
                    Next <i class="ri-arrow-right-s-line"></i>
                </button>
            </div>

        </div>

    </div>

</div>

<!-- ENTRY MODAL -->

<div class="modal fade" id="nonPathReportModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-fullscreen-lg-down modal-xl">

        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Report Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="row mb-3 align-items-end">

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">Invoice Number</label>

                        <input type="text"
                               id="invoiceNoInput"
                               class="form-control"
                               readonly>

                    </div>

                </div>

                <div id="invoiceNotFoundMsg" class="text-danger mb-3" style="display:none;">
                    Invoice not found.
                </div>

                <div id="invoiceInfoWrap" class="mb-3" style="display:none;">

                    <div class="border rounded p-3 bg-light-subtle">

                        <div class="row">

                            <div class="col-md-3">
                                <strong>Invoice No:</strong>
                                <span id="info-invoice_no"></span>
                            </div>

                            <div class="col-md-3">
                                <strong>Invoice Date:</strong>
                                <span id="info-invoice_date"></span>
                            </div>

                            <div class="col-md-3">
                                <strong>Patient:</strong>
                                <span id="info-patient_name"></span>
                            </div>

                            <div class="col-md-3">
                                <strong>Age / Gender:</strong>
                                <span id="info-patient_age_gender"></span>
                            </div>

                        </div>

                    </div>

                </div>

                <div id="noQualifyingMsg" class="text-muted" style="display:none;">
                    No report-eligible tests found on this invoice.
                </div>

                <div id="nonPathologyReportsWrap"></div>

            </div>

        </div>

    </div>

</div>

<!-- NON-PATHOLOGY REPORT CARD TEMPLATE (cloned per line by JS) -->

<template id="nonPathReportCardTemplate">

    <div class="card nonpath-report-card mb-3">

        <div class="card-header d-flex justify-content-between align-items-center">

            <div>
                <strong class="nonpath-item-description"></strong>
                <span class="text-muted small nonpath-item-code-sub"></span>
                <span class="text-muted small ms-2">Reported By: <span class="nonpath-doctor-name">-</span></span>
            </div>

            <span class="badge bg-success nonpath-confirmed-badge" style="display:none;">
                <i class="ri-check-double-line"></i>
                Confirmed
            </span>

        </div>

        <div class="card-body">

            <div class="mb-3 nonpath-template-picker-wrap">
                <select class="form-select form-select-sm nonpath-template-picker" style="max-width:260px">
                    <option value="">-- Load Template --</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Clinical History</label>
                <textarea class="form-control nonpath-clinical-history" rows="2"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Findings</label>
                <textarea class="form-control nonpath-findings" rows="6"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Impression</label>
                <textarea class="form-control nonpath-impression" rows="3"></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2">

                <button type="button" class="btn btn-primary nonpath-save-btn">
                    <i class="ri-save-line"></i>
                    Save
                </button>

                <button type="button" class="btn btn-warning nonpath-confirm-btn">
                    <i class="ri-shield-check-line"></i>
                    Confirm
                </button>

                <a href="javascript:void(0)" class="btn btn-info nonpath-print-btn" style="display:none;" target="_blank">
                    <i class="ri-printer-line"></i>
                    Print Report
                </a>

                <button type="button" class="btn btn-success nonpath-whatsapp-btn" style="display:none;">
                    <i class="ri-whatsapp-line"></i>
                    Send via WhatsApp
                </button>

            </div>

        </div>

    </div>

</template>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/ckeditor5/browser/ckeditor5.umd.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/non-pathology-report.init.js') }}"></script>

@endsection
