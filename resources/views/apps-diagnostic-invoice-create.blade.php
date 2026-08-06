@extends('layouts.master')

@section('title')
Create Diagnostic Invoice
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet">

<link href="{{ URL::asset('build/libs/flatpickr/flatpickr.min.css') }}" rel="stylesheet" type="text/css" />

<link ...>

<style>
    .locked-category {
        pointer-events: none;
        background-color: #f8f9fa !important;
    }
    .locked-test {
        pointer-events: none;
        background-color: #f8f9fa !important;
    }
    .locked-discount {
        pointer-events: none;
        background-color: #f8f9fa !important;
    }
    .detailRow {
        background: #f8f9fa;
    }

        .detailRow label {
            font-size: 11px;
            font-weight: 600;
        }

    .mainRow td {
        vertical-align: middle;
    }





    #patientList {
        font-size: 11px;
    }

        #patientList th,
        #patientList td {
            padding: 4px 5px !important;
            line-height: 1;
        }



    .patientRow {
        cursor: pointer;
        transition: all .2s;
    }

        .patientRow:hover {
            background-color: #cfe2ff !important;
            transform: scale(1.01);
        }
</style>

@endsection

</style>
<script>
    window.editMode = false;
</script>

@section('content')

<div class="container-fluid">

    <form id="invoiceForm">

        @csrf

        <div class="card">

            <div class="card-header">

                <h4 class="card-title">

                    Create Diagnostic Invoice

                </h4>

            </div>

            <div class="card-body">

                <div class="row">

                    <div class="col-md-6">
                        <label>Mobile Number</label>

                        <div class="input-group">

                            <input type="text"
                                   id="search_mobile_no"
                                   class="form-control"
                                   maxlength="10"
                                   placeholder="Enter 10 digit mobile number">

                            <button type="button"
                                    id="searchPatient"
                                    class="btn btn-primary">

                                Search

                            </button>

                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span id="discountMessage"
                              class="text-primary-emphasis ">
                        </span>
                    </div>
                    <div class="row mt-4"
                         id="patientSearchResult"
                         style="display:none;">

                        <div class="col-md-12">

                            <table class="table table-bordered"
                                   id="patientList">

                                <thead class="table-primary">
                                    <tr>
                                        <th>Select Patient</th>
                                        <th>Patient ID</th>
                                        <th>Name</th>
                                        <th>Age</th>
                                        <th>Gender</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody id="patientListBody">
                                </tbody>
                   
                            </table>
                            <div
                                id="addNewPatientContainer"
                                class="mt-2"
                                style="display:none;">

                                <button
                                    type="button"
                                    id="addNewPatientForMobile"
                                    class="btn btn-success">

                                    + Add New Patient For This Mobile Number

                                </button>

                            </div>


                        </div>

                    </div>
                </div>

                <hr>

                <div id="patientSection" style="display:none;">



                    <div class="row mt-3">

                        <div class="col-md-3">
                            <label>Patient ID</label>

                            <input type="text"
                                   id="patient_id"
                                   name="patient_id"
                                   class="form-control"
                                   readonly>
                        </div>

                        <div class="col-md-4">
                            <div class="d-flex justify-content-between">

                                <label>Name</label>



                            </div>

                            <input type="text"
                                   id="patient_name"
                                   name="patient_name"
                                   class="form-control">
                        </div>

                        <div class="col-md-2">
                            <label>Age</label>

                            <input type="text"
                                   id="patient_age"
                                   name="patient_age"
                                   class="form-control">
                        </div>

                        <div class="col-md-3">
                            <label>Gender</label>

                            <select id="patient_gender"
                                    name="patient_gender"
                                    class="form-select">
                                <option value="">
                                    Select Gender
                                </option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>

                            </select>
                        </div>

                    </div>

                    <input type="hidden"
                           id="patient_mobile_no"
                           name="patient_mobile_no">

                    <div class="row mt-3">

                        <div class="col-md-3">

                            <button type="button"
                                    id="generatePatientIdBtn"
                                    class="btn btn-success"
                                    style="display:none;">

                                Generate Patient ID

                            </button>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!-- TEST DETAILS -->

        <!-- TEST DETAILS -->

        <div id="testSection" style="display:none;">

            <div class="card">

                <div class="card-body">

                    <!-- Test Date / Appointment / Referring Doctor -->

                    <div class="row mb-3">

                        <div class="col-md-4">

                            <label class="form-label">
                                Test Date
                            </label>

                            <input
                                type="text"
                                id="test_date"
                                name="test_date"
                                class="form-control flatpickr"
                                value="{{ date('Y-m-d') }}"
                                required>

                        </div>

                        <div class="col-md-4">

                            <label class="form-label">
                                Doctor Visit Reference
                            </label>

                            <select
                                id="doctor_visit_id"
                                name="doctor_visit_id"
                                class="form-select">

                                <option value="">
                                    Direct Referral / Walk In
                                </option>

                            </select>

                        </div>

                        <div class="col-md-4">

                            <label class="form-label">
                                Referring Doctor
                            </label>

                            <input
                                type="text"
                                id="referred_doctor"
                                name="referred_doctor"
                                class="form-control"
                                value="Dr. "
                                placeholder="Enter doctor name">

                        </div>

                    </div>
                    <div class="d-flex justify-content-end mb-2">

                        <button
                            type="button"
                            id="addRow"
                            class="btn btn-primary btn-sm">

                            <i class="ri-add-line"></i>
                            Add Test

                        </button>

                    </div>
                    <!-- Test Grid -->

                    <div class="table-responsive">

                        <table
                            class="table table-bordered"
                            id="testTable">

                            <thead class="table-light">

                                <tr>

                                    <th width="15%">
                                        Category
                                    </th>

                                    <th width="24%">
                                        Test
                                    </th>

                                    <th width="8%">
                                        Rate
                                    </th>

                                    <th width="8%">
                                        Std Disc %
                                    </th>

                                    <th width="8%">
                                        Payable Amt.
                                    </th>

                                    <th width="12%">
                                        Doctor / Staff
                                    </th>

                                    <th width="12%">
                                        Doctor / Staff Amt.
                                    </th>

                                    <th width="12%">
                                        Action
                                    </th>

                                </tr>

                            </thead>

                            <tbody>
                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

        <!-- PAYMENT -->

        <div id="invoiceSection" style="display:none;">
            <div class="card">
                <div class="card-header">

                    <h4 class="card-title">

                        Payment Details

                    </h4>

                </div>

                <div class="card-body">

                    <div class="row">

                        <div class="col-md-3 mb-3">

                            <label>

                                Gross Amount

                            </label>

                            <input type="number"
                                   id="total_amount"
                                   name="total_amount"
                                   class="form-control"
                                   readonly>

                        </div>

                        <div class="col-md-3 mb-3">

                            <label>

                                Total Discount

                            </label>

                            <input type="number"
                                   id="discount"
                                   name="discount"
                                   class="form-control"
                                   value="0"
                                   readonly>

                        </div>

                        

                        <div class="col-md-3 mb-3">

                            <label>

                                Due Amount

                            </label>

                            <input type="number"
                                   id="due_amount"
                                   name="due_amount"
                                   class="form-control"
                                   readonly>

                        </div>
                        <div class="col-md-3 mb-3">

                            <label>

                                Paid Amount

                            </label>

                            <input type="number"
                                   id="paid_amount"
                                   name="paid_amount"
                                   class="form-control fw-bold fs-4"
                                   placeholder="0">

                        </div>
                        <div class="col-md-3 mb-3">

                            <label>

                                Payment Mode

                            </label>

                            <select id="payment_mode"
                                    name="payment_mode"
                                    class="form-select">

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

                        

                        <div class="col-md-6 mb-3">

                            <label>

                                Remarks

                            </label>

                            <textarea id="remarks"
                                      name="remarks"
                                      class="form-control"
                                      rows="1"></textarea>

                        </div>

                    </div>

                </div>
            </div>

        </div>

        <div class="text-end mb-5" id="formActionButtons">

            <a href="{{ url('/diagnostic-invoice') }}"
               class="btn btn-secondary">

                Back

            </a>

            <button type="submit" id="saveInvoiceBtn"
                    class="btn btn-success">

                Save Invoice

            </button>

        </div>

    </form>

