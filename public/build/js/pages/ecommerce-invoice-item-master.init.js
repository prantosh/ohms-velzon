"use strict";

/*--------------------------------------------------------------
 Global Variables
--------------------------------------------------------------*/

let table = null;

let modal = null;

let editId = 0;

const routeList = window.invoiceItemRoutes.list;

const routeStore = window.invoiceItemRoutes.store;

const routeIndex = window.invoiceItemRoutes.index;

const token = window.invoiceItemRoutes.token;


/*--------------------------------------------------------------
 Document Ready
--------------------------------------------------------------*/

$(function () {

    modal = new bootstrap.Modal(
        document.getElementById('itemModal')
    );

    initialiseDataTable();

    initialiseEvents();

});


/*--------------------------------------------------------------
 DataTable
--------------------------------------------------------------*/

function initialiseDataTable() {

    table = $('#invoiceItemTable').DataTable({

        processing: true,

        serverSide: true,

        responsive: true,

        autoWidth: false,

        pageLength: 25,

        order: [[1, 'asc']],

        ajax: {

            url: routeList,

            type: 'POST',

            data: function (d) {

                d._token = token;

            }

        },

        columns: [

            {
                data: 'DT_RowIndex',
                searchable: false,
                orderable: false
            },

            {
                data: 'item_code'
            },

            {
                data: 'item_name'
            },

            {
                data: 'item_type'
            },

            {
                data: 'description',
                defaultContent: ''
            },

            {
                data: 'status',
                orderable: false,
                searchable: false
            },

            {
                data: 'test_parameter_required',
                orderable: false,
                searchable: false
            },

            {
                data: 'created_at'
            },

            {
                data: 'updated_at'
            },

            {
                data: 'action',
                searchable: false,
                orderable: false
            }

        ]

    });

}


/*--------------------------------------------------------------
 Events
--------------------------------------------------------------*/

function initialiseEvents() {

    $('#btnAdd').on(
        'click',
        addNew
    );

    $('#btnReload').on(
        'click',
        function () {

            table.ajax.reload(null, false);

        }
    );

    $('#searchText').on(
        'keyup',
        function () {

            table.search(
                $(this).val()
            ).draw();

        }
    );

    $('#btnSave').on(
        'click',
        saveRecord
    );

    $(document).on(
        'click',
        '.edit-item',
        editRecord
    );

    $(document).on(
        'click',
        '.delete-item',
        deleteRecord
    );

}


/*--------------------------------------------------------------
 Add New
--------------------------------------------------------------*/

function addNew() {

    editId = 0;

    clearForm();

    $('#item_code').prop('disabled', false);

    $('#modalTitle').text(
        'Add Invoice Item'
    );

    modal.show();

}


/*--------------------------------------------------------------
 Edit
--------------------------------------------------------------*/

function editRecord() {

    clearErrors();

    editId = $(this).data('id');
    $('#item_code').prop('disabled', true);

    $.ajax({

        url: routeIndex + '/' + editId,

        type: 'GET',

        success: function (response) {

            $('#modalTitle').text(
                'Edit Invoice Item'
            );

            $('#record_id').val(
                response.id
            );

            $('#item_code').val(
                response.item_code
            );

            $('#item_name').val(
                response.item_name
            );

            let itemType = (response.item_type || '').trim();

            $("#item_type option").each(function () {

                if ($(this).val().toUpperCase() === itemType.toUpperCase()) {

                    $('#item_type').val($(this).val());

                    return false;

                }

            });

            $('#description').val(
                response.description
            );

            $('#item_master_status').val(
                response.status ? 1 : 0
            );

            $('#test_parameter_required').val(
                response.test_parameter_required === 'YES' ? 'YES' : 'NO'
            );

            modal.show();

        }

    });

}


/*--------------------------------------------------------------
 Save / Update
--------------------------------------------------------------*/

