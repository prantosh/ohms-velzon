@extends('layouts.master')

@section('title')
Doctor Visit Invoice
@endsection

@section('css')
<style>
    #invoiceTable {
        font-size: 12px;
    }

    #invoiceTable th,
    #invoiceTable td {
        padding: 6px 8px !important;
        white-space: nowrap;
        vertical-align: middle;
    }

    #invoiceTable .btn {
        padding: 2px 5px;
        font-size: 11px;
    }

    #invoiceTable .badge {
        font-size: 11px;
    }

    .dataTables_wrapper {
        font-size: 11px;
    }
</style>
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet">

<link rel="stylesheet"
      href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<link href="{{ URL::asset('build/libs/flatpickr/flatpickr.min.css') }}" rel="stylesheet" type="text/css" />

@endsection

@section('content')

<div class="container-fluid">

    <div class="card">

        <div class="card-header d-flex justify-content-between align-items-center">

            <h4 class="card-title mb-0">
                Doctor Visit Invoice
            </h4>

            

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-sm align-middle"
                       id="invoiceTable">

                    <thead class="table-light">

                       <tr>

                        <th>Appointment No</th>
                        <th>Patient ID</th>
                        <th>Doctor Name</th>
                        <th>Patient Name</th>
                        <th>Serial No.</th>
                        <th>Invoice No.</th>
                        <th>Appointment Date</th>
                        <th>Total Fees</th>
                        <th>Paid</th>
                        <th>Invoice Status</th>
                        <th>Action</th>

                    </tr>

                    </thead>

                    <tbody></tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<!-- MODAL -->

<div class="modal fade"
     id="invoiceModal"
     tabindex="-1">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    Add Invoice
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <form id="invoiceForm">

                @csrf

                <input type="hidden"
                       id="edit-id">

                <div class="modal-body">

                    <div class="row">

                        <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Appointment No
                        </label>

                        <input type="text"
                               id="appointment_no"
                               class="form-control"
                               readonly>

                        <input type="hidden"
                               id="appointment_id">

                    </div>
                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Patient ID
                        </label>

                        <input type="text"
                               id="patient_id"
                               class="form-control"
                               readonly>
                        <input type="hidden"
                               id="doctor_id">

                    </div>
                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Patient Name
                        </label>

                        <input type="text"
                               id="patient_name"
                               class="form-control"
                               >

                    </div>
                        <div class="col-md-3 mb-3">
                        <label class="form-label">
                            Age <span class="text-danger">*</span>
                        </label>

                        <input type="text"
                               id="patient_age"
                               class="form-control"
                               required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">
                            Gender <span class="text-danger">*</span>
                        </label>

                        <select id="patient_gender"
                                class="form-select"
                                required>

                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>

                        </select>
                    </div>
                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Doctor Name
                        </label>

                        <input type="text"
                               id="doctor_name"
                               class="form-control"
                               readonly>

                    </div>
                        <div class="col-md-4 mb-3">
                        <label class="form-label">Visit Date</label>
                        <input type="text"
                               id="visit_date"
                               class="form-control"
                               readonly>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Time Slot</label>
                        <input type="text"
                               id="visit_time"
                               class="form-control"
                               readonly>
                    </div>
                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Invoice Date
                        </label>

                        <input type="text"
                               id="invoice_date"
                               class="form-control flatpickr">

                    </div>

                        

                        <div class="col-md-3 mb-3">

                            <label class="form-label">
                                Consultation Fee
                            </label>

                            <input type="number"
                                   id="consultation_fee"
                                   class="form-control"
                                   readonly>

                        </div>
                        <div class="col-md-4 mb-3 d-flex align-items-center">

                            <div class="form-check mt-4">

                                <input class="form-check-input"
                                       type="checkbox"
                                       id="is_card_holder">

                                <label class="form-check-label"
                                       for="is_card_holder">

                                    Card Holder

                                </label>

                            </div>

                        </div>
                        <div class="col-md-4 mb-3"
                             id="card_number_div"
                             style="display:none;">

                            <label class="form-label">
                                Card Number
                            </label>

                            <input type="text"
                                   id="card_number"
                                   class="form-control">

                        </div>

                        <div class="col-md-4 mb-3"
                             id="validate_card_div"
                             style="display:none;">

                            <label class="form-label d-block">
                                Validate Card
                            </label>

                            <button type="button"
                                    class="btn btn-info"
                                    id="validateCardBtn">

                                Validate Card

                            </button>

                            <span id="card_validation_status"
                                  class="ms-2 fw-bold"></span>

                        </div>
                        <div class="col-md-3 mb-3">

                            <label class="form-label">
                                Discount
                            </label>

                            <input type="number"
                                   id="discount"
                                   class="form-control"
                                   value="0" readonly>

                        </div>
                        <div class="col-md-3 mb-3">

                            <label class="form-label">
                                Paid Amount
                            </label>

                            <input type="number"
                                   id="paid_amount"
                                   class="form-control"
                                   value="0">

                        </div>

                        
                                               

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Payment Mode
                            </label>

                            <select class="form-select"
                                    id="payment_mode">

                                <option value="Cash">
                                    Cash
                                </option>

                                <option value="Card">
                                    Card
                                </option>

                                <option value="UPI">
                                    UPI
                                </option>

                            </select>

                        </div>
                        

                       
                        
                        <div class="col-md-12 mb-3">

                            <label class="form-label">
                                Remarks
                            </label>

                            <textarea class="form-control"
                                      id="remarks"
                                      rows="3"></textarea>

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

                        Save Invoice

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<!-- CONFIRM REVIEW OFFCANVAS -->

