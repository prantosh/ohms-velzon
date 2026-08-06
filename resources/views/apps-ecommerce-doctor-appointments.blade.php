@extends('layouts.master')

@section('title')
    @lang('translation.doctorappointments')
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
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Hospital
    @endslot

    @slot('title')
        Doctor Appointment Management
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="card-header border-0">

                <div class="row g-3 align-items-center">

                    <div class="col-md-4">

                        <label class="form-label mb-2">
                            Select Specialisation
                        </label>

                        <select class="form-select"
                                id="specialisation_id">

                            <option value="">
                                Select Specialisation
                            </option>

                            @foreach($specialisations as $specialisation)

                                <option value="{{ $specialisation->id }}">
                                    {{ $specialisation->category }}
                                </option>

                            @endforeach

                        </select>

                    </div>

                </div>

            </div>

            <div class="card-body">

                <div class="row"
                     id="doctorListDiv">

                </div>

            </div>

        </div>

    </div>

</div>

<!-- APPOINTMENT MODAL -->

<div class="modal fade"
     id="appointmentModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header bg-light p-3">

                <h5 class="modal-title">
                    Book Appointment
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <input type="hidden"
                       id="doctor_id">
                <input type="hidden"
                       id="confirm_slot_change"
                       value="">
                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Doctor Name
                        </label>

                        <input type="text"
                               class="form-control"
                               id="doctor_name"
                               readonly>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Available Day <span class="text-danger">*</span>
                        </label>

                        <select class="form-select"
                                id="appointment_day"
                                required>

                            <option value="">
                                Select Day
                            </option>

                        </select>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Appointment Date <span class="text-danger">*</span>
                        </label>

                        <input type="text"
                               class="form-control"
                               id="appointment_date"
                               placeholder="DD/MM/YYYY"
                               required>
                        <small
                               class="text-primary fw-semibold"
                               id="totalPatientCount">
                        </small>
                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Available Slot <span class="text-danger">*</span>
                        </label>

                        <select class="form-select"
                                id="appointment_time"
                                required>

                            <option value="">
                                Select Slot
                            </option>

                        </select>

                    </div>

                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Mobile Number <span class="text-danger">*</span>
                        </label>

                        <input type="text"
                               class="form-control"
                               id="patient_mobile_no"
                               required>

                    </div>

                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Search Existing Patient
                        </label>

                        <select class="form-select"
                                id="patient_search">



                        </select>
                        <small class="text-muted">
                            Optional -- pick a match to fill in their details below, or skip this and type fresh below for a new patient.
                        </small>

                    </div>
                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Patient Name <span class="text-danger">*</span>
                        </label>

                        <input type="text"
                               class="form-control"
                               id="patient_name"
                               required>
                        <input type="hidden"
                               id="patient_db_id">
                        <small class="text-muted">
                            Freely editable -- correcting a spelling mistake here still updates the same patient record selected above.
                        </small>
                        <div id="clearPatientSelectionWrap"
                             class="mt-1"
                             style="display:none;">

                            <a href="javascript:void(0);"
                               id="clearPatientSelectionBtn"
                               class="small">
                                <i class="ri-close-circle-line align-middle"></i>
                                Wrong patient selected? Clear &amp; enter a new one
                            </a>

                        </div>

                    </div>
                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Patient ID
                        </label>

                        <input type="text"
                               class="form-control"
                               id="display_patient_id"
                               readonly>
                        <small class="text-muted">
                            Left blank for a new patient -- it's generated automatically when the appointment is created.
                        </small>

                    </div>
                    <div class="col-md-2 mb-3">

                        <label class="form-label">
                            Age
                        </label>

                        <input type="number"
                               class="form-control"
                               id="patient_age"
                               placeholder="Enter Age"
                               min="0"
                               max="120">

                    </div>

                    <div class="col-md-2 mb-3">

                        <label class="form-label">
                            Gender
                        </label>

                        <select class="form-select"
                                id="patient_gender">

                            <option value="">
                                Select
                            </option>

                            <option value="Male">
                                Male
                            </option>

                            <option value="Female">
                                Female
                            </option>

                            <option value="Other">
                                Other
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
                    <div class="col-md-12 mt-4">

                        <div class="card border">

                            <div class="card-header bg-light">

                                <h5 class="mb-0">
                                    Patient Appointment History
                                </h5>

                            </div>

                            <div class="card-body">

                                <div class="row mb-3">

                                    <div class="col-md-6">

                                        <label class="form-label">
                                            Select Patient
                                        </label>

                                        <select class="form-select"
                                                id="history_patient_id">

                                            <option value="">
                                                Select Patient
                                            </option>

                                        </select>

                                    </div>

                                </div>

                                <div class="table-responsive">

                                    <table class="table table-bordered align-middle">

                                        <thead class="table-light">

                                            <tr>

                                                <th>Name of Patient</th>

                                                <th>Date of Appointment</th>

                                                <th>Slot</th>

                                                <th>Name of Doctor</th>

                                            </tr>

                                        </thead>

                                        <tbody id="patientHistoryTbody">

                                            <tr>

                                                <td colspan="4"
                                                    class="text-center text-muted">

                                                    No Records Found

                                                </td>

                                            </tr>

                                        </tbody>

                                    </table>

                                </div>

                            </div>

                        </div>

                    </div>
                </div>

            </div>

            <div class="modal-footer">

                <button type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal">

                    Close

                </button>

                <button type="button"
                        class="btn btn-success"
                        id="saveAppointmentBtn">

                    Save Appointment

                </button>

            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="{{ URL::asset('build/js/pages/ecommerce-doctor-appointment-list.init.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

@endsection
