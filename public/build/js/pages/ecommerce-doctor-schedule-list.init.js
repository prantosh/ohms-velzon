let scheduleDataTable = null;

const SCHEDULE_DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

function escapeAttr(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/*
|--------------------------------------------------------------------------
| LOAD SCHEDULES
|--------------------------------------------------------------------------
*/

function loadSchedules() {

    $.ajax({

        url: "/doctor-schedules/list",

        type: "GET",

        success: function (response) {

            let tbody =
                $("#scheduleTableBody");


            if ($.fn.DataTable.isDataTable('#scheduleTable')) {

                $('#scheduleTable')
                    .DataTable()
                    .clear()
                    .destroy();
            }

            tbody.html('');

            response.data.forEach(function (raw) {

                let sessions = raw.sessions || [];

                tbody.append(`

                    <tr>

                        <td>${raw.id}</td>

                        <td>

                            ${raw.doctor && raw.doctor.doctor_image ? `<img src="/uploads/doctors/${raw.doctor.doctor_image}" class="rounded-circle avatar-sm" style="object-fit:cover; width:40px; height:40px;">`
                             :
                             `<div class="avatar-sm rounded-circle bg-light d-flex align-items-center justify-content-center">
                              <i class="ri-user-3-line fs-5 text-muted"></i>
                              </div>`
                            }

                        </td>
                        <td>
                            ${raw.doctor
                        ? raw.doctor.doctor_name
                        : ''}
                        </td>
                        <td>
                        ${raw.doctor && raw.doctor.specialisation_display
                        ? raw.doctor.specialisation_display
                        : ''}
                        </td>
                        ${SCHEDULE_DAYS.map(day => `<td>${formatDaySessions(sessions, day)}</td>`).join('')}

                        <td>${raw.status == 1 ? 'Active' : 'Inactive'}</td>

                        <td>

                            <div class="d-flex gap-2">

                                <button class="btn btn-sm btn-soft-info editBtn"

                                        data-id="${raw.id}"

                                        data-doctor_id="${raw.doctor_id}"

                                        data-status="${raw.status}"

                                        data-sessions="${escapeAttr(JSON.stringify(sessions))}">

                                    <i class="ri-pencil-fill"></i>

                                </button>

                                <button class="btn btn-sm btn-soft-warning unavailableDatesBtn"
                                        title="Mark specific calendar dates unavailable"
                                        data-doctor_id="${raw.doctor_id}"
                                        data-doctor_name="${raw.doctor ? escapeAttr(raw.doctor.doctor_name) : ''}">

                                    <i class="ri-calendar-close-fill"></i>

                                </button>

                                <button class="btn btn-sm btn-soft-danger deleteBtn"
                                        data-id="${raw.id}">

                                    <i class="ri-delete-bin-5-fill"></i>

                                </button>

                            </div>


                        </td>

                    </tr>
                `);
            });

            scheduleDataTable =
                $('#scheduleTable').DataTable({

                    responsive: true,

                    paging: true,

                    searching: true,

                    ordering: false,

                    destroy: true,

                    lengthChange: false,

                    info: false,
                    dom: 'rtip'
                });


        },

        error: function (xhr) {

            console.log(xhr.responseText);
        }
    });
}

function formatDaySessions(sessions, day) {

    let daySessions = sessions.filter(s => s.day_of_week === day);

    if (!daySessions.length) {
        return '<span class="text-muted">OFF</span>';
    }

    return daySessions.map(function (s) {
        let range = s.is_by_appointment
            ? 'By Appointment'
            : s.start_time.substring(0, 5) + '-' + s.end_time.substring(0, 5);
        return s.session_label ? `${range} (${escapeAttr(s.session_label)})` : range;
    }).join('<br>');
}

/*
|--------------------------------------------------------------------------
| SESSION ROW TEMPLATE (add/edit modal)
|--------------------------------------------------------------------------
*/

function sessionRowTemplate(day, session) {

    session = session || {};

    let id = session.id || '';
    let label = session.session_label || '';
    let start = session.start_time ? session.start_time.substring(0, 5) : '';
    let end = session.end_time ? session.end_time.substring(0, 5) : '';
    let maxPatient = session.max_patient || '';
    let byAppointment = !!session.is_by_appointment;

    // "By Appointment" only controls how this session is DISPLAYED (staff
    // calendar, public schedule PDF) and whether the public patient booking
    // page offers it -- the real time is always required and is always used
    // for internal staff booking, so the time inputs stay visible/required
    // regardless of this checkbox.
    return `
    <div class="session-row border rounded p-2 mb-2 bg-light-subtle" data-day="${day}">
        <input type="hidden" class="session-id-field" value="${id}">
        <div class="row g-2">
            <div class="col-12">
                <input type="text" class="form-control form-control-sm session-label-field"
                       placeholder="Label (optional, e.g. Morning)" value="${escapeAttr(label)}">
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input session-by-appointment-field"
                           ${byAppointment ? 'checked' : ''}>
                    <label class="form-check-label small">
                        By Appointment (show as "By Appointment" on the calendar/public schedule
                        instead of this time; not offered on the public online booking page)
                    </label>
                </div>
            </div>
            <div class="col-6">
                <input type="time" class="form-control form-control-sm session-start-field" value="${start}" required>
            </div>
            <div class="col-6">
                <input type="time" class="form-control form-control-sm session-end-field" value="${end}" required>
            </div>
            <div class="col-8">
                <input type="number" min="1" class="form-control form-control-sm session-max-patient-field"
                       placeholder="Max Patient" value="${maxPatient}" required>
            </div>
            <div class="col-4">
                <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-session-btn" title="Remove">
                    <i class="ri-delete-bin-line"></i>
                </button>
            </div>
        </div>
    </div>
    `;
}

function refreshNoSessionsLabel(day) {

    let container = $('#sessions-' + day);
    let label = $('.no-sessions-label[data-day="' + day + '"]');

    label.toggle(container.children('.session-row').length === 0);
}

function addSessionRow(day, session) {

    $('#sessions-' + day).append(sessionRowTemplate(day, session));
    refreshNoSessionsLabel(day);
}

$(document).on('click', '.add-session-btn', function () {

    addSessionRow($(this).data('day'), null);
});

$(document).on('click', '.remove-session-btn', function () {

    let row = $(this).closest('.session-row');
    let day = row.data('day');

    row.remove();
    refreshNoSessionsLabel(day);
});

/*
|--------------------------------------------------------------------------
| EDIT
|--------------------------------------------------------------------------
*/

$(document).on(
    "click",
    ".editBtn",
    function () {

        $("#id-field")
            .val($(this).data("id"));

        $("#doctor_id-field")
            .val($(this).data("doctor_id"));
        $("#doctor_id-field")
            .prop("disabled", true);

        let specialisation =
            $("#doctor_id-field option:selected")
                .attr("data-specialisation");

        $("#specialisation-field")
            .val(specialisation || "");

        $("#status-field")
            .val($(this).data("status"));

        let sessions = $(this).data("sessions") || [];

        SCHEDULE_DAYS.forEach(function (day) {

            $('#sessions-' + day).empty();

            sessions
                .filter(s => s.day_of_week === day)
                .forEach(s => addSessionRow(day, s));

            refreshNoSessionsLabel(day);
        });

        $("#modal-title")
            .text("Edit Schedule");

        $("#showModal").modal("show");
    }
);

$(document).on(
    "click",
    ".deleteBtn",
    function () {

            let id =
                $(this).data("id");

            Swal.fire({

                title: "Are you sure?",

                text: "This record will be deleted.",

                icon: "warning",

                showCancelButton: true,

                confirmButtonColor: "#d33",

                cancelButtonColor: "#3085d6",

                confirmButtonText: "Yes, delete it!"
            })

                .then((result) => {

                    if (result.isConfirmed) {

                        $.ajax({

                            url:
                                "/doctor-schedules/delete/" + id,

                            type: "DELETE",

                            data: {

                                _token:
                                    $('meta[name="csrf-token"]')
                                        .attr('content')
                            },

                            success: function (response) {

                                Swal.fire({

                                    icon: "success",

                                    title: "Deleted",

                                    text: response.message
                                });

                                loadSchedules();
                            },

                            error: function (xhr) {

                                console.log(xhr.responseText);

                                Swal.fire({
                                    icon: "error",
                                    title: "Delete Failed",
                                    text: (xhr.responseJSON && xhr.responseJSON.message) || "Something went wrong."
                                });
                            }
                        });
                    }
                });
        }
    );


$(document).on('change', '#perPage', function () {

    scheduleDataTable
        .page.len($(this).val())
        .draw();
});

$(document).on('change', '#doctor_id-field', function () {

    let selectedOption = $(this).find('option:selected');

    let specialisation = selectedOption.attr('data-specialisation');

    $('#specialisation-field').val(specialisation || '');
});
/*
|--------------------------------------------------------------------------
| SAVE
|--------------------------------------------------------------------------
*/


$(document).on(
    "submit",
    "#scheduleForm",
    function (e) {

        e.preventDefault();

        let id = $("#id-field").val();

        let url =
            id
                ? "/doctor-schedules/update/" + id
                : "/doctor-schedules/add";

        let sessions = [];

        $('.session-row').each(function () {

            let row = $(this);
            let byAppointment = row.find('.session-by-appointment-field').is(':checked');

            sessions.push({
                id: row.find('.session-id-field').val() || null,
                day_of_week: row.data('day'),
                session_label: row.find('.session-label-field').val(),
                is_by_appointment: byAppointment ? 1 : 0,
                start_time: row.find('.session-start-field').val(),
                end_time: row.find('.session-end-field').val(),
                max_patient: row.find('.session-max-patient-field').val(),
            });
        });

        Swal.fire({
            title: "Saving....",
            text: "Please wait",
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        $.ajax({

            url: url,

            type: "POST",

            data: {

                doctor_id:
                    $("#doctor_id-field").val(),

                status:
                    $("#status-field").val(),

                sessions: sessions,

                _token:
                    $('meta[name="csrf-token"]').attr('content')
            },

            success: function (response) {

                Swal.fire({

                    icon: "success",

                    title: "Success",

                    text: response.message
                });

                $("#scheduleForm")[0].reset();
                $('.session-rows-container').empty();
                SCHEDULE_DAYS.forEach(day => refreshNoSessionsLabel(day));
                $("#doctor_id-field").prop("disabled", false);

                $("#showModal").modal("hide");

                loadSchedules();
            },

            error: function (xhr) {

                console.log(xhr.responseText);

                let message = "Something went wrong. Please try again.";

                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    message = Object.values(xhr.responseJSON.errors).flat().join("\n");
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                Swal.fire({

                    icon: "error",

                    title: "Save Failed",

                    text: message
                });
            }
        });
    }
);

/*
|--------------------------------------------------------------------------
| ADD BUTTON RESET
|--------------------------------------------------------------------------
*/

$("#addDoctorBtn").click(function () {

    $("#scheduleForm")[0].reset();

    $("#id-field").val('');

    $('.session-rows-container').empty();
    SCHEDULE_DAYS.forEach(day => refreshNoSessionsLabel(day));

    $("#doctor_id-field").prop("disabled", false);

    $("#modal-title")
        .text("Add New Schedule");
});
$(document).ready(function () {

    loadSchedules();
    $('#doctor_id-field').trigger('change');

    SCHEDULE_DAYS.forEach(day => refreshNoSessionsLabel(day));
});
$(document).on(
    "keyup",
    "#customSearch",
    function () {

        if (scheduleDataTable) {

            scheduleDataTable
                .search($(this).val())
                .draw();
        }
    }
);

/*
|--------------------------------------------------------------------------
| UNAVAILABLE DATES (blackout exceptions on top of the recurring schedule)
|--------------------------------------------------------------------------
*/

let exceptionFlatpickr = null;
let currentExceptionDoctorId = null;

function loadExceptions() {

    if (!currentExceptionDoctorId) return;

    $('#exceptionList').html('<div class="text-muted small p-2">Loading...</div>');

    $.ajax({

        url: '/doctor-schedule-exceptions',

        type: 'GET',

        data: { doctor_id: currentExceptionDoctorId },

        success: function (response) {

            let rows = response.data || [];

            if (!rows.length) {
                $('#exceptionList').html(
                    '<div class="text-muted small p-2">No upcoming unavailable dates for this doctor.</div>'
                );
                return;
            }

            let html = '<ul class="list-group">';

            rows.forEach(function (row) {

                let bookedBadge = row.booked_count > 0
                    ? `<span class="badge bg-danger ms-2">${row.booked_count} booked</span>`
                    : '';

                html += `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fw-semibold">${row.exception_date}</span>
                            ${row.reason ? `<span class="text-muted small ms-2">${escapeAttr(row.reason)}</span>` : ''}
                            ${bookedBadge}
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-success reEnableDateBtn" data-id="${row.id}">
                            <i class="ri-arrow-go-back-line align-middle"></i> Re-enable
                        </button>
                    </li>
                `;
            });

            html += '</ul>';

            $('#exceptionList').html(html);
        },

        error: function () {
            $('#exceptionList').html('<div class="text-danger small p-2">Failed to load.</div>');
        }
    });
}

$(document).on('click', '.unavailableDatesBtn', function () {

    currentExceptionDoctorId = $(this).data('doctor_id');

    $('#exceptionDoctorName').text($(this).data('doctor_name') || '');
    $('#exceptionDates').val('');
    $('#exceptionReason').val('');

    if (!exceptionFlatpickr) {

        exceptionFlatpickr = flatpickr('#exceptionDates', {
            mode: 'multiple',
            dateFormat: 'd-m-Y',
            minDate: 'today',
            allowInput: true
        });

    } else {

        exceptionFlatpickr.clear();
    }

    loadExceptions();

    bootstrap.Modal.getOrCreateInstance(
        document.getElementById('exceptionModal')
    ).show();
});

$(document).on('submit', '#exceptionForm', function (e) {

    e.preventDefault();

    let selectedDates = exceptionFlatpickr ? exceptionFlatpickr.selectedDates : [];

    if (!selectedDates.length) {

        Swal.fire({
            icon: 'warning',
            title: 'Pick at least one date',
            text: 'Choose one or more dates to mark unavailable.'
        });

        return;
    }

    let dates = selectedDates.map(function (d) {
        let yyyy = d.getFullYear();
        let mm = String(d.getMonth() + 1).padStart(2, '0');
        let dd = String(d.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    });

    Swal.fire({
        title: "Saving....",
        text: "Please wait",
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: function () {
            Swal.showLoading();
        }
    });

    $.ajax({

        url: '/doctor-schedule-exceptions/add',

        type: 'POST',

        data: {
            doctor_id: currentExceptionDoctorId,
            dates: dates,
            reason: $('#exceptionReason').val(),
            _token: $('meta[name="csrf-token"]').attr('content')
        },

        success: function (response) {

            Swal.fire({
                icon: response.status ? 'success' : 'error',
                title: response.status ? 'Saved' : 'Error',
                text: response.message
            });

            $('#exceptionDates').val('');
            $('#exceptionReason').val('');
            if (exceptionFlatpickr) exceptionFlatpickr.clear();

            loadExceptions();
        },

        error: function (xhr) {

            let message = "Something went wrong. Please try again.";

            if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                message = Object.values(xhr.responseJSON.errors).flat().join("\n");
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }

            Swal.fire({
                icon: "error",
                title: "Save Failed",
                text: message
            });
        }
    });
});

$(document).on('click', '.reEnableDateBtn', function () {

    let id = $(this).data('id');

    Swal.fire({

        title: "Re-enable this date?",

        text: "Appointments will be bookable on this date again.",

        icon: "warning",

        showCancelButton: true,

        confirmButtonColor: "#0ab39c",

        cancelButtonColor: "#f06548",

        confirmButtonText: "Yes, re-enable"

    }).then(function (result) {

        if (!result.isConfirmed) return;

        $.ajax({

            url: '/doctor-schedule-exceptions/delete/' + id,

            type: 'DELETE',

            data: {
                _token: $('meta[name="csrf-token"]').attr('content')
            },

            success: function (response) {

                Swal.fire({
                    icon: response.status ? 'success' : 'error',
                    title: response.status ? 'Re-enabled' : 'Error',
                    text: response.message
                });

                loadExceptions();
            },

            error: function () {

                Swal.fire({
                    icon: "error",
                    title: "Failed",
                    text: "Unable to re-enable this date."
                });
            }
        });
    });
});
