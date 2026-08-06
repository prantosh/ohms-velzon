@extends('layouts.master')

@section('title')
    Equipment Rental
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
        Equipment Rental
    @endslot

    @slot('title')
        Oxygen Cylinder / Concentrator Rental
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">

    <!-- LEFT SIDE -->

    <div class="d-flex align-items-center gap-3 flex-wrap">

        <div class="d-flex align-items-center">

            <label class="me-2 mb-0 fw-semibold">
                Show
            </label>

            <select id="perPage"
                    class="form-select form-select-sm w-auto">

                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>

            </select>

            <span class="ms-2">
                records
            </span>

        </div>

        <div class="d-flex align-items-center">

            <label class="me-2 mb-0 fw-semibold">
                Status
            </label>

            <select id="statusFilter"
                    class="form-select form-select-sm w-auto">

                <option value="">All</option>
                <option value="Issued">Issued</option>
                <option value="Returned">Returned</option>
                <option value="Cancelled">Cancelled</option>

            </select>

        </div>

    </div>

    <!-- RIGHT SIDE -->

    <div class="d-flex align-items-center gap-2 flex-wrap">

        <button type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#issueModal"
                id="issueBtn">

            <i class="ri-add-line align-bottom me-1"></i>
            Issue Equipment

        </button>

    </div>

</div>



            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle"
                           id="rentalTable">

                        <thead class="table-light">

                            <tr>

                            <th width="50">
                                <input type="checkbox">
                            </th>

                            <th>Invoice No</th>
                            <th>Type</th>
                            <th>Patient</th>
                            <th width="80">Qty</th>
                            <th width="110">Issue Date</th>
                            <th width="110">Return Date</th>
                            <th width="110">Advance Amount</th>
                            <th width="110">Rented Amount</th>
                            <th width="100">Status</th>
                            <th width="160">Advance Invoice</th>
                            <th width="160">Final Invoice</th>
                            <th width="160">Action</th>

                            </tr>

                        </thead>

                        <tbody>

                        </tbody>

                    </table>

                    <div class="d-flex justify-content-between align-items-center mt-3">

    <div id="pagination-info"></div>

    <div class="d-flex align-items-center gap-2">

        <button class="btn btn-sm btn-primary"
                id="prevPage">
            Previous
        </button>

        <span id="pageNumber">
            Page 1
        </span>

        <button class="btn btn-sm btn-primary"
                id="nextPage">
            Next
        </button>

    </div>

</div>



                </div>

            </div>

        </div>

    </div>

</div>

<!-- ISSUE MODAL -->

<div class="modal fade"
     id="issueModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header bg-light p-3">

                <h5 class="modal-title">
                    Issue Equipment
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <form id="issueForm"
                enctype="multipart/form-data">

                @csrf

                <div class="modal-body">

                    <div class="row">

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Rental Type
                                <span class="text-danger">*</span>
                            </label>

                            <select id="rental_type-field"
                                    class="form-select"
                                    required>

                                <option value="">Select Type</option>
                                <option value="OXYGEN_RENT">Oxygen Cylinder</option>
                                <option value="CONCENTRATOR_RENT">Concentrator</option>

                            </select>

                        </div>

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Category
                                <span class="text-danger">*</span>
                            </label>

                            <select id="category_id-field"
                                    class="form-select"
                                    required>

                                <option value="">Select Category</option>

                            </select>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Quantity
                                <span class="text-danger">*</span>
                            </label>

                            <input type="number"
                                   id="quantity-field"
                                   class="form-control"
                                   min="1"
                                   value="1"
                                   required>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Advance Amount
                                <span class="text-danger">*</span>
                            </label>

                            <input type="number"
                                   id="advance_amount-field"
                                   class="form-control"
                                   min="0"
                                   step="0.01"
                                   required>

                        </div>

                        <div class="col-md-12 mb-3"
                             id="lowAdvanceReasonWrap"
                             style="display:none;">

                            <label class="form-label">
                                Reason for Lower Advance
                                <span class="text-danger">*</span>
                            </label>

                            <input type="text"
                                   id="low_advance_reason-field"
                                   class="form-control">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Payment Mode
                                <span class="text-danger">*</span>
                            </label>

                            <select id="payment_mode-field"
                                    class="form-select"
                                    required>

                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="UPI">UPI</option>
                                <option value="Bank">Bank</option>
                                <option value="Cheque">Cheque</option>

                            </select>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Discount Amount
                            </label>

                            <input type="number"
                                   id="discount_amount-field"
                                   class="form-control"
                                   min="0"
                                   step="0.01"
                                   value="0">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Approved By
                                <span class="text-danger" id="approved-by-required" style="display:none;">*</span>
                            </label>

                            <select id="discount_approved_by-field"
                                    class="form-select">

                                <option value="">Select</option>

                            </select>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Discount Remarks
                            </label>

                            <input type="text"
                                   id="discount_remarks-field"
                                   class="form-control">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Patient Mobile No
                                <span class="text-danger">*</span>
                            </label>

                            <div class="input-group">

                                <input type="text"
                                       id="search_mobile_no"
                                       class="form-control"
                                       maxlength="10"
                                       placeholder="Enter 10 digit mobile number">

                                <button type="button"
                                        id="searchPatientBtn"
                                        class="btn btn-primary">
                                    Search Patient
                                </button>

                            </div>

                        </div>

                        <div class="col-md-12 mb-3"
                             id="patientResultsWrap"
                             style="display:none;">

                            <div class="table-responsive">

                                <table class="table table-sm table-bordered mb-0">

                                    <thead class="table-light">
                                        <tr>
                                            <th>Patient ID</th>
                                            <th>Name</th>
                                            <th>Age</th>
                                            <th>Gender</th>
                                        </tr>
                                    </thead>

                                    <tbody id="patientResultsBody"></tbody>

                                </table>

                            </div>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Patient ID
                            </label>

                            <input type="text"
                                   id="patient_id-field"
                                   class="form-control"
                                   readonly>

                        </div>

                        <div class="col-md-8 mb-3">

                            <label class="form-label">
                                Patient Name
                                <span class="text-danger">*</span>
                            </label>

                            <input type="text"
                                   id="patient_name-field"
                                   class="form-control"
                                   readonly>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Age
                            </label>

                            <input type="text"
                                   id="patient_age-field"
                                   class="form-control"
                                   readonly>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Gender
                            </label>

                            <select id="patient_gender-field"
                                    class="form-select"
                                    disabled>

                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>

                            </select>

                        </div>

                        <div class="col-md-4 mb-3 d-flex align-items-end">

                            <button type="button"
                                    id="generatePatientIdBtn"
                                    class="btn btn-success w-100"
                                    style="display:none;">
                                Generate Patient ID
                            </button>

                        </div>

                        <div class="col-md-12 mb-3">

                            <label class="form-label">
                                Remarks
                            </label>

                            <textarea id="remarks-field"
                                      class="form-control"
                                      rows="2"></textarea>

                        </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-light"
                            data-bs-dismiss="modal">
                        Close
                    </button>

                    <button type="submit"
                            class="btn btn-success"
                            id="issue-btn">
                        Issue Equipment
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<!-- RETURN MODAL -->

