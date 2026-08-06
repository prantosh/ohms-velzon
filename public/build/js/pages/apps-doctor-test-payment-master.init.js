
"use strict";

/*
|--------------------------------------------------------------------------
| PAGE LOAD
|--------------------------------------------------------------------------
*/

$(document).ready(function () {
    loadPayments();
});

/*
|--------------------------------------------------------------------------
| LOAD DATATABLE
|--------------------------------------------------------------------------
*/

function loadPayments() {

    $.ajax({

        url: '/doctor-test-payment-master/list',

        type: 'GET',

        success: function (response) {

            if ($.fn.DataTable.isDataTable('#paymentTable')) {
                $('#paymentTable').DataTable().destroy();
            }

            $('#paymentTable tbody').empty();

            $.each(response.data, function (i, row) {

                let statusBadge = row.status === 'A'
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-danger">Inactive</span>';

                let doctorName = row.doctor_name || '';

                $('#paymentTable tbody').append(`
                    <tr>
                        <td>${row.id}</td>
                        <td>${doctorName}</td>
                        <td>${row.item_name ?? ''}</td>
                        <td>${row.item_description_sub ?? ''}</td>
                        <td>${row.payment_type}</td>
                        <td>${row.payment_value}</td>
                        <td>${row.effective_from_display ?? ''}</td>
                        <td>${statusBadge}</td>
                        <td>
                            <button
                                class="btn btn-warning btn-sm editBtn"
                                data-id="${row.id}">
                                <i class="ri-edit-line"></i>
                            </button>

                            <button
                                class="btn btn-danger btn-sm deleteBtn"
                                data-id="${row.id}">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });

            $('#paymentTable').DataTable({
                responsive: true,
                destroy: true
            });
        }
    });
}

/*
|--------------------------------------------------------------------------
| ADD NEW
|--------------------------------------------------------------------------
*/

$(document)
    .off('click', '#addBtn')
    .on('click', '#addBtn', function () {

    $('#paymentForm')[0].reset();

    $('#id').val('');

    $('#specialisation').val('');

    resetDoctorCheckboxList();

    $('#item_code').val('');

    $('#item_code_sub').html(
        '<option value="">Select Test</option>'
    );

    window.editTestCode = null;

        $('#paymentModal').modal('show');

        $('#specialisationWrap, #doctorCheckboxWrap').removeClass('d-none');
        $('#editDoctorWrap').addClass('d-none');

        $('#item_code').css('pointer-events', '');

        $('#item_code_sub').css('pointer-events', '');
});

/*
|--------------------------------------------------------------------------
| DOCTOR CHECKBOX LIST
|--------------------------------------------------------------------------
*/

function resetDoctorCheckboxList() {

    $('#doctorCheckboxList').html(
        '<span class="text-muted">Select a category to list doctors.</span>'
    );

    $('#doctorStaff').prop('checked', false);

    $('#staffDoctorWrap').addClass('d-none');
}

/*
|--------------------------------------------------------------------------
| STAFF CHECKBOX (extra doctor option for diagnostic/LAB test categories)
|--------------------------------------------------------------------------
*/

function updateStaffCheckboxVisibility() {

    let staffId = $('#doctorStaff').val();

    if (!staffId || !$('#editDoctorWrap').hasClass('d-none')) {

        if (staffId) {
            $('#doctorStaff').prop('checked', false);
            $('#staffDoctorWrap').addClass('d-none');
        }

        return;
    }

    let selectedOption = $('#item_code').find('option:selected');
    let itemType = selectedOption.data('item-type');

    let alreadyListed = $('#doctorCheckboxList').find('input[value="' + staffId + '"]').length > 0;

    if (itemType === 'LAB' && !alreadyListed) {

        $('#staffDoctorWrap').removeClass('d-none');

    } else {

        $('#doctorStaff').prop('checked', false);

        $('#staffDoctorWrap').addClass('d-none');
    }
}

$(document).on('change', '#specialisation', function () {

    let specialisation = $(this).val();

    if (!specialisation) {

        resetDoctorCheckboxList();

        return;
    }

    $('#doctorCheckboxList').html('<span class="text-muted">Loading...</span>');

    $.ajax({

        url: '/doctor-test-payment-master/doctors-by-specialisation',

        type: 'GET',

        data: { specialisation: specialisation },

        success: function (doctors) {

            if (!doctors.length) {

                $('#doctorCheckboxList').html(
                    '<span class="text-muted">No doctors found for this category.</span>'
                );

            } else {

                let html = '';

                $.each(doctors, function (i, doctor) {

                    html += `
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="doctor_${doctor.id}"
                                name="doctor_ids[]"
                                value="${doctor.id}">
                            <label class="form-check-label" for="doctor_${doctor.id}">
                                ${doctor.doctor_name}
                            </label>
                        </div>
                    `;
                });

                $('#doctorCheckboxList').html(html);
            }

            updateStaffCheckboxVisibility();
        }
    });
});

/*
|--------------------------------------------------------------------------
| LOAD TESTS
|--------------------------------------------------------------------------
*/

$(document).on('change', '#item_code', function () {

    let itemCode = $(this).val();

    updateStaffCheckboxVisibility();

    $('#item_code_sub').html(
        '<option value="">Loading...</option>'
    );

    if (!itemCode) {

        $('#item_code_sub').html(
            '<option value="">Select Test</option>'
        );

        return;
    }

    $.ajax({

        url: '/doctor-test-payment-master/tests/' + itemCode,

        type: 'GET',

        success: function (response) {

            let dropdown = $('#item_code_sub');

            dropdown.html(
                '<option value="">Select Test</option>'
            );

            $.each(response, function (i, row) {

                dropdown.append(`
                    <option
                        value="${row.item_code_sub}"
                        data-description="${row.item_description_sub}">
                        ${row.item_description_sub}
                    </option>
                `);
            });

            if (window.editTestCode) {

                dropdown.val(window.editTestCode);

                window.editTestCode = null;
            }
        }
    });
});

/*
|--------------------------------------------------------------------------
| SAVE
|--------------------------------------------------------------------------
*/

$(document)
    .off('submit', '#paymentForm')
    .on('submit', '#paymentForm', function (e) {

    e.preventDefault();

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

        url: '/doctor-test-payment-master/store',

        type: 'POST',

        data: $(this).serialize(),

        success: function (response) {

            console.log(response);

            if (response.status) {

                Swal.fire({

                    icon: 'success',

                    title: 'Success',

                    text: response.message

                });

                $('#paymentModal').modal('hide');

                loadPayments();
            }
            else {

                Swal.fire({

                    icon: 'warning',

                    title: 'Warning',

                    text: response.message

                });
            }
        },

        error: function (xhr) {

            let msg = 'Validation failed';

            if (
                xhr.responseJSON &&
                xhr.responseJSON.message
            ) {
                msg = xhr.responseJSON.message;
            }

            Swal.fire({

                icon: 'error',

                title: 'Error',

                text: msg

            });
        }
    });
});

/*
|--------------------------------------------------------------------------
| EDIT
|--------------------------------------------------------------------------
*/

$(document)
    .off('click', '.editBtn')
    .on('click', '.editBtn', function () {

    let id = $(this).data('id');

    let doctorName = $(this).closest('tr').find('td').eq(1).text().trim();

    $.ajax({

        url: '/doctor-test-payment-master/show/' + id,

        type: 'GET',

        success: function (response) {

            let row = response.data;

            $('#id').val(row.id);

            resetDoctorCheckboxList();

            $('#specialisation').val('');

            $('#specialisationWrap, #doctorCheckboxWrap').addClass('d-none');

            $('#editDoctorDisplay').val(doctorName);

            $('#editDoctorWrap').removeClass('d-none');

            $('#item_code').val(row.item_code);

            window.editTestCode = row.item_code_sub;

            $('#item_code').trigger('change');

            $('#payment_type').val(row.payment_type);

            $('#payment_value').val(row.payment_value);




            $('#payment_status').val(row.status);

            /*
            |--------------------------------------------------------------------------
            | LOCK FIELDS IN EDIT MODE
            |--------------------------------------------------------------------------
            */

            $('#item_code').css('pointer-events', 'none');

            $('#item_code_sub').css('pointer-events', 'none');

            $('#paymentModal').modal('show');

        }
    });
});

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/

$(document)
    .off('click', '.deleteBtn')
    .on('click', '.deleteBtn', function () {

    let id = $(this).data('id');

    Swal.fire({

        title: 'Delete Payment Rule?',

        icon: 'warning',

        showCancelButton: true,

        confirmButtonText: 'Yes'

    }).then((result) => {

        if (result.isConfirmed) {

            $.ajax({

                url: '/doctor-test-payment-master/delete/' + id,

                type: 'DELETE',

                data: {

                    _token: $('meta[name="csrf-token"]').attr('content')
                },

                success: function (response) {

                    if (response.status) {

                        Swal.fire({

                            icon: 'success',

                            title: 'Deleted',

                            text: response.message

                        });

                        loadPayments();

                    } else {

                        Swal.fire({

                            icon: 'error',

                            title: 'Error',

                            text: response.message

                        });
                    }
                },

                error: function (xhr) {

                    Swal.fire({

                        icon: 'error',

                        title: 'Error',

                        text: xhr.responseJSON?.message || 'Delete failed'

                    });
                }
            });
        }
    });
});
