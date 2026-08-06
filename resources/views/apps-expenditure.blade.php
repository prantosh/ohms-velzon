@extends('layouts.master')

@section('title')
    Expenditure Entry
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
        Billing
    @endslot

    @slot('title')
        Expenditure Entry
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0" id="formTitle">Record Expenditure</h5>
            </div>

            <div class="card-body">

                <form id="expenditureForm">

                    @csrf

                    <input type="hidden" id="edit-id">

                    <div class="row">

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Expenditure Category <span class="text-danger">*</span></label>
                            <select id="category_id-field" class="form-select" required>
                                <option value="">-- Select --</option>
                                @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->description }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Paid To (Agency) <span class="text-danger">*</span></label>
                            <select id="agency_id-field" class="form-select" required>
                                <option value="">-- Select --</option>
                                @foreach($agencies as $agency)
                                <option value="{{ $agency->id }}">{{ $agency->description }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Transaction Date <span class="text-danger">*</span></label>
                            <input type="text" id="transaction_date-field" class="form-control flatpickr" required>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Amount (&#8377;) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" id="amount-field" class="form-control" required>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Payment Mode <span class="text-danger">*</span></label>
                            <select id="payment_mode-field" class="form-select" required>
                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="UPI">UPI</option>
                                <option value="Bank">Bank</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Bill Number</label>
                            <input type="text" id="bill_number-field" class="form-control" maxlength="50" placeholder="Vendor's bill / invoice number">
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Expenditure By <span class="text-danger">*</span></label>
                            <select id="expenditure_by-field" class="form-select" required>
                                <option value="">-- Select --</option>
                                @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 mb-3 cheque-field-wrap" style="display:none;">
                            <label class="form-label">Cheque Number</label>
                            <input type="text" id="cheque_number-field" class="form-control" maxlength="50">
                        </div>

                        <div class="col-md-3 mb-3 cheque-field-wrap" style="display:none;">
                            <label class="form-label">Bank Name</label>
                            <input type="text" id="bank_name-field" class="form-control" maxlength="150">
                        </div>

                        <div class="col-md-3 mb-3 cheque-field-wrap" style="display:none;">
                            <label class="form-label">Cheque Date</label>
                            <input type="text" id="cheque_date-field" class="form-control flatpickr">
                        </div>

                        <div class="col-md-9 mb-3">
                            <label class="form-label">Remarks</label>
                            <input type="text" id="remarks-field" class="form-control">
                        </div>

                    </div>

                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="ri-save-line"></i>
                        Save Expenditure
                    </button>

                    <button type="button" class="btn btn-light" id="btnCancelEdit" style="display:none;">
                        Cancel Edit
                    </button>

                </form>

            </div>

        </div>

        <div class="card">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3">

                <div class="d-flex align-items-center gap-3 flex-wrap">

                    <div class="d-flex align-items-center">
                        <label class="me-2 mb-0 fw-semibold">Show</label>
                        <select id="perPage" class="form-select form-select-sm w-auto">
                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                        <span class="ms-2">records</span>
                    </div>

                    <input type="text" id="searchInput" class="form-control form-control-sm w-auto" placeholder="Search transaction / expenditure by...">

                </div>

            </div>

            <div class="card-body pt-0">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th width="60">Sl</th>
                                <th width="150">Voucher No</th>
                                <th width="120">Bill No</th>
                                <th width="100">Date</th>
                                <th>Category</th>
                                <th>Paid To</th>
                                <th width="110">Amount (&#8377;)</th>
                                <th width="100">Mode</th>
                                <th>Expenditure By</th>
                                <th width="140">Action</th>
                            </tr>
                        </thead>

                        <tbody id="expenditureTableBody"></tbody>

                    </table>

                    <div class="d-flex justify-content-between align-items-center mt-3">

                        <div id="pagination-info"></div>

                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-sm btn-primary" id="prevPage">Previous</button>
                            <span id="pageNumber">Page 1</span>
                            <button class="btn btn-sm btn-primary" id="nextPage">Next</button>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- CONFIRM REVIEW OFFCANVAS -->

<div class="offcanvas offcanvas-end" tabindex="-1" id="confirmExpenditureOffcanvas" style="width: 450px;">

    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title">Confirm Expenditure Entry</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body">

        <table class="table table-sm table-bordered mb-4">

            <tbody>
                <tr><th width="45%">Category</th><td id="review-category"></td></tr>
                <tr><th>Paid To</th><td id="review-agency"></td></tr>
                <tr><th>Transaction Date</th><td id="review-transaction_date"></td></tr>
                <tr><th>Amount</th><td id="review-amount"></td></tr>
                <tr><th>Payment Mode</th><td id="review-payment_mode"></td></tr>
                <tr><th>Cheque No</th><td id="review-cheque_number"></td></tr>
                <tr><th>Bank Name</th><td id="review-bank_name"></td></tr>
                <tr><th>Cheque Date</th><td id="review-cheque_date"></td></tr>
                <tr><th>Bill No</th><td id="review-bill_number"></td></tr>
                <tr><th>Expenditure By</th><td id="review-expenditure_by"></td></tr>
                <tr><th>Remarks</th><td id="review-remarks"></td></tr>
            </tbody>

        </table>

        <div class="d-flex gap-2">

            <button type="button" class="btn btn-light w-50" id="btnBackToEditExpenditure">
                Back &amp; Edit
            </button>

            <button type="button" class="btn btn-success w-50" id="btnConfirmSaveExpenditure">
                <i class="ri-checkbox-circle-line"></i>
                Confirm &amp; Save
            </button>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/expenditure.init.js') }}"></script>

@endsection
