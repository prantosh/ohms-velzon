@extends('layouts.master')

@section('title')
    @lang('translation.doctors')
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />
<link rel="stylesheet"
      href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<link rel="stylesheet"
      href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
<link rel="stylesheet"
      href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css"
      rel="stylesheet" />

<style>

.flatpickr-calendar {

    border-radius: 14px !important;

    box-shadow:
        0 10px 25px rgba(0,0,0,0.12) !important;

    border: none !important;

    overflow: hidden;
}

.flatpickr-day.selected {

    background: #405189 !important;

    border-color: #405189 !important;
}

.flatpickr-day:hover {

    background: #e9edf7 !important;
}

.flatpickr-months {

    background: #405189;

    color: white;
}

.flatpickr-current-month input.cur-year {

    color: white !important;
}

.flatpickr-monthDropdown-months {

    color: white !important;
}

.flatpickr-weekday {

    color: #405189 !important;

    font-weight: 600;
}

</style>

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Hospital
    @endslot

    @slot('title')
        Doctor Management
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">

    <!-- LEFT SIDE -->

    <div class="d-flex align-items-center gap-3 flex-wrap">

        <!-- SHOW RECORDS -->

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

        <!-- EXPORT BUTTONS -->

        <div id="exportButtons"
             class="d-flex gap-2"></div>

    </div>

    <!-- RIGHT SIDE -->

    <div class="d-flex align-items-center gap-2 flex-wrap">

        <!-- SEARCH -->

        <div id="customSearch"></div>

        <!-- ADD BUTTON -->

        <button type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#showModal"
                id="addDoctorBtn">

            <i class="ri-add-line align-bottom me-1"></i>

            Add Doctor

        </button>

    </div>

</div>



            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle"
                           id="doctorTable">

                        <thead class="table-light">

                            <tr>
                                <th style="width:50px;">

                                    <div class="form-check">

                                        <input class="form-check-input"
                                               type="checkbox">

                                    </div>

                                </th>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Doctor Code</th>
                                <th>Doctor Name</th>
                                <th>Registration No.</th>
                                <th>Gender</th>
                                <th>Mobile</th>
                                <th>specialisation</th>
                                <th>Qualification</th>
                                <th>Joining Date</th>
                                <th>Consultation Fee</th>
                                <th>Status</th>
                                <th>Action</th>

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

<!-- MODAL -->

<div class="modal fade"
     id="showModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header bg-light p-3">

                <h5 class="modal-title"  id="modal-title">
                    Add New Doctor
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <form class="tablelist-form"
                enctype="multipart/form-data">
               

                @csrf

                <input type="hidden"
                       id="edit-id">

                <div class="modal-body">

                    <div class="row">

                        <div class="col-md-3 mb-3">

                            <label class="form-label">
                                Doctor Code
                            </label>

                            <input type="text"
                                   id="doctor_code-field"
                                   class="form-control"
                                   required>

                        </div>

                        <div class="col-md-5 mb-3">

                            <label class="form-label">
                                Doctor Name
                            </label>

                            <input type="text"
                                   id="doctor_name-field"
                                   class="form-control"
                                   required>

                        </div>

                        <div class="col-md-2 mb-3">

                            <label class="form-label">
                                Gender
                            </label>

                            <select id="gender-field"
                                    class="form-select"
                                    required>

                                <option value="">
                                    Select
                                </option>

                                <option value="M">
                                    Male
                                </option>

                                <option value="F">
                                    Female
                                </option>

                                <option value="O">
                                    Other
                                </option>

                            </select>

                        </div>

                        <div class="col-md-2 mb-3">

                            <label class="form-label">
                                DOB
                            </label>

                            <input type="text"
                                   id="date_of_birth-field"
                                   class="form-control datepicker"
                                   placeholder="mm/dd/yyyy">

                        </div>

                        <div class="col-md-3 mb-3">

                            <label class="form-label">
                                Mobile No
                            </label>

                            <input type="text"
                                   id="mobile_no-field"
                                   class="form-control">

                        </div>

                        <div class="col-md-3 mb-3">

                            <label class="form-label">
                                Alternate Mobile
                            </label>

                            <input type="text"
                                   id="alternate_mobile_no-field"
                                   class="form-control">

                        </div>

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Email
                            </label>

                            <input type="email"
                                   id="email-field"
                                   class="form-control">

                        </div>
                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Specialisation
                            </label>

                            <small class="text-muted d-block mb-1">
                                First selected is the primary specialisation.
                            </small>

                            <select name="specialisation_codes-field[]"
                                    id="specialisation_codes-field"
                                    class="form-select select2"
                                    multiple
                                    required>

                                @foreach(($specialisations ?? []) as $specialisation)

                                    <option value="{{ $specialisation->id }}">

                                        {{ $specialisation->category }}

                                    </option>

                                @endforeach

                            </select>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Qualification
                            </label>

                            <input type="text"
                                   id="qualification-field"
                                   class="form-control">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Experience Years
                            </label>

                            <input type="number"
                                   id="experience_years-field"
                                   class="form-control">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Registration No
                            </label>

                            <input type="text"
                                   id="registration_no-field"
                                   class="form-control">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Joining Date
                            </label>

                            <input type="text"
                                   id="joining_date-field"
                                   class="form-control datepicker"
                                   placeholder="mm/dd/yyyy">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Total Consultation Fee
                            </label>

                            <input type="number"
                                   id="consultation_fee_total-field"
                                   class="form-control">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Doctor Fee
                            </label>

                            <input type="number"
                                   id="consultation_fee_doctor-field"
                                   class="form-control">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Discounted Patient Fee
                            </label>

                            <input type="number"
                                   id="consultation_fee_doctor_discounted_patient-field"
                                   class="form-control">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Discount
                            </label>

                            <input type="number"
                                   id="discount-field"
                                   class="form-control">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Status
                            </label>

                            <select id="status-field"
                                    class="form-select">

                                <option value="Active">
                                    Active
                                </option>

                                <option value="Inactive">
                                    Inactive
                                </option>

                            </select>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Doctor Image
                            </label>

                            <input type="file"
                                   id="image-field"
                                   class="form-control"
                                   accept="image/*">

                            <img id="imagePreview"
                                 class="mt-2 rounded"
                                 style="width:80px;height:80px;display:none;object-fit:cover;">

                        </div>

                        <div class="col-md-12 mb-3">

                            <label class="form-label">
                                Remarks
                            </label>

                            <textarea id="remarks"
                                      rows="3"
                                      class="form-control"></textarea>

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
                            id="add-btn">
                        Save Doctor
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>

document.addEventListener("DOMContentLoaded", function () {

    flatpickr("#joining_date-field", {

        dateFormat: "d-m-Y",

        maxDate: "today",

        allowInput: true,

        disableMobile: true
    });

    flatpickr("#date_of_birth-field", {

        dateFormat: "d-m-Y",

        allowInput: true,

        disableMobile: true
    });

});

</script>


<script src="{{ URL::asset('build/js/pages/ecommerce-doctor-list.init.js') }}"></script>

@endsection