<div class="modal fade"
     id="returnModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header bg-light p-3">

                <h5 class="modal-title">
                    Return Equipment
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <form id="returnForm">

                <input type="hidden" id="return-invoice-id">

                <div class="modal-body">

                    <div class="row">

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Return Date
                            <span class="text-danger">*</span>
                        </label>

                        <input type="text"
                               id="return_date-field"
                               class="form-control flatpickr"
                               required>

                    </div>

                    <div class="col-md-6 mb-3"
                         id="returnUnitsWrap"
                         style="display:none;">

                        <label class="form-label">
                            Units (kg) Consumed
                            <span class="text-danger">*</span>
                        </label>

                        <input type="number"
                               id="return_units-field"
                               class="form-control"
                               min="0"
                               step="1">

                    </div>

                    <div class="col-12 mb-3" id="settlementPreview" style="display:none;">

                        <div class="alert alert-info mb-0">

                            <div class="row text-center gy-2">

                                <div class="col-6 col-md-3">
                                    <small class="d-block text-muted">Advance Paid</small>
                                    <span class="fw-semibold" id="preview-advance">-</span>
                                </div>

                                <div class="col-6 col-md-3">
                                    <small class="d-block text-muted">Days</small>
                                    <span class="fw-semibold" id="preview-days">-</span>
                                </div>

                                <div class="col-6 col-md-3">
                                    <small class="d-block text-muted">Rent Amount</small>
                                    <span class="fw-semibold" id="preview-rent">-</span>
                                </div>

                                <div class="col-6 col-md-3">
                                    <small class="d-block text-muted" id="preview-balance-label">Balance</small>
                                    <span class="fw-semibold" id="preview-balance">-</span>
                                </div>

                            </div>

                        </div>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Payment Mode
                            <span class="text-danger">*</span>
                        </label>

                        <select id="return_payment_mode-field"
                                class="form-select"
                                required>

                            <option value="Cash">Cash</option>
                            <option value="Card">Card</option>
                            <option value="UPI">UPI</option>
                            <option value="Bank">Bank</option>
                            <option value="Cheque">Cheque</option>

                        </select>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Discount Amount
                        </label>

                        <input type="number"
                               id="return_discount_amount-field"
                               class="form-control"
                               min="0"
                               step="0.01"
                               value="0">

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Approved By
                            <span class="text-danger" id="return-approved-by-required" style="display:none;">*</span>
                        </label>

                        <select id="return_discount_approved_by-field"
                                class="form-select">

                            <option value="">Select</option>

                        </select>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Discount Remarks
                        </label>

                        <input type="text"
                               id="return_discount_remarks-field"
                               class="form-control">

                    </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-light"
                            data-bs-dismiss="modal">
                        Close
                    </button>

                    <button type="submit"
                            class="btn btn-success">
                        Settle Return
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/equipment-rental.init.js') }}"></script>

@endsection
