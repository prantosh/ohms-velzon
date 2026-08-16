@extends('layouts.master')

@section('title')
    USG Report
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

<style>

.result-status-Pending { background:#f1963380; color:#7a4b00; }
.result-status-Partial { background:#299cdb33; color:#0f5f8c; }
.result-status-Complete { background:#0ab39c33; color:#03816f; }

.usg-study-card textarea:disabled {
    background-color: #f8f9fa;
    color: #212529;
    opacity: 1;
}

</style>

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Diagnostic Test
    @endslot

    @slot('title')
        USG Report
    @endslot

@endcomponent

<!-- DASHBOARD -->

<div class="card">

    <div class="card-header">
        <h5 class="card-title mb-0">USG Invoices</h5>
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
            No USG invoices found for the selected filters.
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

<div class="modal fade" id="usgReportModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-fullscreen-lg-down modal-xl">

        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">USG Report Entry</h5>
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

                <div id="noStudiesMsg" class="text-muted" style="display:none;">
                    No USG studies found on this invoice.
                </div>

                <div id="studiesWrap"></div>

            </div>

        </div>

    </div>

</div>

<!-- PREVIEW MODAL -- shows the draft report as it would print, without
     saving or confirming; closing it returns to the still-open, still-
     editable entry modal underneath. -->

<div class="modal fade" id="usgPreviewModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-fullscreen-lg-down modal-xl">

        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Report Preview (Unsaved Draft)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-0">
                <iframe id="usgPreviewFrame" style="width:100%; height:80vh; border:none;"></iframe>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    Close &amp; Continue Editing
                </button>
            </div>

        </div>

    </div>

</div>

<!-- USG STUDY CARD TEMPLATE (cloned per line by JS) -->

<template id="usgStudyCardTemplate">

    <div class="card usg-study-card mb-3">

        <div class="card-header d-flex justify-content-between align-items-center">

            <div>
                <strong class="study-item-description"></strong>
                <span class="text-muted small study-item-code-sub"></span>
                <span class="text-muted small ms-2">Reported By: <span class="study-doctor-name">-</span></span>
            </div>

            <span class="badge bg-success study-confirmed-badge" style="display:none;">
                <i class="ri-check-double-line"></i>
                Confirmed
            </span>

        </div>

        <div class="card-body">

            <div class="mb-3">
                <label class="form-label fw-semibold">Load Template</label>
                <select class="form-select study-template-picker">
                    <option value="">-- Load Template --</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Clinical History</label>
                <textarea class="form-control study-clinical-history" rows="2" style="overflow-y:hidden; resize:none;"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Findings</label>
                <textarea class="form-control study-findings" rows="6" style="overflow-y:hidden; resize:none;"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Impression</label>
                <textarea class="form-control study-impression" rows="3" style="overflow-y:hidden; resize:none;"></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2">

                <button type="button" class="btn btn-primary study-save-btn">
                    <i class="ri-save-line"></i>
                    Save
                </button>

                <button type="button" class="btn btn-outline-secondary study-preview-btn">
                    <i class="ri-eye-line"></i>
                    Preview
                </button>

                <button type="button" class="btn btn-warning study-confirm-btn">
                    <i class="ri-shield-check-line"></i>
                    Confirm
                </button>

                <a href="javascript:void(0)" class="btn btn-info study-print-btn" style="display:none;" target="_blank">
                    <i class="ri-printer-line"></i>
                    Print Report
                </a>

                <button type="button" class="btn btn-success study-whatsapp-btn" style="display:none;">
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

<script src="{{ URL::asset('build/js/pages/usg-report.init.js') }}"></script>

@endsection
