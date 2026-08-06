@extends('layouts.master')

@section('title')
    WhatsApp Message Report
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
        WhatsApp Message Report
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-whatsapp-line"></i>
            Based on the <code>whatsapp_message_logs</code> table -- covers invoice, test report, appointment and OTP
            WhatsApp sends. <b>Summary</b> shows a day-wise Sent/Failed count across a date range. <b>Detail</b> lists
            every individual message sent on a single day.
        </div>

        <ul class="nav nav-tabs nav-tabs-custom mb-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#summaryTab" role="tab">
                    <i class="ri-bar-chart-2-line align-middle me-1"></i> Summary
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#detailTab" role="tab">
                    <i class="ri-list-check-2 align-middle me-1"></i> Detail
                </a>
            </li>
        </ul>

        <div class="tab-content">

            <!-- =======================================================
                 SUMMARY TAB
            ======================================================= -->

            <div class="tab-pane active" id="summaryTab" role="tabpanel">

                <div class="card">

                    <div class="card-header">
                        <h5 class="card-title mb-0">Select Date Range</h5>
                    </div>

                    <div class="card-body">

                        <div class="row align-items-end">

                            <div class="col-md-3 mb-3">
                                <label class="form-label">From Date <span class="text-danger">*</span></label>
                                <input type="text" id="summary_from_date-field" class="form-control flatpickr" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">To Date <span class="text-danger">*</span></label>
                                <input type="text" id="summary_to_date-field" class="form-control flatpickr" required>
                            </div>

                            <div class="col-md-2 mb-3">
                                <button type="button" class="btn btn-primary" id="loadSummaryBtn">
                                    <i class="ri-search-line"></i>
                                    Load
                                </button>
                            </div>

                            <div class="col-md-2 mb-3">
                                <a href="javascript:void(0)" id="printSummaryBtn" class="btn btn-success" style="display:none;" target="_blank">
                                    <i class="ri-printer-line"></i>
                                    Print PDF
                                </a>
                            </div>

                        </div>

                    </div>

                </div>

                <div class="row" id="summaryTotalsRow" style="display:none;">

                    <div class="col-md-3 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0" id="grand-total_count">0</h5>
                            <small class="text-muted">Total Messages</small>
                        </div></div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0 text-success" id="grand-sent_count">0</h5>
                            <small class="text-muted">Sent</small>
                        </div></div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0 text-danger" id="grand-failed_count">0</h5>
                            <small class="text-muted">Failed</small>
                        </div></div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card h-100 border border-success"><div class="card-body text-center">
                            <h5 class="mb-0 text-success" id="grand-success_rate">0%</h5>
                            <small class="text-muted fw-semibold">Success Rate</small>
                        </div></div>
                    </div>

                </div>

                <div class="card" id="summaryCard" style="display:none;">

                    <div class="card-header">
                        <h5 class="card-title mb-0">Day-Wise Summary</h5>
                    </div>

                    <div class="card-body">

                        <div class="table-responsive">

                            <table class="table table-bordered align-middle">

                                <thead class="table-light">
                                    <tr>
                                        <th width="130">Date</th>
                                        <th width="90" class="text-end">Total</th>
                                        <th width="90" class="text-end">Sent</th>
                                        <th width="90" class="text-end">Failed</th>
                                        <th width="90" class="text-end">Invoice</th>
                                        <th width="100" class="text-end">Test Report</th>
                                        <th width="100" class="text-end">Appointment</th>
                                        <th width="80" class="text-end">OTP</th>
                                        <th width="90"></th>
                                    </tr>
                                </thead>

                                <tbody id="summaryTableBody"></tbody>

                            </table>

                        </div>

                    </div>

                </div>

                <div id="summaryNoDataWrap" class="alert alert-warning" style="display:none;">
                    No WhatsApp messages found in this date range.
                </div>

            </div>

            <!-- =======================================================
                 DETAIL TAB
            ======================================================= -->

            <div class="tab-pane" id="detailTab" role="tabpanel">

                <div class="card">

                    <div class="card-header">
                        <h5 class="card-title mb-0">Select Date</h5>
                    </div>

                    <div class="card-body">

                        <div class="row align-items-end">

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="text" id="detail_date-field" class="form-control flatpickr" required>
                            </div>

                            <div class="col-md-2 mb-3">
                                <button type="button" class="btn btn-primary" id="loadDetailBtn">
                                    <i class="ri-search-line"></i>
                                    Load
                                </button>
                            </div>

                            <div class="col-md-2 mb-3">
                                <a href="javascript:void(0)" id="printDetailBtn" class="btn btn-success" style="display:none;" target="_blank">
                                    <i class="ri-printer-line"></i>
                                    Print PDF
                                </a>
                            </div>

                        </div>

                    </div>

                </div>

                <div class="row" id="detailTotalsRow" style="display:none;">

                    <div class="col-md-3 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0" id="detail-total_count">0</h5>
                            <small class="text-muted">Total Messages</small>
                        </div></div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0 text-success" id="detail-sent_count">0</h5>
                            <small class="text-muted">Sent</small>
                        </div></div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0 text-danger" id="detail-failed_count">0</h5>
                            <small class="text-muted">Failed</small>
                        </div></div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card h-100 border border-success"><div class="card-body text-center">
                            <h5 class="mb-0 text-success" id="detail-success_rate">0%</h5>
                            <small class="text-muted fw-semibold">Success Rate</small>
                        </div></div>
                    </div>

                </div>

                <div class="card" id="detailCard" style="display:none;">

                    <div class="card-header">
                        <h5 class="card-title mb-0">Message Detail</h5>
                    </div>

                    <div class="card-body">

                        <div class="table-responsive">

                            <table class="table table-bordered align-middle">

                                <thead class="table-light">
                                    <tr>
                                        <th width="90">Time</th>
                                        <th>Invoice No</th>
                                        <th width="120">Mobile No</th>
                                        <th width="110">Type</th>
                                        <th width="90">Status</th>
                                        <th>Message ID / Error</th>
                                    </tr>
                                </thead>

                                <tbody id="detailTableBody"></tbody>

                            </table>

                        </div>

                    </div>

                </div>

                <div id="detailNoDataWrap" class="alert alert-warning" style="display:none;">
                    No WhatsApp messages found for this date.
                </div>

            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/whatsapp-message-report.init.js') }}"></script>

@endsection
