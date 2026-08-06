@extends('layouts.master')

@section('title')
    Ambulance Rental
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
        Ambulance Rental
    @endslot

    @slot('title')
        Ambulance Rental Invoice
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">

                <div class="d-flex align-items-center gap-3 flex-wrap">

                    <div class="d-flex align-items-center">

                        <label class="me-2 mb-0 fw-semibold">Show</label>

                        <select id="perPage" class="form-select form-select-sm w-auto">

                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>

                        </select>

                        <span class="ms-2">records</span>

                    </div>

                    <div class="d-flex align-items-center">

                        <label class="me-2 mb-0 fw-semibold">Status</label>

                        <select id="statusFilter" class="form-select form-select-sm w-auto">

                            <option value="">All</option>
                            <option value="Active">Active</option>
                            <option value="Cancelled">Cancelled</option>

                        </select>

                    </div>

                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">

                    <button type="button"
                            class="btn btn-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#bookingModal"
                            id="bookBtn">

                        <i class="ri-add-line align-bottom me-1"></i>
                        New Ambulance Booking

                    </button>

                </div>

            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle" id="rentalTable">

                        <thead class="table-light">

                            <tr>
                                <th>Invoice No</th>
                                <th>Patient</th>
                                <th>Destination</th>
                                <th width="90">Type</th>
                                <th width="110">Booking Date</th>
                                <th width="110">Actual Amount</th>
                                <th width="110">Received</th>
                                <th width="100">Due</th>
                                <th width="100">Status</th>
                                <th width="160">Invoice</th>
                                <th width="80">Action</th>
                            </tr>

                        </thead>

                        <tbody></tbody>

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

<!-- BOOKING MODAL -->

<div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header bg-light p-3">

                <h5 class="modal-title">New Ambulance Booking</h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

            </div>

            <form id="bookingForm">

                @csrf

                <div class="modal-body">

                    <div class="row">

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

                                <button type="button" id="searchPatientBtn" class="btn btn-primary">
                                    Search Patient
                                </button>

                            </div>

                        </div>

                        <div class="col-md-12 mb-3" id="patientResultsWrap" style="display:none;">

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

                            <label class="form-label">Patient ID</label>

                            <input type="text" id="patient_id-field" class="form-control" readonly>

                        </div>

                        <div class="col-md-8 mb-3">

                            <label class="form-label">
                                Patient Name
                                <span class="text-danger">*</span>
                            </label>

                            <input type="text" id="patient_name-field" class="form-control" readonly>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">Age</label>

                            <input type="text" id="patient_age-field" class="form-control" readonly>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">Gender</label>

                            <select id="patient_gender-field" class="form-select" disabled>

                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>

                            </select>

                        </div>

                        <div class="col-md-4 mb-3 d-flex align-items-end">

                            <button type="button" id="generatePatientIdBtn" class="btn btn-success w-100" style="display:none;">
                                Generate Patient ID
                            </button>

                        </div>

                        <div class="col-md-12 mb-3">

                            <label class="form-label">Address</label>

                            <textarea id="address-field" class="form-control" rows="2"></textarea>

                        </div>

                        <hr class="mt-2 mb-3">

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Destination
                                <span class="text-danger">*</span>
                            </label>

                            <select id="destination_id-field" class="form-select" required>

                                <option value="">Select Destination</option>

                            </select>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Booking Type
                                <span class="text-danger">*</span>
                            </label>

                            <select id="booking_type-field" class="form-select" required>

                                <option value="AC">AC</option>
                                <option value="NONAC">Non-AC</option>

                            </select>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">From Destination</label>

                            <input type="text" id="from_destination-field" class="form-control">

                        </div>

                        <div class="col-md-3 mb-3">

                            <label class="form-label">
                                Date of Booking
                                <span class="text-danger">*</span>
                            </label>

                            <input type="text" id="booking_date-field" class="form-control flatpickr" required>

                        </div>

                        <div class="col-md-3 mb-3">

                            <label class="form-label">
                                Pickup Time
                                <span class="text-danger">*</span>
                            </label>

                            <input type="datetime-local" id="pickup_time-field" class="form-control" required>

                        </div>

                        <div class="col-md-3 mb-3">

                            <label class="form-label">Release Time</label>

                            <input type="datetime-local" id="release_time-field" class="form-control">

                        </div>

                        <div class="col-md-3 mb-3">

                            <label class="form-label">Waiting Charge</label>

                            <input type="number" id="waiting_charge-field" class="form-control" min="0" step="0.01" value="0">

                        </div>

                        <div class="col-md-3 mb-3">

                            <label class="form-label">Odometer KM (Pickup)</label>

                            <input type="number" id="odometer_pickup_km-field" class="form-control" min="0" step="0.01">

                        </div>

                        <div class="col-md-3 mb-3">

                            <label class="form-label">Odometer KM (Release)</label>

                            <input type="number" id="odometer_release_km-field" class="form-control" min="0" step="0.01">

                        </div>

                        <div class="col-md-3 mb-3">

                            <label class="form-label">Actual Amount</label>

                            <input type="text" id="actual_amount-display" class="form-control" readonly>

                        </div>

                        <div class="col-md-3 mb-3">

                            <label class="form-label">Discount Amount</label>

                            <input type="number" id="discount_amount-field" class="form-control" min="0" step="0.01" value="0">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Discount Authority
                                <span class="text-danger" id="approved-by-required" style="display:none;">*</span>
                            </label>

                            <select id="discount_approved_by-field" class="form-select">

                                <option value="">Select</option>

                            </select>

                        </div>

                        <div class="col-md-5 mb-3">

                            <label class="form-label">Discount Remarks</label>

                            <input type="text" id="discount_remarks-field" class="form-control">

                        </div>

                        <div class="col-md-3 mb-3">

                            <label class="form-label">
                                Received Amount
                                <span class="text-danger">*</span>
                            </label>

                            <input type="number" id="received_amount-field" class="form-control" min="0" step="0.01" required>

                        </div>

                        <div class="col-md-3 mb-3">

                            <label class="form-label">
                                Payment Mode
                                <span class="text-danger">*</span>
                            </label>

                            <select id="payment_mode-field" class="form-select" required>

                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="UPI">UPI</option>
                                <option value="Bank">Bank</option>
                                <option value="Cheque">Cheque</option>

                            </select>

                        </div>

                        <div class="col-md-3 mb-3">

                            <label class="form-label">Received By</label>

                            <select id="received_by-field" class="form-select">

                                <option value="">Select</option>

                            </select>

                        </div>

                        <div class="col-md-3 mb-3">

                            <label class="form-label">Due Amount</label>

                            <input type="text" id="due_amount-display" class="form-control" readonly>

                        </div>

                        <div class="col-md-12 mb-3">

                            <label class="form-label">Remarks</label>

                            <textarea id="remarks-field" class="form-control" rows="2"></textarea>

                        </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>

                    <button type="submit" class="btn btn-success" id="book-btn">Create Invoice</button>

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

<script src="{{ URL::asset('build/js/pages/ambulance-rental.init.js') }}"></script>

@endsection