</div>

<!-- CONFIRM REVIEW OFFCANVAS -->

<div class="offcanvas offcanvas-end"
     tabindex="-1"
     id="confirmDiagnosticOffcanvas"
     style="width: 500px;">

    <div class="offcanvas-header border-bottom">

        <h5 class="offcanvas-title">Confirm Invoice Details</h5>

        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>

    </div>

    <div class="offcanvas-body">

        <table class="table table-sm table-bordered mb-3">

            <tbody>

                <tr><th width="40%">Patient ID</th><td id="review-patient_id"></td></tr>
                <tr><th>Patient Name</th><td id="review-patient_name"></td></tr>
                <tr><th>Age / Gender</th><td id="review-patient_age_gender"></td></tr>
                <tr><th>Mobile No</th><td id="review-patient_mobile_no"></td></tr>
                <tr><th>Test Date</th><td id="review-test_date"></td></tr>
                <tr><th>Doctor Visit Reference</th><td id="review-doctor_visit_id"></td></tr>
                <tr><th>Referring Doctor</th><td id="review-referred_doctor"></td></tr>

            </tbody>

        </table>

        <div class="table-responsive mb-3">

            <table class="table table-sm table-bordered">

                <thead class="table-light">
                    <tr>
                        <th>Category</th>
                        <th>Test</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>

                <tbody id="reviewTestsBody"></tbody>

            </table>

        </div>

        <table class="table table-sm table-bordered mb-4">

            <tbody>

                <tr><th width="40%">Gross Amount</th><td id="review-total_amount"></td></tr>
                <tr><th>Discount</th><td id="review-discount"></td></tr>
                <tr><th>Paid Amount</th><td id="review-paid_amount"></td></tr>
                <tr><th>Due Amount</th><td id="review-due_amount"></td></tr>
                <tr><th>Payment Mode</th><td id="review-payment_mode"></td></tr>
                <tr><th>Remarks</th><td id="review-remarks"></td></tr>

            </tbody>

        </table>

        <div class="d-flex gap-2">

            <button type="button" class="btn btn-light w-50" id="btnBackToEditDiagnostic">
                Back &amp; Edit
            </button>

            <button type="button" class="btn btn-success w-50" id="btnConfirmSaveDiagnostic">
                <i class="ri-checkbox-circle-line"></i>
                Confirm &amp; Save
            </button>

        </div>

    </div>

</div>



@endsection

@section('script')

<script>

window.categoryOptions = `
<option value="">Select Category</option>

@foreach($categories as $category)
<option
    value="{{ $category->item_code }}"
    data-category-name="{{ strtoupper($category->item_name) }}">
    {{ $category->item_name }}
</option>
@endforeach
`;

</script>
<script>

window.discountApproverOptions = `

<option value="">
Select
</option>

@foreach($discountApprovers as $user)

<option value="{{ $user->id }}">
{{ $user->name }}
</option>

@endforeach

`;

</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/apps-diagnostic-invoice.init.js') }}"></script>

@endsection
