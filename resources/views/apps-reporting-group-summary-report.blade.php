@extends('layouts.master')

@section('title')
    Reporting Group Summary Report
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
        Reporting Group Summary Report
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-file-list-3-line"></i>
            Reporting-Group-wise collection, refund and doctor-payment summary for a single day, across every user.
            <b>Doctor Payable</b> is the new amount accrued that day (invoices billed with a referring doctor);
            <b>Paid to Doctor</b> is the amount actually settled that day -- these routinely differ since a payable
            and its eventual settlement often happen on different days. Items not yet assigned to a Reporting Group
            appear under "Unassigned".
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Select Date</h5>
            </div>

            <div class="card-body">

                <div class="row align-items-end">

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="text" id="date-field" class="form-control flatpickr" required>
                    </div>

                    <div class="col-md-2 mb-3">
                        <button type="button" class="btn btn-primary w-100" id="loadReportBtn">
                            <i class="ri-search-line"></i>
                            Load
                        </button>
                    </div>

                </div>

            </div>

        </div>

        <div class="d-flex justify-content-end mb-2">
            <a href="javascript:void(0)" id="printReportBtn" class="btn btn-success" style="display:none;" target="_blank">
                <i class="ri-printer-line"></i>
                Print PDF
            </a>
        </div>

        <div class="row" id="totalsRow" style="display:none;">

            <div class="col mb-3">
                <div class="card h-100"><div class="card-body text-center">
                    <h5 class="mb-0" id="total-collection_cash">&#8377;0.00</h5>
                    <small class="text-muted">Collection Cash</small>
                </div></div>
            </div>

            <div class="col mb-3">
                <div class="card h-100"><div class="card-body text-center">
                    <h5 class="mb-0" id="total-collection_noncash">&#8377;0.00</h5>
                    <small class="text-muted">Collection Non-Cash</small>
                </div></div>
            </div>

            <div class="col mb-3">
                <div class="card h-100"><div class="card-body text-center">
                    <h5 class="mb-0 text-danger" id="total-refund">&#8377;0.00</h5>
                    <small class="text-muted">Refund</small>
                </div></div>
            </div>

            <div class="col mb-3">
                <div class="card h-100"><div class="card-body text-center">
                    <h5 class="mb-0" id="total-doctor_payable">&#8377;0.00</h5>
                    <small class="text-muted">Doctor Payable</small>
                </div></div>
            </div>

            <div class="col mb-3">
                <div class="card h-100"><div class="card-body text-center">
                    <h5 class="mb-0" id="total-paid_to_doctor">&#8377;0.00</h5>
                    <small class="text-muted">Paid to Doctor</small>
                </div></div>
            </div>

            <div class="col mb-3">
                <div class="card h-100 border border-success"><div class="card-body text-center">
                    <h5 class="mb-0 text-success" id="total-cash_to_deposit">&#8377;0.00</h5>
                    <small class="text-muted fw-semibold">Cash to Deposit</small>
                </div></div>
            </div>

        </div>

        <div class="card" id="reportCard" style="display:none;">

            <div class="card-header">
                <h5 class="card-title mb-0">Reporting Group Summary</h5>
            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th width="110" class="text-end">Collection Cash</th>
                                <th width="110" class="text-end">Collection Non-Cash</th>
                                <th width="100" class="text-end">Refund</th>
                                <th width="110" class="text-end">Doctor Payable</th>
                                <th width="110" class="text-end">Paid to Doctor</th>
                                <th width="120" class="text-end">Cash to Deposit</th>
                            </tr>
                        </thead>

                        <tbody id="reportTableBody"></tbody>

                    </table>

                </div>

            </div>

        </div>

        <div id="noDataWrap" class="alert alert-warning" style="display:none;">
            No activity found for the selected date.
        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/reporting-group-summary-report.init.js') }}"></script>

@endsection
