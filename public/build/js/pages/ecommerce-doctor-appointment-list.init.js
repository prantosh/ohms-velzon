"use strict";

$(function () {

    loadDoctorsBySpecialisation();

    validateAppointmentDate();

    initializePatientDropdown();
    flatpickr("#appointment_date", {

        dateFormat: "d-m-Y",

        minDate: "today",

        allowInput: true
    });

    saveAppointment();

});

/*
|--------------------------------------------------------------------------
| Load Doctors By Specialisation
|--------------------------------------------------------------------------
*/

function loadDoctorsBySpecialisation() {

    $('#specialisation_id').on('change', function () {

        let specialisation_id = $(this).val();

        $('#doctorListDiv').html('');

        if (specialisation_id) {

            $.ajax({

                url: '/doctor-appointments/get-doctors-by-specialisation',

                type: 'POST',

                data: {

                    specialisation_id: specialisation_id,

                    _token: $('meta[name="csrf-token"]').attr('content')
                },

                success: function (response) {

                    let html = '';

                    response.forEach(function (doctor) {

                        let image = doctor.doctor_image
                            ? '/uploads/doctors/' + doctor.doctor_image
                            : '/assets/images/users/avatar-1.jpg';

                        html += `

                            <div class="col-xl-4 col-md-6 mb-4">

                                <div class="card h-100 shadow-sm border-0">

                                    <div class="card-body text-center">

                                        <img
                                            src="${image}"
                                            class="rounded-circle mb-3"
                                            width="120"
                                            height="120"
                                            style="object-fit:cover;"
                                        >

                                        <h5 class="mb-1">
                                            ${doctor.doctor_name}
                                        </h5>

                                        <p class="text-primary mb-1">
                                            ${doctor.specialisation.category}
                                        </p>

                                        <p class="mb-1">
                                            ${doctor.qualification ?? ''}
                                        </p>

                                        <p class="small text-muted text-start mb-2 px-2"
                                           style="min-height:3em; line-height:1.5em;">
                                            ${doctor.schedule_summary || ''}
                                        </p>

                                        <p class="mb-3">
                                            Experience :
                                            ${doctor.experience_years ?? 0} Years
                                        </p>

                                        <button
                                            class="btn btn-primary bookAppointmentBtn"
                                            data-id="${doctor.id}"
                                            data-name="${doctor.doctor_name}"
                                        >
                                            Book Appointment
                                        </button>

                                    </div>

                                </div>

                            </div>
                        `;
                    });

                    $('#doctorListDiv').html(html);
                }
            });
        }
    });
}

/*
|--------------------------------------------------------------------------
| Open Appointment Modal
|--------------------------------------------------------------------------
*/

$(document).on('click', '.bookAppointmentBtn', function () {

    let doctor_id = $(this).data('id');

    let doctor_name = $(this).data('name');

    $('#doctor_id').val(doctor_id);

    $('#doctor_name').val(doctor_name);

    $('#appointmentModal').modal('show');

    loadDoctorDays(doctor_id);
});

/*
|--------------------------------------------------------------------------
| Load Doctor Available Days
|--------------------------------------------------------------------------
*/

function loadDoctorDays(doctor_id) {

    $.ajax({

        url: '/doctor-appointments/get-available-days',

        type: 'POST',

        data: {

            doctor_id: doctor_id,

            _token: $('meta[name="csrf-token"]').attr('content')
        },

        success: function (response) {

            $('#appointment_day').empty();

            $('#appointment_day').append(
                '<option value="">Select Day</option>'
            );

            response.forEach(function (day) {

                $('#appointment_day').append(
                    '<option value="' + day + '">' +
                    day +
                    '</option>'
                );
            });
        }
    });
}

/*
|--------------------------------------------------------------------------
| Validate Appointment Date
|--------------------------------------------------------------------------
*/

function validateAppointmentDate() {

    $('#appointment_date').on('change', function () {

        let selectedDay = $('#appointment_day').val();

        if (!selectedDay) {

            alert('Please select appointment day.');

            $('#appointment_date').val('');

            return;
        }

        let parts = $(this).val().split('-');

        let selectedDate = new Date(
            parts[2],
            parts[1] - 1,
            parts[0]
        );

        let actualDay = selectedDate.toLocaleDateString(
            'en-US',
            {
                weekday: 'long'
            }
        );

        if (
            selectedDay.toLowerCase() !==
            actualDay.toLowerCase()
        ) {

            Swal.fire({

                icon: 'warning',

                title: 'Invalid Appointment Date',

                html:
                    '<div class="fs-15">' +
                    'Selected date does not match the doctor\'s available day.' +
                    '</div>',

                confirmButtonColor: '#f7b84b',

                background: '#fff',

                customClass: {

                    popup: 'shadow-lg rounded-4',

                    title: 'fw-bold text-warning',

                    confirmButton: 'px-4'
                }
            });

            $('#appointment_date').val('');

            $('#appointment_time').empty();

            $('#appointment_time').append(
                '<option value="">Select Slot</option>'
            );

            return;
        }

        loadAvailableSlots();

        loadTotalPatients();
    });
}

