"use strict";

let verificationToken = null;
let verifiedMobile = null;

/*
|--------------------------------------------------------------------------
| STEP NAVIGATION
|--------------------------------------------------------------------------
*/

function showStep(stepId) {
    $('.booking-step').removeClass('active');
    $('#' + stepId).addClass('active');
    $('html, body').animate({ scrollTop: 0 }, 200);
}

/*
|--------------------------------------------------------------------------
| CLOSE
|--------------------------------------------------------------------------
| This page is opened as its own tab via a plain link from the main site
| (not window.open()'d by script), so browsers will normally block
| window.close() here -- it's attempted anyway in case it does work, and
| the fallback sends the patient back to the main website after a short
| delay (which never fires if the close above actually succeeded, since
| the page is gone by then).
*/

function closeBookingPage() {

    window.close();

    setTimeout(function () {
        window.location.href = 'https://abssrk.online';
    }, 150);
}

$(document).on('click', '.closeBookingBtn', function () {
    closeBookingPage();
});

/*
|--------------------------------------------------------------------------
| OTP -- SEND
|--------------------------------------------------------------------------
*/

$(document).on('click', '#sendOtpBtn', function () {

    let mobile = $('#patient_mobile_no').val().trim();

    if (!/^[1-9][0-9]{9}$/.test(mobile)) {
        Swal.fire({ icon: 'warning', title: 'Enter a valid 10 digit mobile number' });
        return;
    }

    let btn = $(this);
    btn.prop('disabled', true).text('Sending...');

    $.ajax({
        url: '/book-appointment/send-otp',
        type: 'POST',
        data: {
            mobile_no: mobile,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {

            btn.prop('disabled', false).text('Send OTP via WhatsApp');

            if (!response.status) {
                Swal.fire({ icon: 'error', title: 'Could not send OTP', text: response.message });
                return;
            }

            $('#otpEntryRow').show();
            $('#patient_mobile_no').prop('readonly', true);
            $('.otp-box').val('');
            $('#otpInputs .otp-box').first().focus();

            Swal.fire({ icon: 'success', title: 'OTP Sent', text: response.message, timer: 2000, showConfirmButton: false });
        },
        error: function () {
            btn.prop('disabled', false).text('Send OTP via WhatsApp');
            Swal.fire({ icon: 'error', title: 'Something went wrong. Please try again.' });
        }
    });
});

$(document).on('click', '#resendOtpBtn', function () {
    $('#otpEntryRow').hide();
    $('#patient_mobile_no').prop('readonly', false).val('');
    $('.otp-box').val('');
});

/*
|--------------------------------------------------------------------------
| OTP INPUT BOXES -- auto-advance
|--------------------------------------------------------------------------
*/

$(document).on('input', '.otp-box', function () {

    this.value = this.value.replace(/\D/g, '').substring(0, 1);

    if (this.value.length === 1) {
        $(this).next('.otp-box').focus();
    }
});

$(document).on('keydown', '.otp-box', function (e) {

    if (e.key === 'Backspace' && this.value === '') {
        $(this).prev('.otp-box').focus();
    }
});

/*
|--------------------------------------------------------------------------
| OTP -- VERIFY
|--------------------------------------------------------------------------
*/

$(document).on('click', '#verifyOtpBtn', function () {

    let code = '';
    $('.otp-box').each(function () { code += $(this).val(); });

    if (code.length !== 6) {
        Swal.fire({ icon: 'warning', title: 'Enter the complete 6 digit OTP' });
        return;
    }

    let mobile = $('#patient_mobile_no').val().trim();
    let btn = $(this);
    btn.prop('disabled', true).text('Verifying...');

    $.ajax({
        url: '/book-appointment/verify-otp',
        type: 'POST',
        data: {
            mobile_no: mobile,
            otp_code: code,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {

            btn.prop('disabled', false).text('Verify OTP');

            if (!response.status) {
                Swal.fire({ icon: 'error', title: 'Verification Failed', text: response.message });
                return;
            }

            verificationToken = response.verification_token;
            verifiedMobile = mobile;

            showStep('step-doctor');
        },
        error: function () {
            btn.prop('disabled', false).text('Verify OTP');
            Swal.fire({ icon: 'error', title: 'Something went wrong. Please try again.' });
        }
    });
});

/*
|--------------------------------------------------------------------------
| SPECIALISATION -> DOCTOR CARDS
|--------------------------------------------------------------------------
*/

$(document).on('change', '#specialisation_id', function () {

    let specialisationId = $(this).val();

    $('#doctorListDiv').html('');

    if (!specialisationId) {
        return;
    }

    $.ajax({
        url: '/book-appointment/doctors-by-specialisation',
        type: 'POST',
        data: {
            specialisation_id: specialisationId,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {

            let html = '';

            if (!response.length) {
                html = '<div class="col-12 text-muted text-center py-4">No doctors available for this specialisation right now.</div>';
            }

            response.forEach(function (doctor) {

                let image = doctor.doctor_image
                    ? '/uploads/doctors/' + doctor.doctor_image
                    : '/assets/images/users/avatar-1.jpg';

                html += `
                    <div class="col-md-4 mb-3">
                        <div class="card border shadow-sm h-100 doctor-card"
                             data-id="${doctor.id}"
                             data-name="${doctor.doctor_name}">
                            <div class="card-body text-center">
                                <img src="${image}" class="rounded-circle mb-2" width="90" height="90" style="object-fit:cover;">
                                <h6 class="mb-1">${doctor.doctor_name}</h6>
                                <p class="text-primary small mb-1">${doctor.specialisation ? doctor.specialisation.category : ''}</p>
                                <p class="small text-muted mb-1">${doctor.qualification ?? ''}</p>
                                <p class="small text-muted" style="min-height:3em; line-height:1.5em;">${doctor.schedule_summary ?? ''}</p>
                            </div>
                        </div>
                    </div>
                `;
            });

            $('#doctorListDiv').html(html);
        }
    });
});

$(document).on('click', '.doctor-card', function () {

    $('.doctor-card').removeClass('selected');
    $(this).addClass('selected');

    let doctorId = $(this).data('id');
    let doctorName = $(this).data('name');

    $('#doctor_id').val(doctorId);
    $('#doctor_name').val(doctorName);

    $('#appointment_date').val('');
    $('#appointment_time').html('<option value="">Select Slot</option>');

    showStep('step-details');
    loadDoctorDays(doctorId);
});

$(document).on('click', '#backToDoctorsBtn', function () {
    showStep('step-doctor');
});

/*
|--------------------------------------------------------------------------
| AVAILABLE DAYS / SLOTS
|--------------------------------------------------------------------------
*/

function loadDoctorDays(doctorId) {

    $.ajax({
        url: '/book-appointment/available-days',
        type: 'POST',
        data: {
            doctor_id: doctorId,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function (days) {

            let select = $('#appointment_day');
            select.html('<option value="">Select Day</option>');

            days.forEach(function (day) {
                select.append(`<option value="${day}">${day}</option>`);
            });
        }
    });
}

// Guards against an out-of-order AJAX response: if the date is changed
// again before an earlier available-slots request finishes, that earlier
// response could otherwise arrive AFTER the newer one and overwrite the
// dropdown with slots for a date that's no longer selected. Each request
// is tagged with the request-time date and a monotonically increasing
// sequence number; a response is only applied if both still match the
// current state when it arrives.
let slotsRequestSeq = 0;

$(document).on('change', '#appointment_date', function () {

    let doctorId = $('#doctor_id').val();
    let dateStr = $(this).val();

    slotsRequestSeq++;
    let thisRequestSeq = slotsRequestSeq;

    if (!doctorId || !dateStr) {
        $('#appointment_time').html('<option value="">Select Slot</option>');
        return;
    }

    let parts = dateStr.split('-');
    let mysqlDate = parts[2] + '-' + parts[1] + '-' + parts[0];

    // The "Available Day" dropdown is just a reference list of the
    // doctor's working days -- it was never actually cross-checked against
    // the calendar date picked below it, so choosing e.g. "Monday" there
    // and then picking a date that happens to fall on a Wednesday silently
    // showed real Wednesday slots with no warning that the day and date
    // didn't match. Block that mismatch here, same as the internal staff
    // booking screen already does.
    let selectedDay = $('#appointment_day').val();

    if (selectedDay) {

        let dateParts = dateStr.split('-');
        let pickedDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
        let actualDay = pickedDate.toLocaleDateString('en-US', { weekday: 'long' });

        if (selectedDay.toLowerCase() !== actualDay.toLowerCase()) {

            Swal.fire({
                icon: 'warning',
                title: 'Date does not match selected day',
                text: `${dateStr} is a ${actualDay}, not ${selectedDay}. Please pick a date that falls on ${selectedDay}, or change the "Available Day" selection.`
            });

            $(this).val('');
            $('#appointment_time').html('<option value="">Select Slot</option>');
            return;
        }
    }

    // Clear immediately so a stale slot from a previous date is never
    // left on screen while the new request is in flight.
    $('#appointment_time').html('<option value="">Loading...</option>');

    $.ajax({
        url: '/book-appointment/available-slots',
        type: 'POST',
        data: {
            doctor_id: doctorId,
            appointment_date: mysqlDate,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function (slots) {

            // A newer date-change (and its own request) has happened
            // since this one fired -- this response is stale, discard it.
            if (thisRequestSeq !== slotsRequestSeq) {
                return;
            }

            // The date field itself has since changed further (defensive
            // double-check alongside the sequence guard above).
            if ($('#appointment_date').val() !== dateStr) {
                return;
            }

            let select = $('#appointment_time');
            select.html('<option value="">Select Slot</option>');

            if (!slots.length) {
                select.append('<option value="" disabled>No slots available on this date</option>');
                return;
            }

            slots.forEach(function (slot) {
                select.append(`<option value="${slot.value}" data-session-id="${slot.session_id}">${slot.display}</option>`);
            });
        }
    });
});

/*
|--------------------------------------------------------------------------
| SAVE APPOINTMENT
|--------------------------------------------------------------------------
*/

$(document).on('click', '#saveAppointmentBtn', function () {

    let doctorId = $('#doctor_id').val();
    let sessionId = $('#appointment_time option:selected').data('session-id');
    let dateStr = $('#appointment_date').val();
    let timeVal = $('#appointment_time').val();
    let patientName = $('#patient_name').val().trim();
    let patientAge = $('#patient_age').val();
    let patientGender = $('#patient_gender').val();

    if (!doctorId || !dateStr || !timeVal || !sessionId) {
        Swal.fire({ icon: 'warning', title: 'Please select date and slot' });
        return;
    }

    if (!patientName || !patientAge || !patientGender) {
        Swal.fire({ icon: 'warning', title: 'Please fill in patient name, age and gender' });
        return;
    }

    if (!verificationToken || !verifiedMobile) {
        Swal.fire({ icon: 'error', title: 'Mobile number not verified', text: 'Please verify your mobile number again.' });
        showStep('step-mobile');
        return;
    }

    let parts = dateStr.split('-');
    let mysqlDate = parts[2] + '-' + parts[1] + '-' + parts[0];

    let btn = $(this);
    btn.prop('disabled', true).text('Booking...');

    $.ajax({
        url: '/book-appointment/store',
        type: 'POST',
        data: {
            doctor_id: doctorId,
            doctor_schedule_session_id: sessionId,
            patient_name: patientName,
            patient_mobile_no: verifiedMobile,
            patient_age: patientAge,
            patient_gender: patientGender,
            appointment_date: mysqlDate,
            appointment_time: timeVal,
            verification_token: verificationToken,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {

            btn.prop('disabled', false).text('Confirm Booking');

            if (!response.status) {
                Swal.fire({ icon: 'error', title: 'Could not book appointment', text: response.message });
                return;
            }

            window.location.href = '/book-appointment/confirmation/' + response.appointment_no;
        },
        error: function () {
            btn.prop('disabled', false).text('Confirm Booking');
            Swal.fire({ icon: 'error', title: 'Something went wrong. Please try again.' });
        }
    });
});

/*
|--------------------------------------------------------------------------
| INIT
|--------------------------------------------------------------------------
*/

$(document).ready(function () {

    flatpickr('#appointment_date', {
        dateFormat: 'd-m-Y',
        minDate: 'today',
        allowInput: false
    });

    $(document).on('input', '#patient_mobile_no', function () {
        this.value = this.value.replace(/\D/g, '').substring(0, 10);
    });
});
