@extends('layouts.master-without-nav')

@section('title')
Book Doctor Appointment Online
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    .booking-wrapper {
        max-width: 900px;
        margin: 0 auto;
        padding: 40px 15px;
    }

    .booking-step {
        display: none;
    }

    .booking-step.active {
        display: block;
    }

    .doctor-card {
        cursor: pointer;
        transition: transform .2s ease, box-shadow .2s ease;
    }

    .doctor-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 .75rem 1.5rem rgba(0,0,0,.1);
    }

    .doctor-card.selected {
        border: 2px solid #405189 !important;
    }

    #otpInputs input {
        width: 45px;
        text-align: center;
        font-size: 20px;
        font-weight: 700;
    }

    /*
     * master-without-nav hardcodes data-bs-theme="dark" on <html> (shared
     * with the login page), which flips Bootstrap/Velzon's text-color
     * variables to light-on-dark. This page's cards/inputs are plain white,
     * so that combination reads as low-contrast light text on white. Force
     * a plain light theme here regardless of the inherited attribute.
     */
    .booking-wrapper .card,
    .booking-wrapper .doctor-card {
        background-color: #fff !important;
        color: #212529 !important;
    }

    .booking-wrapper .card-header,
    .booking-wrapper .card-footer {
        background-color: #f8f9fa !important;
        color: #212529 !important;
    }

    .booking-wrapper .form-control,
    .booking-wrapper .form-select,
    .booking-wrapper #otpInputs input {
        background-color: #fff !important;
        color: #212529 !important;
        border-color: #ced4da !important;
    }

    .booking-wrapper .form-control::placeholder {
        color: #6c757d !important;
        opacity: 1;
    }

    .booking-wrapper .form-label,
    .booking-wrapper h3,
    .booking-wrapper h5,
    .booking-wrapper h6 {
        color: #212529 !important;
    }

    .booking-wrapper .text-muted {
        color: #6c757d !important;
    }
</style>

@endsection

@section('content')

<div class="booking-wrapper">

    <div class="text-center mb-4">
        <h3 class="fw-bold">Book Doctor Appointment</h3>
        <p class="text-muted">ABSSRK -- Online appointment booking</p>
    </div>

    <!-- STEP 1: MOBILE + OTP -->

    <div class="card booking-step active" id="step-mobile">

        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Verify Your Mobile Number</h5>
            <button type="button" class="btn btn-sm btn-outline-secondary closeBookingBtn">
                Close
            </button>
        </div>

        <div class="card-body p-4">

            <div class="row g-3 align-items-end" id="mobileEntryRow">

                <div class="col-md-8">
                    <label class="form-label">Mobile Number</label>
                    <input type="text" id="patient_mobile_no" class="form-control" maxlength="10"
                           placeholder="Enter 10 digit mobile number">
                </div>

                <div class="col-md-4">
                    <button type="button" id="sendOtpBtn" class="btn btn-primary w-100">
                        Send OTP via WhatsApp
                    </button>
                </div>

            </div>

            <div class="row g-3 align-items-end mt-2" id="otpEntryRow" style="display:none;">

                <div class="col-md-12">
                    <label class="form-label">Enter the 6-digit OTP sent to your WhatsApp</label>
                    <div class="d-flex gap-2" id="otpInputs">
                        <input type="text" maxlength="1" class="form-control otp-box">
                        <input type="text" maxlength="1" class="form-control otp-box">
                        <input type="text" maxlength="1" class="form-control otp-box">
                        <input type="text" maxlength="1" class="form-control otp-box">
                        <input type="text" maxlength="1" class="form-control otp-box">
                        <input type="text" maxlength="1" class="form-control otp-box">
                    </div>
                </div>

                <div class="col-md-6 mt-3">
                    <button type="button" id="verifyOtpBtn" class="btn btn-success w-100">
                        Verify OTP
                    </button>
                </div>

                <div class="col-md-6 mt-3">
                    <button type="button" id="resendOtpBtn" class="btn btn-outline-secondary w-100">
                        Change Number / Resend OTP
                    </button>
                </div>

            </div>

        </div>

    </div>

    <!-- STEP 2: SPECIALISATION + DOCTOR -->

    <div class="card booking-step" id="step-doctor">

        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Select Specialisation &amp; Doctor</h5>
            <button type="button" class="btn btn-sm btn-outline-secondary closeBookingBtn">
                Close
            </button>
        </div>

        <div class="card-body">

            <label class="form-label">Specialisation</label>
            <select class="form-select mb-3" id="specialisation_id">
                <option value="">-- Select Specialisation --</option>
                @foreach($specialisations as $specialisation)
                <option value="{{ $specialisation->id }}">{{ $specialisation->category }}</option>
                @endforeach
            </select>

            <div class="row g-3" id="doctorListDiv"></div>

        </div>

    </div>

    <!-- STEP 3: DATE / SLOT / PATIENT DETAILS -->

    <div class="card booking-step" id="step-details">

        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Appointment Details</h5>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="backToDoctorsBtn">
                    &laquo; Choose Different Doctor
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary closeBookingBtn">
                    Close
                </button>
            </div>
        </div>

        <div class="card-body">

            <input type="hidden" id="doctor_id">

            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label">Doctor</label>
                    <input type="text" class="form-control" id="doctor_name" readonly>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Available Day</label>
                    <select class="form-select" id="appointment_day">
                        <option value="">Select Day</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Appointment Date</label>
                    <input type="text" class="form-control" id="appointment_date" placeholder="DD/MM/YYYY">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Available Slot</label>
                    <select class="form-select" id="appointment_time">
                        <option value="">Select Slot</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Patient Name</label>
                    <input type="text" class="form-control" id="patient_name">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Age</label>
                    <input type="number" class="form-control" id="patient_age" min="0" max="120">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Gender</label>
                    <select class="form-select" id="patient_gender">
                        <option value="">Select</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

            </div>

        </div>

        <div class="card-footer text-end">
            <button type="button" class="btn btn-success" id="saveAppointmentBtn">
                Confirm Booking
            </button>
        </div>

    </div>

</div>

@endsection

@section('script')

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script src="{{ URL::asset('build/js/pages/public-appointment-booking.init.js') }}"></script>

@endsection