/*
|--------------------------------------------------------------------------
| Appointment Day Change
|--------------------------------------------------------------------------
*/

$('#appointment_day').on('change', function () {

    $('#appointment_time').empty();

    $('#appointment_time').append(
        '<option value="">Select Slot</option>'
    );

    $('#appointment_date').val('');
});

/*
|--------------------------------------------------------------------------
| Patient Mobile Search
|--------------------------------------------------------------------------
*/

$('#patient_mobile_no').on('keyup', function () {

    let mobile_no = $(this).val();

    if (mobile_no.length >= 10) {

        $.ajax({

            url: '/doctor-appointments/get-patients-by-mobile',

            type: 'POST',

            data: {

                mobile_no: mobile_no,

                _token: $('meta[name="csrf-token"]').attr('content')
            },

            success: function (response) {

                $('#patient_search').empty();

                $('#patient_search').append(
                    '<option value=""></option>'
                );

                response.forEach(function (patient) {

                    let option = new Option(
                        patient.patient_name,
                        patient.patient_name,
                        false,
                        false
                    );
                    $(option).attr(
                        'data-patient-id',
                        patient.id
                    );

                    $(option).attr(
                        'data-patient-code',
                        patient.patient_id
                    );

                    $('#patient_search').append(option);
                });

                $('#patient_search').trigger('change');
            }
        });
    }
});
/*
|--------------------------------------------------------------------------
| Load Patients In History Dropdown
|--------------------------------------------------------------------------
*/

$('#patient_mobile_no').on('keyup', function () {

    let mobile_no = $(this).val();

    if (mobile_no.length >= 10) {

        $.ajax({

            url: '/doctor-appointments/get-patients-by-mobile',

            type: 'POST',

            data: {

                mobile_no: mobile_no,

                _token: $('meta[name="csrf-token"]').attr('content')
            },

            success: function (response) {

                $('#history_patient_id').empty();

                $('#history_patient_id').append(
                    '<option value="">Select Patient</option>'
                );

                response.forEach(function (patient) {

                    $('#history_patient_id').append(

                        '<option value="' + patient.id + '">' +

                        patient.patient_name +

                        '</option>'
                    );
                });
            }
        });
    }
});

/*
|--------------------------------------------------------------------------
| Patient Appointment History
|--------------------------------------------------------------------------
*/

$('#history_patient_id').on('change', function () {

    let patient_id = $(this).val();

    if (patient_id === '') {

        return;
    }

    $.ajax({

        url: '/doctor-appointments/get-patient-appointments',

        type: 'POST',

        data: {

            mobile_no: $('#patient_mobile_no').val(),

            patient_name: $('#history_patient_id option:selected').text(),

            _token: $('meta[name="csrf-token"]').attr('content')
        },

        success: function (response) {

            let html = '';

            if (response.length > 0) {

                response.forEach(function (appointment) {

                    html += `

                        <tr>
                            <td>
                                ${appointment.patient_name}
                            </td>
                            <td>
                                ${formatDate(appointment.appointment_date)}
                            </td>
                            <td>
                                ${appointment.appointment_time}
                            </td>
                            <td>
                                ${appointment.doctor_name}
                            </td>

                            

                        </tr>
                    `;
                });

            } else {

                html = `

                    <tr>

                        <td colspan="4"
                            class="text-center text-muted">

                            No Records Found

                        </td>

                    </tr>
                `;
            }

            $('#patientHistoryTbody').html(html);
        }
    });
});
/*
|--------------------------------------------------------------------------
| Patient Change
|--------------------------------------------------------------------------
*/

