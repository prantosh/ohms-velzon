@extends('layouts.master')

@section('title')
    WhatsApp Auto-Send Settings
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
        WhatsApp Auto-Send Settings
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-9">

        <div class="alert alert-info">
            <i class="ri-whatsapp-line"></i>
            Each switch below controls whether the system sends that category of WhatsApp message
            <b>automatically</b>, the moment the underlying action happens (invoice saved, report confirmed,
            appointment booked, etc.). Turning a category off does <b>not</b> remove the manual
            "Send WhatsApp" button anywhere in the app -- staff can always resend by hand regardless of this
            setting.
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Message Categories</h5>
            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th>Category</th>
                                <th width="140" class="text-center">Automatic Sending</th>
                            </tr>
                        </thead>

                        <tbody>

                            @foreach($rows as $row)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $row['label'] }}</div>

                                    @switch($row['message_type'])
                                        @case('OTP_APPOINTMENT_BOOKING')
                                            <small class="text-muted d-block">Sent when a public patient requests a login OTP to book an appointment online.</small>
                                            <div class="alert alert-warning py-1 px-2 mt-1 mb-0 small">
                                                <i class="ri-alert-line"></i>
                                                There is no other delivery channel for this OTP. Turning this off will block
                                                public online appointment booking entirely until re-enabled.
                                            </div>
                                            @break
                                        @case('OTP_FORGOT_PASSWORD')
                                            <small class="text-muted d-block">Sent when a staff member requests a password-reset OTP.</small>
                                            <div class="alert alert-warning py-1 px-2 mt-1 mb-0 small">
                                                <i class="ri-alert-line"></i>
                                                There is no other delivery channel for this OTP. Turning this off will block
                                                staff self-service password reset entirely until re-enabled.
                                            </div>
                                            @break
                                        @case('INVOICE')
                                            <small class="text-muted d-block">Sent when an invoice is created (Diagnostic, Doctor Visit, Equipment Rental, Ambulance Rental, Membership Fee).</small>
                                            @break
                                        @case('APPOINTMENT')
                                            <small class="text-muted d-block">Sent when a doctor appointment is booked (staff or public portal).</small>
                                            @break
                                        @case('TEST_REPORT')
                                            <small class="text-muted d-block">Sent when a pathology test report is confirmed and locked.</small>
                                            @break
                                        @case('USG_REPORT')
                                            <small class="text-muted d-block">Sent when a USG report is confirmed. Confirmed per billed line -- an invoice with several USG items confirmed separately sends once per line.</small>
                                            @break
                                        @case('NON_PATHOLOGY_REPORT')
                                            <small class="text-muted d-block">Sent when a non-pathology report (X-Ray, Cardiology, EMG-NCV, etc.) is confirmed. Confirmed per billed line -- an invoice with several items confirmed separately sends once per line.</small>
                                            @break
                                        @case('DOCTOR_SCHEDULE_CHANGE')
                                            <small class="text-muted d-block">Sent to affected patients when a doctor's schedule edit shifts their booked appointment time.</small>
                                            @break
                                        @case('DOCTOR_APPOINTMENT_REASSIGNED')
                                            <small class="text-muted d-block">Sent to affected patients when their appointment is bulk-reassigned to a new date after a doctor blackout/leave.</small>
                                            @break
                                    @endswitch
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch fs-4 d-inline-block">
                                        <input class="form-check-input autoSendToggle" type="checkbox" role="switch"
                                               data-message-type="{{ $row['message_type'] }}"
                                               {{ $row['is_enabled'] ? 'checked' : '' }}>
                                    </div>
                                </td>
                            </tr>
                            @endforeach

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/whatsapp-auto-send-settings.init.js') }}"></script>

@endsection