<div class="offcanvas offcanvas-end"
     tabindex="-1"
     id="confirmInvoiceOffcanvas"
     style="width: 450px;">

    <div class="offcanvas-header border-bottom">

        <h5 class="offcanvas-title">Confirm Invoice Details</h5>

        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>

    </div>

    <div class="offcanvas-body">

        <table class="table table-sm table-bordered mb-4">

            <tbody>

                <tr><th width="45%">Appointment No</th><td id="review-appointment_no"></td></tr>
                <tr><th>Patient ID</th><td id="review-patient_id"></td></tr>
                <tr><th>Patient Name</th><td id="review-patient_name"></td></tr>
                <tr><th>Age</th><td id="review-patient_age"></td></tr>
                <tr><th>Gender</th><td id="review-patient_gender"></td></tr>
                <tr><th>Doctor Name</th><td id="review-doctor_name"></td></tr>
                <tr><th>Visit Date</th><td id="review-visit_date"></td></tr>
                <tr><th>Time Slot</th><td id="review-visit_time"></td></tr>
                <tr><th>Invoice Date</th><td id="review-invoice_date"></td></tr>
                <tr><th>Consultation Fee</th><td id="review-consultation_fee"></td></tr>
                <tr><th>Card Holder</th><td id="review-is_card_holder"></td></tr>
                <tr><th>Card Number</th><td id="review-card_number"></td></tr>
                <tr><th>Discount</th><td id="review-discount"></td></tr>
                <tr><th>Paid Amount</th><td id="review-paid_amount"></td></tr>
                <tr><th>Payment Mode</th><td id="review-payment_mode"></td></tr>
                <tr><th>Remarks</th><td id="review-remarks"></td></tr>

            </tbody>

        </table>

        <div class="d-flex gap-2">

            <button type="button" class="btn btn-light w-50" id="btnBackToEditInvoice">
                Back &amp; Edit
            </button>

            <button type="button" class="btn btn-success w-50" id="btnConfirmSaveInvoice">
                <i class="ri-checkbox-circle-line"></i>
                Confirm &amp; Save
            </button>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/doctor-visit-invoice.init.js') }}"></script>
@endsection