$('#patient_search').on('change', function () {

    let selectedOption = $('#patient_search option:selected');

    let patientDbId = selectedOption.attr('data-patient-id');

    let patientCode = selectedOption.attr('data-patient-code');

    $('#display_patient_id').val(patientCode ?? '');
    $('#patient_db_id').val(patientDbId ?? '');

    // Only visible once a real existing patient is actually linked --
    // this is the operator's way out if that link was picked by mistake.
    $('#clearPatientSelectionWrap').toggle(!!patientDbId);

    // No match picked (cleared, or nothing typed yet) -- leave whatever
    // the operator has already typed into #patient_name/#patient_age/
    // #patient_gender alone rather than wiping it out from under them.
    if (!patientDbId) {

        return;
    }

    // Pre-fill from the matched patient -- #patient_name stays a plain,
    // independently editable text field from here on, so correcting a
    // spelling mistake never breaks the link to this same patient record
    // (patient_db_id, not the name text, is what identifies them to the
    // server).
    $('#patient_name').val(selectedOption.text());

    $.ajax({

        url: '/doctor-appointments/get-patient-details',

        type: 'POST',

        data: {

            patient_id: patientDbId,

            mobile_no: $('#patient_mobile_no').val(),

            _token: $('meta[name="csrf-token"]').attr('content')
        },

        success: function (patient) {

            $('#patient_age').val(patient.age);

            $('#patient_gender').val(patient.gender);
        }
    });
});
/*
|--------------------------------------------------------------------------
| Clear An Accidentally-Selected Patient
|--------------------------------------------------------------------------
| Detaches #patient_name from the wrongly-picked existing patient record
| entirely -- without this, typing over the name after a mis-click still
| carries the old patient_db_id along, silently renaming that patient
| instead of registering a new one.
*/

$(document).on('click', '#clearPatientSelectionBtn', function () {

    $('#patient_search').val(null).trigger('change.select2');

    $('#patient_db_id').val('');
    $('#display_patient_id').val('');
    $('#patient_name').val('');
    $('#patient_age').val('');
    $('#patient_gender').val('');

    $('#clearPatientSelectionWrap').hide();

    $('#patient_name').trigger('focus');
});
/*
|--------------------------------------------------------------------------
| Initialize Patient Dropdown
|--------------------------------------------------------------------------
*/

function initializePatientDropdown() {

    $('#patient_search').select2({

        dropdownParent: $('#appointmentModal'),

        placeholder: 'Search by mobile number above, then pick a match',

        width: '100%',

        allowClear: true,

        minimumInputLength: 0
    });
}

/*
|--------------------------------------------------------------------------
| Load Available Slots
|--------------------------------------------------------------------------
*/

function loadAvailableSlots() {

    let doctor_id = $('#doctor_id').val();

    let appointment_date = convertToMysqlDate(
        $('#appointment_date').val()
    );

    if (doctor_id && appointment_date) {

        $.ajax({

            url: '/doctor-appointments/get-slots',

            type: 'POST',

            data: {

                doctor_id: doctor_id,

                appointment_date: appointment_date,

                _token: $('meta[name="csrf-token"]').attr('content')
            },

            success: function (response) {

                $('#appointment_time').empty();

                $('#appointment_time').append(
                    '<option value="">Select Slot</option>'
                );

                response.forEach(function (slot) {

                    $('#appointment_time').append(
                        '<option value="' +
                        slot.value +
                        '" data-session-id="' +
                        slot.session_id +
                        '">' +
                        slot.display +
                        '</option>'
                    );
                });
            }
        });
    }
}

/*
|--------------------------------------------------------------------------
| Load Total Patients
|--------------------------------------------------------------------------
*/

function loadTotalPatients() {

    let doctor_id = $('#doctor_id').val();

    let appointment_date = convertToMysqlDate(
        $('#appointment_date').val()
    );

    if (doctor_id && appointment_date) {

        $.ajax({

            url: '/doctor-appointments/get-total-patients',

            type: 'POST',

            data: {

                doctor_id: doctor_id,

                appointment_date: appointment_date,

                _token: $('meta[name="csrf-token"]').attr('content')
            },

            success: function (response) {

                $('#totalPatientCount').html(
                    'Total Patients Booked : ' +
                    response.total_patients
                );
            }
        });
    }
}
/*
|--------------------------------------------------------------------------
| Format Date DD-MM-YYYY
|--------------------------------------------------------------------------
*/

function formatDate(dateString) {

    let date = new Date(dateString);

    let day = String(date.getDate()).padStart(2, '0');

    let month = String(date.getMonth() + 1).padStart(2, '0');

    let year = date.getFullYear();

    return day + '-' + month + '-' + year;
}
/*
|--------------------------------------------------------------------------
| Convert DD-MM-YYYY To YYYY-MM-DD
|--------------------------------------------------------------------------
*/

function convertToMysqlDate(dateString) {

    let parts = dateString.split('-');

    return parts[2] + '-' + parts[1] + '-' + parts[0];
}
/*
|--------------------------------------------------------------------------
| Save Appointment
|--------------------------------------------------------------------------
*/

