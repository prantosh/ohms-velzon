@extends('layouts.master')

@section('title')
    Patient Detail Correction
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Admin
    @endslot

    @slot('title')
        Patient Detail Correction
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-8">

        <div class="alert alert-warning">
            <i class="ri-shield-keyhole-line"></i>
            Saving here corrects the Name/Age/Gender on the patient's master record and on every invoice,
            appointment, doctor payable, settlement item and daily transaction already linked to this Patient ID
            -- not just the one record you're looking at. Double-check before saving.
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Find Patient</h5>
            </div>

            <div class="card-body">

                <div class="row align-items-end">

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Patient ID <span class="text-danger">*</span></label>
                        <input type="text" id="patientIdInput" class="form-control"
                               placeholder="e.g. P9876543210-01">
                    </div>

                    <div class="col-md-3 mb-3">
                        <button type="button" class="btn btn-primary w-100" id="searchPatientBtn">
                            <i class="ri-search-line"></i>
                            Search
                        </button>
                    </div>

                </div>

            </div>

        </div>

        <div id="notFoundWrap" class="alert alert-danger" style="display:none;">
            No patient found with this Patient ID.
        </div>

        <div class="card" id="editCard" style="display:none;">

            <div class="card-header">
                <h5 class="card-title mb-0">Correct Patient Details</h5>
            </div>

            <div class="card-body">

                <div id="protectedNameWarning" class="alert alert-info" style="display:none;">
                    <i class="ri-information-line"></i>
                    This patient's current name matches a Member/Admin/Supervisor account (or one of their
                    registered family members), so it is locked here. Age/Gender can still be corrected.
                </div>

                <div class="row">

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Patient ID</label>
                        <input type="text" id="edit-patient_id" class="form-control" readonly>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Mobile No</label>
                        <input type="text" id="edit-mobile_no" class="form-control" readonly>
                    </div>

                </div>

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Patient Name <span class="text-danger">*</span></label>
                        <input type="text" id="edit-patient_name" class="form-control">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Age</label>
                        <input type="number" id="edit-age" class="form-control" min="0" max="150">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Gender</label>
                        <select id="edit-gender" class="form-select">
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                </div>

                <button type="button" class="btn btn-success" id="saveCorrectionBtn">
                    <i class="ri-save-line"></i>
                    Save Correction
                </button>

            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/patient-detail-correction.init.js') }}"></script>

@endsection
