@extends('layouts.master')

@section('title')
    Invoice Cancellation (Partial)
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
        Invoice Cancellation (Partial)
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
                    Your cancellation request was granted by <span id="permission-granted-by"></span>
                    on <span id="permission-granted-at"></span>. You can now cancel this invoice.
                </div>

                <div id="grantedToOtherWrap" class="alert alert-danger" style="display:none;">
                    <i class="ri-shield-cross-line"></i>
                    Cancellation permission for this invoice was granted to <strong id="granted-to-other-name"></strong>.
                    Only they can cancel it.
                </div>

                <div id="pendingOwnWrap" class="alert alert-warning" style="display:none;">
                    <i class="ri-time-line"></i>
                    Your cancellation request (submitted <span id="pending-own-at"></span>) is awaiting approval
                    from a Supervisor or Admin.
                </div>

                <div id="pendingOtherWrap" class="alert alert-warning" style="display:none;">
                    <i class="ri-time-line"></i>
                    <strong id="pending-other-name"></strong> has already requested cancellation permission for
                    this invoice (pending approval). Only they will be able to cancel it once granted.
                </div>

                <div id="requestPermissionWrap" style="display:none;">

                    <div class="alert alert-secondary">
                        <i class="ri-shield-user-line"></i>
                        This invoice was not created today. Submit a reason below to request permission from a
                        Supervisor or Admin -- once granted, only you will be able to cancel it.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reason for Cancellation <span class="text-danger">*</span></label>
                        <textarea id="request_reason-field" class="form-control" rows="3" placeholder="Why does this invoice need to be cancelled?" required></textarea>
                    </div>

                    <button type="button" class="btn btn-warning" id="btnRequestPermission">
                        <i class="ri-send-plane-line"></i>
                        Request Cancellation Permission
                    </button>

                </div>

                <div id="supersededWrap" class="alert alert-info" style="display:none;">
                    <i class="ri-file-copy-line"></i>
                    Partially cancelled. Replaced by Modified Invoice <strong id="superseded-by"></strong>.
                </div>

                <div id="modifiedFromWrap" class="alert alert-info" style="display:none;">
                    <i class="ri-file-copy-line"></i>
                    Modified Invoice &mdash; supersedes <strong id="modified-from"></strong>.
                </div>

                <div id="notEligibleWrap" class="alert alert-secondary" style="display:none;"></div>

                <div id="partialWrap" class="border rounded p-3" style="display:none;">

                    <h6 class="mb-1">Cancel individual items</h6>
                    <p class="text-muted small mb-3">
                        Tick the item(s) to cancel. Items that already have a report cannot be cancelled.
                        A new <strong>Modified Invoice</strong> is issued for the remaining items, and any
                        refund or due is worked out from what has already been paid.
                    </p>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-2">
                            <thead class="table-light">
                                <tr>
                                    <th width="120" class="text-center">Select to Cancel</th>
                                    <th>Item Description</th>
                                    <th width="110" class="text-end">Rate (&#8377;)</th>
                                    <th width="110" class="text-end">Discount (&#8377;)</th>
                                    <th width="120" class="text-end">Amount (&#8377;)</th>
                                    <th width="300">Status</th>
                                </tr>
                            </thead>
                            <tbody id="partialLinesBody"></tbody>
                        </table>
                    </div>

                    <table class="table table-sm table-bordered w-auto mb-3" id="partialPreview">
                        <tbody>
                            <tr><th class="label-cell">Cancelled items value</th><td class="text-end" id="pp-cancelled">0.00</td></tr>
                            <tr><th class="label-cell">New invoice total</th><td class="text-end" id="pp-newtotal">0.00</td></tr>
                            <tr><th class="label-cell">Already paid</th><td class="text-end" id="pp-paid">0.00</td></tr>
                            <tr><th class="label-cell" id="pp-result-label">Refund / Due</th><td class="text-end fw-bold" id="pp-result">0.00</td></tr>
                        </tbody>
                    </table>

                    <div id="collectWrap" class="row g-3 mb-3" style="display:none;">
                        <div class="col-12">
                            <div class="alert alert-warning mb-0">
                                <i class="ri-money-rupee-circle-line"></i>
                                A balance will still be due on the Modified Invoice. You may collect any amount
                                up to the due amount now (leave 0 to collect later).
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Collect Now (&#8377;)</label>
                            <input type="number" id="collect_amount-field" class="form-control" min="0" step="0.01" value="0">
                            <div class="form-text">Maximum: &#8377; <span id="collect-max">0.00</span></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Payment Mode</label>
                            <select id="collect_mode-field" class="form-select">
                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="UPI">UPI</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Due after collection (&#8377;)</label>
                            <input type="text" id="collect-balance" class="form-control" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remarks <span class="text-danger">*</span></label>
                        <textarea id="partial_remarks-field" class="form-control" rows="2" placeholder="Reason for cancelling these items"></textarea>
                    </div>

                    <button type="button" class="btn btn-warning" id="btnPartialCancel" disabled>
                        <i class="ri-file-reduce-line"></i>
                        Cancel Selected Items &amp; Issue Modified Invoice
                    </button>

                    <a href="{{ url('/') }}" class="btn btn-light ms-2" id="btnDoNotCancel">
                        <i class="ri-arrow-go-back-line"></i>
                        Do Not Cancel
                    </a>

                </div>



            </div>

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

<script src="{{ URL::asset('build/js/pages/invoice-partial-cancellation.init.js') }}"></script>

@endsection
