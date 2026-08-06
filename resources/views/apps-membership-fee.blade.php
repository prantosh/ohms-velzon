@extends('layouts.master')

@section('title')
    Membership Fee Collection
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Membership Fee
    @endslot

    @slot('title')
        Membership Fee Collection
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="card-body">

                <div class="row mb-3 align-items-end">

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">Member Mobile Number</label>

                        <input type="text"
                               id="mobileInput"
                               class="form-control"
                               maxlength="10"
                               placeholder="Enter 10 digit mobile number">

                    </div>

                    <div class="col-md-2">

                        <button class="btn btn-primary w-100" id="btnSearchMember">
                            <i class="ri-search-line"></i>
                            Search
                        </button>

                    </div>

                </div>

                <div id="memberNotFoundMsg" class="text-danger mb-3" style="display:none;">
                    No member found with this mobile number.
                </div>

                <div id="memberInfoWrap" style="display:none;">

                    <div class="border rounded p-3 bg-light-subtle mb-3">

                        <div class="row">

                            <div class="col-md-3">
                                <strong>Name:</strong>
                                <span id="info-name"></span>
                            </div>

                            <div class="col-md-3">
                                <strong>Mobile:</strong>
                                <span id="info-mobile"></span>
                            </div>

                            <div class="col-md-3">
                                <strong>Status:</strong>
                                <span id="info-status"></span>
                            </div>

                            <div class="col-md-3">
                                <strong>Last Paid Month:</strong>
                                <span id="info-last-paid">Never Paid</span>
                            </div>

                        </div>

                    </div>

                    <ul class="nav nav-tabs mb-3">

                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#payTab">Collect Fee</a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#historyTab">Payment History</a>
                        </li>

                        <li class="nav-item" id="adjustTabItem" style="display:none;">
                            <a class="nav-link" data-bs-toggle="tab" href="#adjustTab">Admin: Adjust Last Paid Month</a>
                        </li>

                    </ul>

                    <div class="tab-content">

                        <!-- COLLECT FEE -->

                        <div class="tab-pane fade show active" id="payTab">

                            <div class="row mb-3">

                                <div class="col-md-3">
                                    <label class="form-label">From Month</label>
                                    <input type="month" id="from_month-field" class="form-control">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">To Month</label>
                                    <input type="month" id="to_month-field" class="form-control">
                                </div>

                                <div class="col-md-2 d-flex align-items-end">
                                    <button class="btn btn-info w-100" id="btnPreviewMonths">
                                        Preview
                                    </button>
                                </div>

                            </div>

                            <div id="previewWrap" style="display:none;">

                                <div class="table-responsive mb-3">

                                    <table class="table table-bordered align-middle">

                                        <thead class="table-light">
                                            <tr>
                                                <th>Month</th>
                                                <th width="20%">Rate</th>
                                            </tr>
                                        </thead>

                                        <tbody id="previewTableBody"></tbody>

                                    </table>

                                </div>

                                <div id="skippedMsg" class="text-muted mb-2" style="display:none;"></div>

                                <div class="row mb-3">

                                    <div class="col-md-3">
                                        <label class="form-label">Total Amount</label>
                                        <input type="text" id="total_amount-display" class="form-control fw-bold" readonly>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label">Payment Mode</label>
                                        <select id="payment_mode-field" class="form-select">
                                            <option value="Cash">Cash</option>
                                            <option value="Card">Card</option>
                                            <option value="UPI">UPI</option>
                                            <option value="Bank">Bank</option>
                                            <option value="Cheque">Cheque</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Remarks</label>
                                        <input type="text" id="remarks-field" class="form-control">
                                    </div>

                                </div>

                                <button class="btn btn-success" id="btnCollectFee">
                                    <i class="ri-money-rupee-circle-line"></i>
                                    Collect Payment
                                </button>

                            </div>

                            <div id="lastInvoiceWrap" class="border rounded p-3 bg-light-subtle mt-3" style="display:none;">

                                <strong>Last Invoice:</strong>
                                <span id="lastInvoiceNo"></span>

                                <button class="btn btn-sm btn-primary ms-3" id="btnPrintInvoice">
                                    <i class="ri-printer-line"></i>
                                    Print Invoice
                                </button>

                                <button class="btn btn-sm btn-success ms-2" id="btnSendWhatsapp">
                                    <i class="ri-whatsapp-line"></i>
                                    Send WhatsApp
                                </button>

                            </div>

                        </div>

                        <!-- HISTORY -->

                        <div class="tab-pane fade" id="historyTab">

                            <div class="table-responsive">

                                <table class="table table-bordered align-middle">

                                    <thead class="table-light">
                                        <tr>
                                            <th>Month</th>
                                            <th>Rate</th>
                                            <th>Type</th>
                                            <th>Invoice No</th>
                                            <th>Remarks</th>
                                            <th>Recorded On</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>

                                    <tbody id="historyTableBody"></tbody>

                                </table>

                            </div>

                        </div>

                        <!-- ADMIN ADJUST -->

                        <div class="tab-pane fade" id="adjustTab">

                            <div class="alert alert-warning">
                                This directly sets the member's last paid month as a single record, without creating any invoice or financial transaction. Use only to correct historical records or to establish a starting baseline. The target month must be after the member's current last paid month.
                            </div>

                            <div class="row mb-3">

                                <div class="col-md-3">
                                    <label class="form-label">Set Last Paid Month To</label>
                                    <input type="month" id="target_month-field" class="form-control">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Remarks</label>
                                    <input type="text" id="adjust_remarks-field" class="form-control" placeholder="Reason for manual adjustment">
                                </div>

                                <div class="col-md-3 d-flex align-items-end">
                                    <button class="btn btn-warning w-100" id="btnAdjust">
                                        Adjust
                                    </button>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script>
    window.currentUserRole = "{{ Auth::user()->role }}";
</script>

<script src="{{ URL::asset('build/js/pages/membership-fee.init.js') }}"></script>

@endsection
