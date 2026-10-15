@extends('layouts.master')

@section('title')
    Doctor Visit Collection Report
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
        Report
    @endslot

    @slot('title')
        Doctor Visit Collection Report
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-stethoscope-line"></i>
            Every Doctor Visit invoice splits into a <b>Doctor Share</b> (the doctor's fixed payable, from
            <code>doctor_payables</code>) and a <b>Clinic Share</b> (actual amount collected minus the doctor's
            share). <b>Summary</b> shows day-wise totals across a date range, broken down per specialisation.
            <b>Detail</b> lists every visit for one day and one specialisation.
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
                                <label class="form-label">
                                    To Date <span class="text-danger">*</span>
                                    <small class="text-muted">(max 7 days)</small>
                                </label>
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

                    <div class="col mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0" id="grand-visits">0</h5>
                            <small class="text-muted">Total Visits</small>
                        </div></div>
                    </div>

                    <div class="col mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0 text-success" id="grand-collected">0</h5>
                            <small class="text-muted">Total Collected</small>
                        </div></div>
                    </div>

                    <div class="col mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0 text-info" id="grand-doctor_share">0</h5>
                            <small class="text-muted">Doctor Share</small>
                        </div></div>
                    </div>

                    <div class="col mb-3">
                        <div class="card h-100 border border-success"><div class="card-body text-center">
                            <h5 class="mb-0 text-success" id="grand-clinic_share">0</h5>
                            <small class="text-muted fw-semibold">Clinic Share</small>
                        </div></div>
                    </div>

                </div>

                <div class="alert alert-light border small mb-3">
                    <b>How to read this table:</b> <code>Clinic Share = Collected &minus; Doctor Share</code> on
                    every row. Each date column shows that specialisation's own visit count and collected
                    amount for that day.
                </div>

                <div class="card" id="summaryCard" style="display:none;">

                    <div class="card-header">
                        <h5 class="card-title mb-0">Specialisation-Wise Summary</h5>
                    </div>

                    <div class="card-body">

                        <div class="table-responsive">

                            <table class="table table-bordered align-middle">

                                <thead class="table-light">
                                    <tr id="summaryTableHeadRow">
                                        <th width="150">Specialisation</th>
                                        <th width="80" class="text-end">Visits</th>
                                        <th width="110" class="text-end">Collected</th>
                                        <th width="110" class="text-end">Doctor Share</th>
                                        <th width="110" class="text-end">Clinic Share</th>
                                        <!-- date columns injected dynamically by JS -->
                                        <th width="90"></th>
                                    </tr>
                                </thead>

                                <tbody id="summaryTableBody"></tbody>

                            </table>

                        </div>

                    </div>

                </div>

                <div id="summaryNoDataWrap" class="alert alert-warning" style="display:none;">
                    No Doctor Visit invoices found in this date range.
                </div>

            </div>

            <!-- =======================================================
                 DETAIL TAB
            ======================================================= -->

            <div class="tab-pane" id="detailTab" role="tabpanel">

                <div class="card">

                    <div class="card-header">
                        <h5 class="card-title mb-0">Select Date &amp; Specialisation</h5>
                    </div>

                    <div class="card-body">

                        <div class="row align-items-end">

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="text" id="detail_date-field" class="form-control flatpickr" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Specialisation <span class="text-danger">*</span></label>
                                <select id="detail_specialisation-field" class="form-select" required>
                                    <option value="">Select Specialisation</option>
                                    @foreach($specialisations as $spec)
                                    <option value="{{ $spec }}">{{ $spec }}</option>
                                    @endforeach
                                    <option value="Unspecified">Unspecified</option>
                                </select>
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

                    <div class="col mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0" id="detail-visits">0</h5>
                            <small class="text-muted">Total Visits</small>
                        </div></div>
                    </div>

                    <div class="col mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0 text-success" id="detail-collected">0</h5>
                            <small class="text-muted">Total Collected</small>
                        </div></div>
                    </div>

                    <div class="col mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0 text-info" id="detail-doctor_share">0</h5>
                            <small class="text-muted">Doctor Share</small>
                        </div></div>
                    </div>

                    <div class="col mb-3">
                        <div class="card h-100 border border-success"><div class="card-body text-center">
                            <h5 class="mb-0 text-success" id="detail-clinic_share">0</h5>
                            <small class="text-muted fw-semibold">Clinic Share</small>
                        </div></div>
                    </div>

                </div>

                <div class="card" id="detailCard" style="display:none;">

                    <div class="card-header">
                        <h5 class="card-title mb-0" id="detailCardTitle">Visit Detail</h5>
                    </div>

                    <div class="card-body">

                        <div class="table-responsive">

                            <table class="table table-bordered align-middle">

                                <thead class="table-light">
                                    <tr>
                                        <th width="90">Time</th>
                                        <th>Invoice No</th>
                                        <th>Patient</th>
                                        <th>Doctor</th>
                                        <th width="110" class="text-end">Collected</th>
                                        <th width="110" class="text-end">Doctor Share</th>
                                        <th width="110" class="text-end">Clinic Share</th>
                                    </tr>
                                </thead>

                                <tbody id="detailTableBody"></tbody>

                            </table>

                        </div>

                    </div>

                </div>

                <div id="detailNoDataWrap" class="alert alert-warning" style="display:none;">
                    No Doctor Visit invoices found for this date and specialisation.
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

<script src="{{ URL::asset('build/js/pages/doctor-visit-collection-report.init.js') }}"></script>

@endsection
