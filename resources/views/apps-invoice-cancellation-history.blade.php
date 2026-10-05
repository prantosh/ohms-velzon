@extends('layouts.master')

@section('title')
    Invoice Cancellation History
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
        Invoice Cancellation History
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Select Duration</h5>
            </div>

            <div class="card-body">

                <div class="row align-items-end">

                    <div class="col-md-3 mb-3">
                        <label class="form-label">From Date <span class="text-danger">*</span></label>
                        <input type="text" id="from_date-field" class="form-control flatpickr" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">To Date <span class="text-danger">*</span></label>
                        <input type="text" id="to_date-field" class="form-control flatpickr" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Cancelled By</label>
                        <select id="user_id-field" class="form-select">
                            <option value="ALL">All Users</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 mb-3">
                        <button type="button" class="btn btn-primary w-100" id="loadReportBtn">
                            <i class="ri-search-line"></i>
                            Load Report
                        </button>
                    </div>

                </div>

            </div>

        </div>

        <div class="card" id="listCard" style="display:none;">

            <div class="card-header">
                <h5 class="card-title mb-0">Day-Wise Cancellation Summary</h5>
            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th class="text-end">Cancelled Invoices</th>
                                <th class="text-end">Total Invoice Amount (&#8377;)</th>
                                <th class="text-end">Paid/Refunded Amount (&#8377;)</th>
                                <th width="90"></th>
                            </tr>
                        </thead>

                        <tbody id="summaryTableBody"></tbody>

                        <tfoot>
                            <tr class="table-light fw-semibold">
                                <td>Total</td>
                                <td class="text-end" id="total-cancelled_count">0</td>
                                <td class="text-end" id="total-total_amount">&#8377;0.00</td>
                                <td class="text-end" id="total-paid_amount">&#8377;0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>

                    </table>

                </div>

            </div>

        </div>

        <div id="noDataWrap" class="alert alert-warning" style="display:none;">
            No cancelled invoices found in this date range.
        </div>

    </div>

</div>

<!-- SINGLE DATE DETAIL MODAL -->

<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header bg-light p-3">
                <h5 class="modal-title" id="detailModalTitle">Cancellation Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="row mb-3">

                    <div class="col-md-4 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0" id="detail-cancelled_count">0</h5>
                            <small class="text-muted">Cancelled Invoices</small>
                        </div></div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0 text-danger" id="detail-total_amount">&#8377;0.00</h5>
                            <small class="text-muted">Total Invoice Amount</small>
                        </div></div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <div class="card h-100"><div class="card-body text-center">
                            <h5 class="mb-0 text-danger" id="detail-paid_amount">&#8377;0.00</h5>
                            <small class="text-muted">Paid/Refunded Amount</small>
                        </div></div>
                    </div>

                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice No</th>
                                <th>Type</th>
                                <th>Patient</th>
                                <th>Mobile</th>
                                <th width="100">Invoice Date</th>
                                <th class="text-end" width="110">Amount (&#8377;)</th>
                                <th class="text-end" width="110">Paid (&#8377;)</th>
                                <th width="130">Cancelled By</th>
                                <th width="140">Cancelled At</th>
                                <th width="130">Approved By</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody id="detailTableBody"></tbody>
                    </table>
                </div>

                <div id="detailNoDataWrap" class="text-muted text-center py-2" style="display:none;">
                    No cancelled invoices found for this date.
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/invoice-cancellation-history.init.js') }}"></script>

@endsection
