@extends('layouts.master')

@section('title')
    Test Result Entry
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
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

/* The confirm-review offcanvas opens on top of the result-entry modal,
   but Bootstrap's default z-index puts .offcanvas (1045) BELOW .modal
   (1055) -- so without this it renders partially hidden behind the
   already-open modal. */
#confirmOffcanvas {
    z-index: 1075;
}

.offcanvas-backdrop.show {
    z-index: 1065;
}

</style>

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Diagnostic Test
    @endslot

    @slot('title')
        Test Result Entry
    @endslot

@endcomponent

<script id="extraFieldTypesData" type="application/json">{!! json_encode($extraFieldTypes) !!}</script>

<!-- DASHBOARD -->

<div class="card">

    <div class="card-header">

        <ul class="nav nav-tabs card-header-tabs" id="categoryTabs">

            <li class="nav-item">
                <button type="button" class="nav-link active" data-category="PATHOLOGY">Pathology</button>
            </li>

            <li class="nav-item">
                <button type="button" class="nav-link" data-category="NON_PATHOLOGY">Non-Pathology</button>
            </li>

        </ul>

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

                <div id="resultTableWrap" style="display:none;">

                    <div class="table-responsive">

                        <table class="table table-bordered align-middle">

                            <thead class="table-light">

                                <tr>
                                    <th>Item Code</th>
                                    <th>Sub Code</th>
                                    <th>Description</th>
                                    <th>UOM</th>
                                    <th>Range (Male)</th>
                                    <th>Range (Female)</th>
                                    <th>Range (Common)</th>
                                    <th>Method</th>
                                    <th width="15%">Result Value</th>
                                    <th width="15%">Remarks</th>
                                    <th width="8%">Action</th>
                                </tr>

                            </thead>

                            <tbody id="resultTableBody">

                            </tbody>

                        </table>

                    </div>

                    <div class="d-flex justify-content-end align-items-center gap-2 mt-3">

                        <span id="confirmedBadge" class="badge bg-success" style="display:none;">
                            <i class="ri-check-double-line"></i>
                            Confirmed
                        </span>

                        <button class="btn btn-warning" id="btnConfirmReport">
                            <i class="ri-shield-check-line"></i>
                            Confirm
                        </button>

                        <button class="btn btn-info" id="btnPrintReport" disabled>
                            <i class="ri-printer-line"></i>
                            Print Report
                        </button>

                        <button class="btn btn-success" id="btnWhatsappReport" disabled>
                            <i class="ri-whatsapp-line"></i>
                            Send via WhatsApp
                        </button>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- CONFIRM REVIEW OFFCANVAS -->

<div class="offcanvas offcanvas-end"
     tabindex="-1"
     id="confirmOffcanvas"
     style="width: 50%;">

    <div class="offcanvas-header border-bottom">

        <h5 class="offcanvas-title">Review Entered Test Results</h5>

        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>

    </div>

    <div class="offcanvas-body">

        <div class="border rounded p-3 bg-light-subtle mb-3">

            <div class="row">

                <div class="col-6">
                    <strong>Invoice No:</strong>
                    <span id="review-invoice_no"></span>
                </div>

                <div class="col-6">
                    <strong>Patient:</strong>
                    <span id="review-patient_name"></span>
                </div>

            </div>

        </div>

        <div class="table-responsive">

            <table class="table table-bordered align-middle">

                <thead class="table-light">
                    <tr>
                        <th>Description</th>
                        <th>Result Value</th>
                        <th>UOM</th>
                        <th>Remarks</th>
                    </tr>
                </thead>

                <tbody id="reviewTableBody">

                </tbody>

            </table>

        </div>

        <div id="reviewExtraParamsWrap" style="display:none;">

            <h6 class="fw-semibold mt-3">Extra Parameters</h6>

            <div class="table-responsive">

                <table class="table table-bordered align-middle">

                    <thead class="table-light">
                        <tr>
                            <th>Field</th>
                            <th>Value</th>
                        </tr>
                    </thead>

                    <tbody id="reviewExtraParamsBody">

                    </tbody>

                </table>

            </div>

        </div>

        <div class="text-end mt-3">

            <button class="btn btn-warning" id="btnConfirmLock">
                <i class="ri-shield-check-line"></i>
                Confirm &amp; Lock
            </button>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/test-result-entry.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/test-result-entry-dashboard.init.js') }}"></script>

@endsection