function saveAppointment() {

    $('#saveAppointmentBtn').off('click').on('click', function () {

        // Age, gender, and remarks are the only optional fields on this
        // form -- every other field must be filled before an appointment
        // can be saved. display_patient_id is deliberately excluded: it's
        // read-only and stays blank for a brand new patient (their real
        // patient ID is generated when the appointment itself is created).
        let hasPatientName = $('#patient_name').val().trim().length > 0;

        let missing = [];

        if (!$('#appointment_day').val()) missing.push('Available Day');
        if (!$('#appointment_date').val()) missing.push('Appointment Date');
        if (!$('#appointment_time').val()) missing.push('Available Slot');
        if (!$('#patient_mobile_no').val()) missing.push('Mobile Number');
        if (!hasPatientName) missing.push('Patient Name');

        if (missing.length) {

            Swal.fire({
                icon: 'warning',
                title: 'Please fill in the required fields',
                text: missing.join(', ')
            });

            return;
        }

        let btn = $(this);

        btn.prop('disabled', true);

        Swal.fire({
            title: 'Saving....',
            text: 'Please wait',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        $.ajax({

            url: '/doctor-appointments',

            type: 'POST',

            data: {

                doctor_id: $('#doctor_id').val(),

                doctor_schedule_session_id: $('#appointment_time option:selected')
                    .attr('data-session-id'),

                patient_id: $('#patient_db_id').val(),

                patient_name: $('#patient_name').val(),

                patient_mobile_no:
                    $('#patient_mobile_no').val(),

                patient_age:
                    $('#patient_age').val(),

                patient_gender:
                    $('#patient_gender').val(),

                appointment_day:
                    $('#appointment_day').val(),

                appointment_date:
                    convertToMysqlDate(
                        $('#appointment_date').val()
                    ),

                appointment_time:
                    $('#appointment_time').val(),

                remarks:
                    $('#remarks').val(),

                confirm_slot_change:
                    $('#confirm_slot_change').val(),
                _token:
                    $('meta[name="csrf-token"]').attr('content')
            },

            success: function (response) {

                if (response.status === 'confirm_change') {

                    Swal.fire({

                        icon: 'warning',

                        title: 'Slot Already Booked',

                        text: response.message,

                        showCancelButton: true,

                        confirmButtonText: 'Yes, Change Slot',

                        cancelButtonText: 'No',

                        confirmButtonColor: '#405189',

                        cancelButtonColor: '#f06548'

                    }).then((result) => {

                        if (result.isConfirmed) {

                            $('#confirm_slot_change').val(1);

                            $('#saveAppointmentBtn').click();

                        } else {

                            btn.prop('disabled', false);
                        }
                    });

                    return;
                }

                if (response.status) {

                    $('#confirm_slot_change').val('');

                    let modalEl =
                        document.getElementById(
                            'appointmentModal'
                        );

                    let modal =
                        bootstrap.Modal.getInstance(
                            modalEl
                        );

                    if (modal) {

                        modal.hide();
                    }

                    let patientName = $('#patient_name').val();

                    let doctorName = $('#doctor_name').val();

                    Swal.fire({

                        icon: 'success',

                        title: 'Appointment Booked Successfully',

                        html:
                            '<div class="text-start">' +

                            '<b>Doctor :</b> ' + doctorName +

                            '<br><br>' +

                            '<b>Patient :</b> ' + patientName +

                            '<br><br>' +

                            '<b>Date :</b> ' + $('#appointment_date').val() +

                            '<br><br>' +

                            '<b>Slot :</b> ' +
                            $('#appointment_time option:selected').text() +

                            '<br><br>' +

                            '<b>Serial Number :</b> ' +
                            (response.token_no ?? '-') +

                            '</div>',

                        confirmButtonColor: '#405189'

                    }).then(() => {

                        window.location.href =
                            '/doctor-appointments';
                    });

                } else {

                    $('#confirm_slot_change').val('');

                    btn.prop('disabled', false);

                    Swal.fire({

                        icon: 'error',

                        title: 'Error',

                        text: response.message,

                        confirmButtonColor: '#f06548'
                    });
                }
            },

            error: function (xhr) {

                btn.prop('disabled', false);

                console.log(xhr.responseText);

                Swal.fire({

                    icon: 'error',

                    title: 'Error',

                    text: xhr.responseJSON?.message
                        ?? xhr.responseText
                        ?? 'Something went wrong.',

                    confirmButtonColor: '#f06548'
                });
            }
        });
    });
}