function saveRecord() {

    clearErrors();

    let url = routeStore;

    let type = 'POST';

    if (editId > 0) {

        url = routeIndex + '/' + editId;

        type = 'PUT';

    }

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

        url: url,

        type: type,

        dataType: 'json',

        headers: {

            'X-CSRF-TOKEN': token,

            'Accept': 'application/json'

        },

        data: {

            _token: token,

            item_code: $('#item_code').val(),

            item_name: $('#item_name').val(),

            item_type: $('#item_type').val(),

            description: $('#description').val(),

            status: $('#item_master_status').val(),

            test_parameter_required: $('#test_parameter_required').val()

        },

        success: function (response) {

            Swal.close();

            if (!response.status) {

                showValidationErrors(
                    response.errors
                );

                return;

            }

            modal.hide();

            Swal.fire({

                icon: 'success',

                title: 'Success',

                text: response.message,

                timer: 1500,

                showConfirmButton: false

            });

            table.ajax.reload(
                null,
                false
            );

        },

        error: function (xhr) {

            console.log(xhr.responseText);

            Swal.fire(

                'Error',

                xhr.responseJSON?.message ??
                'Unable to save record.',

                'error'

            );

        }

    });

}
/*--------------------------------------------------------------
 Delete
--------------------------------------------------------------*/

function deleteRecord() {

    let id = $(this).data('id');

    Swal.fire({

        title: 'Delete Record?',

        text: 'This record will be permanently deleted.',

        icon: 'warning',

        showCancelButton: true,

        confirmButtonColor: '#d33',

        cancelButtonColor: '#3085d6',

        confirmButtonText: 'Delete'

    }).then(function (result) {

        if (!result.isConfirmed) {

            return;

        }

        $.ajax({

            url: routeIndex + '/' + id,

            type: 'DELETE',

            data: {

                _token: token

            },

            success: function (response) {

                if (!response.status) {

                    Swal.fire(

                        'Error',

                        response.message,

                        'error'

                    );

                    return;

                }

                Swal.fire({

                    icon: 'success',

                    title: 'Deleted',

                    text: response.message,

                    timer: 1200,

                    showConfirmButton: false

                });

                table.ajax.reload(null, false);

            },

            error: function () {

                Swal.fire(

                    'Error',

                    'Unable to delete record.',

                    'error'

                );

            }

        });

    });

}


/*--------------------------------------------------------------
 Excel Export
--------------------------------------------------------------*/

$('#btnExcel').on(

    'click',

    function () {

        window.location =
            routeIndex + '/excel';

    }

);


/*--------------------------------------------------------------
 Uppercase Item Code
--------------------------------------------------------------*/

$('#item_code').on(

    'keyup',

    function () {

        this.value = this.value.toUpperCase();

    }

);


/*--------------------------------------------------------------
 Enter Key Navigation
--------------------------------------------------------------*/

$('#item_code').keypress(function (e) {

    if (e.which == 13) {

        $('#item_name').focus();

    }

});

$('#item_name').keypress(function (e) {

    if (e.which == 13) {

        $('#item_type').focus();

    }

});

$('#item_type').keypress(function (e) {

    if (e.which == 13) {

        $('#description').focus();

    }

});


/*--------------------------------------------------------------
 Clear Form
--------------------------------------------------------------*/

function clearForm() {

    $('#record_id').val('');

    $('#item_code').val('');

    $('#item_name').val('');

    $('#item_type').val('');

    $('#description').val('');

    $('#item_master_status').val(1);

    $('#test_parameter_required').val('NO');

    clearErrors();

}


/*--------------------------------------------------------------
 Clear Validation
--------------------------------------------------------------*/

function clearErrors() {

    $('.is-invalid').removeClass('is-invalid');

    $('.invalid-feedback').html('');

}


/*--------------------------------------------------------------
 Validation Messages
--------------------------------------------------------------*/

function showValidationErrors(errors) {

    $.each(

        errors,

        function (key, value) {

            $('#' + key)

                .addClass('is-invalid');

            $('#error_' + key)

                .html(value[0]);

        }

    );

}


/*--------------------------------------------------------------
 Modal Closed
--------------------------------------------------------------*/

$('#itemModal').on(

    'hidden.bs.modal',

    function () {

        clearForm();

        $('#item_code').prop('disabled', false);

        editId = 0;

    }

);


/*--------------------------------------------------------------
 Auto Focus
--------------------------------------------------------------*/

$('#itemModal').on(

    'shown.bs.modal',

    function () {

        $('#item_code').focus();

    }

);


/*--------------------------------------------------------------
 Refresh Grid
--------------------------------------------------------------*/

function reloadGrid() {

    table.ajax.reload(

        null,

        false

    );

}
