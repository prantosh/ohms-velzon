@extends('layouts.master')

@section('title')
    Invoice Cancellation
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Billing
    @endslot

    @slot('title')
        Invoice Cancellation
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Search Invoice</h5>
            </div>

            <div class="card-body">

                <form id="searchForm">

                    <div class="row align-items-end">

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Invoice Number <span class="text-danger">*</span></label>
                            <input type="text" id="invoice_no-field" class="form-control" placeholder="e.g. AMB/0726/001/0001" required>
                        </div>

                        <div class="col-md-2 mb-3">
                            <button type="submit" class="btn btn-primary" id="searchBtn">
                                <i class="ri-search-line"></i>
                                Search
                            </button>
                        </div>

                    </div>

                </form>

                <div id="notFoundWrap" class="alert alert-warning" style="display:none;">
                    No invoice found with this invoice number.
                </div>

            </div>

        </div>

        <div class="card" id="invoiceResultCard" style="display:none;">

            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Invoice Details</h5>
                <span id="statusBadge"></span>
            </div>

            <div class="card-body">

                <div class="table-responsive mb-4">

                    <table class="table table-sm table-bordered">
                        <tbody>
                            <tr>
                                <th width="20%" class="label-cell">Invoice No</th>
                                <td width="30%" id="detail-invoice_no"></td>
                                <th width="20%" class="label-cell">Invoice Type</th>
                                <td width="30%" id="detail-invoice_type"></td>
                            </tr>
                            <tr>
                                <th class="label-cell">Invoice Date</th>
                                <td id="detail-invoice_date"></td>
                                <th class="label-cell">Patient / Party</th>
                                <td id="detail-patient_name"></td>
                            </tr>
                            <tr>
                                <th class="label-cell">Mobile No</th>
                                <td id="detail-patient_mobile_no"></td>
                                <th class="label-cell">Payment Mode</th>
                                <td id="detail-payment_mode"></td>
                            </tr>
                            <tr>
                                <th class="label-cell">Total Amount (&#8377;)</th>
                                <td id="detail-total_amount"></td>
                                <th class="label-cell">Paid Amount (&#8377;)</th>
                                <td id="detail-paid_amount"></td>
                            </tr>
                            <tr>
                                <th class="label-cell">Due Amount (&#8377;)</th>
                                <td id="detail-due_amount"></td>
                                <th class="label-cell">Received By</th>
                                <td id="detail-received_by_name"></td>
                            </tr>
                            <tr>
                                <th class="label-cell">Remarks</th>
                                <td colspan="3" id="detail-remarks"></td>
                            </tr>
                        </tbody>
                    </table>

                </div>

                <div class="table-responsive mb-4">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th width="60">Sl</th>
                                <th>Item / Description</th>
                                <th width="100">Qty</th>
                                <th width="120">Rate (&#8377;)</th>
                                <th width="120">Amount (&#8377;)</th>
                            </tr>
                        </thead>

                        <tbody id="detailsTableBody"></tbody>

                    </table>

                </div>

                <div id="alreadyCancelledWrap" class="alert alert-danger" style="display:none;">

                    <strong>This invoice has already been cancelled.</strong><br>
                    Cancelled By: <span id="cancelled-by"></span> on <span id="cancelled-at"></span><br>
                    <span id="cancelled-approval-wrap" style="display:none;">
                        Approved By: <span id="cancelled-approved-by"></span><br>
                    </span>
                    Remarks: <span id="cancelled-remarks"></span>

                </div>

                <div id="permissionGrantedInfoWrap" class="alert alert-success" style="display:none;">
                    <i class="ri-shield-check-line"></i>
                    Cancellation permission granted by <span id="permission-granted-by"></span>
                    on <span id="permission-granted-at"></span>. Remarks: <span id="permission-remarks"></span>
                </div>

                <div id="permissionRequiredWrap" class="alert alert-warning" style="display:none;">
                    <i class="ri-shield-user-line"></i>
                    This invoice was not created today. It cannot be cancelled until a Supervisor or Admin grants
                    permission for it via the Cancellation Permission dashboard.
                </div>

                <div id="cancelActionWrap" style="display:none;">

                    <button type="button" class="btn btn-danger" id="btnOpenCancel">
                        <i class="ri-close-circle-line"></i>
                        Cancel Invoice
                    </button>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- CANCEL OFFCANVAS -->

<div class="offcanvas offcanvas-end" tabindex="-1" id="cancelOffcanvas" style="width: 450px;">

    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title">Cancel Invoice</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body">

        <table class="table table-sm table-bordered mb-4">
            <tbody>
                <tr><th width="45%">Invoice No</th><td id="oc-invoice_no"></td></tr>
                <tr><th>Invoice Date</th><td id="oc-invoice_date"></td></tr>
                <tr><th>Patient / Party</th><td id="oc-patient_name"></td></tr>
                <tr><th>Paid Amount</th><td id="oc-paid_amount"></td></tr>
            </tbody>
        </table>

        <div class="alert alert-info" id="oc-approval-note" style="display:none;">
            This invoice was not created today. It has cancellation permission granted by
            <strong id="oc-permission-granted-by"></strong>.
        </div>

        <div class="mb-3">
            <label class="form-label">Cancellation Remarks <span class="text-danger">*</span></label>
            <textarea id="cancellation_remarks-field" class="form-control" rows="3" placeholder="Reason for cancellation" required></textarea>
        </div>

        <div class="d-flex gap-2">

            <button type="button" class="btn btn-light w-50" data-bs-dismiss="offcanvas">
                Back
            </button>

            <button type="button" class="btn btn-danger w-50" id="btnConfirmCancel">
                <i class="ri-close-circle-line"></i>
                Confirm Cancel
            </button>

        </div>

    </div>

</div>

@endsection

@section('script')

<style>
    .label-cell {
        background-color: var(--vz-light);
        font-weight: 600;
    }
</style>

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/invoice-cancellation.init.js') }}"></script>

@endsection
