@extends('layouts.master')

@section('title')
    Pathology Test Result Entry
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

<link href="{{ URL::asset('build/libs/ckeditor5/browser/ckeditor5.css') }}"
      rel="stylesheet"
      type="text/css" />

<style>

.extra-param-item {
    min-width: 220px;
    background: #fff;
}

.analyte-row td:nth-child(3) {
    padding-left: 24px;
}

.result-status-Pending { background:#f1963380; color:#7a4b00; }
.result-status-Partial { background:#299cdb33; color:#0f5f8c; }
.result-status-Complete { background:#0ab39c33; color:#03816f; }
.result-status-NA { background:#74788d33; color:#4a4d5a; }

</style>

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Diagnostic Test
    @endslot

    @slot('title')
        Pathology Test Result Entry
    @endslot

@endcomponent

<script id="extraFieldTypesData" type="application/json">{!! json_encode($extraFieldTypes) !!}</script>

<!-- DASHBOARD -->

<div class="card">

    <div class="card-header">
        <h5 class="card-title mb-0">Pathology Invoices</h5>
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
                        <th>Test Category</th>
                        <th>Test Description</th>
                        <th class="text-center">Test Count</th>
                        <th class="text-center">Result Status</th>
                        <th class="text-center">Confirmed</th>
                        <th class="text-center" width="220">Action</th>
                    </tr>
                </thead>

                <tbody id="dashTableBody"></tbody>

            </table>

        </div>

        <div id="dashNoDataWrap" class="text-muted text-center py-4" style="display:none;">
            No invoices found for the selected filters.
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

<div class="modal fade" id="resultEntryModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-fullscreen-lg-down modal-xl">

        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="resultEntryModalTitle">Enter Test Results</h5>
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

                            <div class="col-md-3 mt-2">
                                <strong>Referred Doctor:</strong>
                                <span id="info-referred_doctor"></span>
                            </div>

                            <div class="col-md-3 mt-2">
                                <strong>Status:</strong>
                                <span id="info-status"></span>
                            </div>

                        </div>

                    </div>

                </div>

                <div id="noQualifyingMsg" class="text-muted" style="display:none;">
                    No test-parameter-required items found on this invoice.
                </div>

                <!-- One WhatsApp status for the WHOLE invoice's Pathology
                     reports, not per test group/report -- the patient gets
                     exactly one combined message once every Pathology
                     report on the invoice is confirmed (PathologyReportController
                     ::confirm()/autoSendInvoiceWhatsapp()). -->
                <div id="pathologyWhatsappBanner" class="alert py-2 px-3 mb-3 d-none align-items-center justify-content-between">
                    <span id="pathologyWhatsappBanner-text"></span>
                    <button type="button" class="btn btn-sm btn-success ms-3" id="pathologyWhatsappBanner-sendBtn" style="display:none;">
                        <i class="ri-whatsapp-line"></i>
                        Send Now
                    </button>
                </div>

                <div id="pathologyReportsWrap" style="display:none;"></div>

            </div>

        </div>

    </div>

</div>

<!-- PATHOLOGY GROUP TAB PANE (cloned once per test group by JS -- header,
     existing report cards, and a picker to start a new report over
     whichever billed lines in this group aren't claimed by a report yet.) -->

<template id="pathologyGroupPaneTemplate">

    <div class="pathology-group-pane">

        <div class="d-flex justify-content-between align-items-center mt-2 mb-3">

            <h6 class="fw-semibold mb-0 pathology-group-title"></h6>

            <div class="d-flex gap-2">

                <!-- One Confirm for the WHOLE group -- saves and locks
                     every report in it together (pathology-report.confirm,
                     now invoice_no+test_group_code scoped). Shown once
                     every item in the group has a started report and at
                     least one isn't confirmed yet; replaced by Print once
                     the group is fully confirmed. -->
                <button type="button" class="btn btn-sm btn-warning pathology-confirm-group-btn" style="display:none;">
                    <i class="ri-shield-check-line"></i>
                    Confirm Group
                </button>

                <!-- Combines every confirmed report in THIS group into one
                     PDF (pathology-report.print-group) -- shown only once
                     every item in the group has a confirmed report; stays
                     hidden otherwise instead of erroring on click. -->
                <a href="javascript:void(0)" class="btn btn-sm btn-info pathology-print-group-btn" style="display:none;" target="_blank">
                    <i class="ri-printer-line"></i>
                    Print Group Report
                </a>

                <!-- Manual (re)send of the SAME combined WhatsApp message
                     that already goes out automatically once the whole
                     invoice is confirmed (pathology-report.send-whatsapp)
                     -- for resending on request, or after the automatic
                     send failed. Same visibility/precondition as Print All
                     Reports, which it sits beside. -->
                <button type="button" class="btn btn-sm btn-warning pathology-send-whatsapp-btn" style="display:none;">
                    <i class="ri-whatsapp-line"></i>
                    Send WhatsApp
                </button>

                <!-- Every confirmed report across EVERY test group on this
                     invoice, in the same combined document actually sent
                     via WhatsApp (pathology-report.print-invoice) -- shown
                     only once the WHOLE invoice is confirmed, same
                     precondition as the WhatsApp send itself. Present in
                     every group's pane (not just one), same reasoning as
                     the WhatsApp banner above being invoice-wide. -->
                <a href="javascript:void(0)" class="btn btn-sm btn-success pathology-print-invoice-btn" style="display:none;" target="_blank">
                    <i class="ri-printer-line"></i>
                    Print All Reports
                </a>

            </div>

        </div>

        <div class="pathology-group-findings"></div>

        <div class="border rounded p-3 bg-light-subtle pathology-start-wrap">

            <label class="form-label fw-semibold mb-2">Start a New Report</label>

            <select class="form-select form-select-sm pathology-start-picker">
                <option value="">-- Select a Template / Item --</option>
            </select>

        </div>

    </div>

</template>

<!-- PATHOLOGY REPORT CARD (cloned per report -- a report can cover one
     billed line or several bundled together by the chosen template.) -->

<template id="pathologyFindingCardTemplate">

    <div class="card pathology-finding-card mb-3">

        <div class="card-header d-flex justify-content-between align-items-center">

            <div>
                <strong class="pathology-item-description"></strong>
                <span class="text-muted small pathology-template-title"></span>
            </div>

            <span class="badge bg-success pathology-confirmed-badge" style="display:none;">
                <i class="ri-check-double-line"></i>
                Confirmed
            </span>

        </div>

        <div class="card-body">

            <textarea class="form-control pathology-content"></textarea>

            <div class="d-flex justify-content-end gap-2 mt-3">

                <button type="button" class="btn btn-primary pathology-save-btn">
                    <i class="ri-save-line"></i>
                    Save
                </button>

                <!-- Confirms/locks just THIS one report, independent of the
                     rest of its test group (pathology-report.confirm-finding)
                     -- for items that come back sooner than the rest of the
                     group. Sits alongside "Confirm Group" above; either path
                     works, and WhatsApp still only fires once every report
                     on the whole invoice is confirmed. -->
                <button type="button" class="btn btn-warning pathology-confirm-btn">
                    <i class="ri-shield-check-line"></i>
                    Confirm
                </button>

                <a href="javascript:void(0)" class="btn btn-info pathology-print-btn" style="display:none;" target="_blank">
                    <i class="ri-printer-line"></i>
                    Print Report
                </a>

            </div>

        </div>

    </div>

</template>

<!-- PATHOLOGY LEGACY FALLBACK CARD -- shown instead of the above when an
     invoice was already confirmed under the old analyte-grid system before
     this feature existed. Print/WhatsApp hit TestResultEntryController's
     untouched routes, exactly as they did before. -->

<template id="pathologyLegacyCardTemplate">

    <div class="card">

        <div class="card-body">

            <p class="mb-3">
                <i class="ri-information-line"></i>
                This invoice's Pathology report was confirmed under the previous report format. It can still be
                printed and sent from here; new-format reports are used for invoices going forward.
            </p>

            <a href="javascript:void(0)" class="btn btn-info pathology-legacy-print-btn" target="_blank">
                <i class="ri-printer-line"></i>
                Print Report
            </a>

            <button type="button" class="btn btn-success pathology-legacy-whatsapp-btn">
                <i class="ri-whatsapp-line"></i>
                Send via WhatsApp
            </button>

        </div>

    </div>

</template>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/ckeditor5/browser/ckeditor5.umd.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/test-result-entry.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/test-result-entry-dashboard.init.js') }}"></script>

@endsection
