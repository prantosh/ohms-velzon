"use strict";

let table = null;
let modal = null;
let editId = 0;

const routeList = window.invoiceItemDetailRoutes.list;
const routeStore = window.invoiceItemDetailRoutes.store;
const routeIndex = window.invoiceItemDetailRoutes.index;
const routeMaster = window.invoiceItemDetailRoutes.master;
const routeComponents = window.invoiceItemDetailRoutes.components;
const routeExcel = window.invoiceItemDetailRoutes.excel;
const token = window.invoiceItemDetailRoutes.token;

/*
|--------------------------------------------------------------------------
| INSERT ± AT CURSOR (range fields)
|--------------------------------------------------------------------------
*/

document.addEventListener('click', function (e) {

    let btn = e.target.closest('.insert-plusminus');

    if (!btn) return;

    let input = btn.closest('.input-group')?.querySelector('input');

    if (!input) return;

    let start = input.selectionStart ?? input.value.length;
    let end = input.selectionEnd ?? input.value.length;

    input.value = input.value.slice(0, start) + '±' + input.value.slice(end);

    input.focus();
    input.setSelectionRange(start + 1, start + 1);
});

/*
|--------------------------------------------------------------------------
| DETAIL RANGE QUICK-PICK (range fields) -- lets staff pick a saved,
| highly-descriptive range instead of typing it out each time. Selecting
| an option replaces the sibling input's full value.
|--------------------------------------------------------------------------
*/

let detailRangeOptions = [];

function loadDetailRangeOptions() {

    $.get('/detail-range-master/options', function (response) {

        detailRangeOptions = response.status ? response.data : [];

        $('.range-quick-pick').each(function () {
            populateRangeQuickPick(this);
        });
    });
}

function populateRangeQuickPick(select) {

    select.innerHTML = '';
    select.appendChild(new Option('Pick...', ''));

    detailRangeOptions.forEach(function (o) {

        let label = o.name.length > 60 ? o.name.slice(0, 60) + '…' : o.name;

        select.appendChild(new Option(label, o.name));
    });
}

document.addEventListener('change', function (e) {

    let select = e.target.closest('.range-quick-pick');

    if (!select || !select.value) return;

    let input = select.closest('.input-group')?.querySelector('input');

    if (!input) return;

    input.value = select.value;

    select.value = '';
});

$(function () {


    $('#item_code').select2({

        dropdownParent: $('#itemDetailModal'),

        width: '100%',

        placeholder: 'Select Item Code',

        allowClear: true

    });

    $('#components').select2({

        dropdownParent: $('#itemDetailModal'),

        width: '100%',

        placeholder: 'Select Component Tests'

    });

    modal = new bootstrap.Modal(
        document.getElementById('itemDetailModal')
    );

    initialiseDataTable();

    initialiseEvents();

    loadDetailRangeOptions();

});

function initialiseDataTable() {

    table = $('#invoiceItemDetailTable').DataTable({

        processing: true,

        serverSide: true,

        responsive: true,

        pageLength: 25,

        ajax: {

            url: routeList,

            type: 'POST',

            data: function (d) {

                d._token = token;

                d.packages_only = $('#packagesOnlyFilter').is(':checked') ? 1 : 0;

            }

        },

        columns: [

            {
                data: 'DT_RowIndex',
                name: 'DT_RowIndex',
                searchable: false,
                orderable: false
            },

            {
                data: 'item_name',
                name: 'invoice_item_masters.item_name'
            },

            {
                data: 'item_code_sub',
                name: 'invoice_item_details.item_code_sub'
            },

            {
                data: 'item_description_sub',
                name: 'invoice_item_details.item_description_sub'
            },

            {
                data: 'rate',
                name: 'invoice_item_details.rate',
                className: 'text-end'
            },

            {
                data: 'discount_percent',
                name: 'invoice_item_details.discount_percent',
                className: 'text-end'
            },

            {
                data: 'member_discount',
                name: 'invoice_item_details.member_discount',
                className: 'text-end'
            },

            {
                data: 'discount_other_lab',
                name: 'invoice_item_details.discount_other_lab',
                className: 'text-end'
            },

            {
                data: 'discount_member_other_lab',
                name: 'invoice_item_details.discount_member_other_lab',
                className: 'text-end'
            },

            {
                data: 'test_group_name',
                name: 'test_group_masters.test_group_name'
            },

            {
                data: 'uom',
                name: 'invoice_item_details.uom',
                className: 'text-center'
            },

            {
                data: 'range_male',
                name: 'invoice_item_details.range_male'
            },

            {
                data: 'range_female',
                name: 'invoice_item_details.range_female'
            },

            {
                data: 'range_common',
                name: 'invoice_item_details.range_common'
            },

            {
                data: 'method',
                name: 'invoice_item_details.method'
            },

            {
                data: 'report_days',
                name: 'invoice_item_details.report_days',
                className: 'text-center'
            },

            {
                data: 'is_package_label',
                name: 'invoice_item_details.is_package',
                searchable: false,
                className: 'text-center'
            },

            {
                data: 'status',
                name: 'invoice_item_details.status',
                searchable: false
            },

            {
                data: 'action',
                searchable: false,
                orderable: false
            }

        ],
        columnDefs: [
            {
                targets: '_all',
                orderable: false
            }
        ]

    });

}
function clearForm() {

    // Reset form fields
    $('#record_id').val('');

    $('#item_code').prop('disabled', false)
        .val(null)
        .trigger('change');

    $('#item_name').val('');

    $('#item_type').val('');

    $('#item_code_sub').val('');

    $('#item_description_sub').val('');

    $('#is_package').prop('checked', false);

    $('#componentsWrap').hide();

    $('#testDetailsWrap').show();

    $('#components').empty().trigger('change');

    $('#rate').val('');

    $('#standard_discount').val('0');

    $('#member_discount').val('0');

    $('#other_lab_discount').val('0');

    $('#member_other_lab_discount').val('0');

    $('#test_group_code').val('');

    $('#uom').val('');

    $('#male_range').val('');

    $('#female_range').val('');

    $('#common_range').val('');

    $('#method').val('');

    $('#report_days').val('');

    $('#item_status').val('1');

    // Remove validation
    $('.is-invalid').removeClass('is-invalid');

    $('.invalid-feedback').html('');

    editId = 0;
}
function addNew() {

    clearForm();

    $('#modalTitle').text('Add Invoice Item Detail');

    modal.show();

}

function loadComponentCandidates(itemCode, excludeId, selectedIds) {

    if (!itemCode) {
        $('#components').empty().trigger('change');
        return;
    }

    let url = routeComponents + '/' + itemCode + (excludeId ? '/' + excludeId : '');

    $.get(url, function (response) {

        if (!response.status) {
            return;
        }

        let selected = selectedIds || [];

        $('#components').empty();

        response.items.forEach(function (item) {

            let option = new Option(
                item.item_code_sub + ' - ' + item.item_description_sub,
                item.id,
                false,
                selected.indexOf(item.id) !== -1
            );

            $('#components').append(option);

        });

        $('#components').trigger('change');

    });

}

$('#is_package').change(function () {

    if ($(this).is(':checked')) {

        $('#componentsWrap').show();

        $('#testDetailsWrap').hide();

        loadComponentCandidates($('#item_code').val(), editId, []);

    } else {

        $('#componentsWrap').hide();

        $('#testDetailsWrap').show();

        $('#components').empty().trigger('change');

    }

});

function editRecord() {

    editId = $(this).data('id');

    $.get(

        routeIndex + '/' + editId,

        function (response) {

            $('#item_code').val(response.item_code);
            $('#item_code').trigger('change.select2');

            $('#item_name').val(response.item_name);

            $('#item_type').val(response.item_type);

            $('#item_code_sub').val(response.item_code_sub);

            $('#item_description_sub').val(response.item_description_sub);

            $('#is_package').prop('checked', !!(response.is_package * 1));

            if ($('#is_package').is(':checked')) {

                $('#componentsWrap').show();

                $('#testDetailsWrap').hide();

                loadComponentCandidates(response.item_code, editId, response.component_ids || []);

            } else {

                $('#componentsWrap').hide();

                $('#testDetailsWrap').show();

            }

            $('#rate').val(response.rate);

            $('#standard_discount').val(response.discount_percent);

            $('#member_discount').val(response.member_discount);

            $('#other_lab_discount').val(response.discount_other_lab);

            $('#member_other_lab_discount').val(response.discount_member_other_lab);

            $('#test_group_code').val(response.test_group_code);

            $('#uom').val(response.uom);

            $('#male_range').val(response.range_male);

            $('#female_range').val(response.range_female);

            $('#common_range').val(response.range_common);
            $('#method').val(response.method);

            $('#report_days').val(response.report_days);

            $('#item_status').val(response.status);

            $('#item_code').prop('disabled', true);
            $('#modalTitle').text('Edit Invoice Item Detail');

            modal.show();

        }

    );

}

$('#item_code').change(function () {

    let code = $(this).val();

    if (code == '') {

        $('#item_name').val('');

        $('#item_type').val('');

        $('#item_code_sub').val('');

        $('#components').empty().trigger('change');

        return;

    }

    $.ajax({

        url: routeMaster + '/' + code,

        type: 'GET',

        success: function (response) {

            if (!response.status) {

                return;

            }

            $('#item_name').val(response.item_name);

            $('#item_type').val(response.item_type);

            $('#item_code_sub').val(response.item_code_sub);

            if ($('#is_package').is(':checked')) {
                loadComponentCandidates(code, editId, []);
            }

        }

    });

});

function saveRecord() {

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

        headers: {

            'X-CSRF-TOKEN': token,

            'Accept': 'application/json'

        },

        data: {

            item_code: $('#item_code').val(),

            item_code_sub: $('#item_code_sub').val(),

            item_description_sub: $('#item_description_sub').val(),

            is_package: $('#is_package').is(':checked') ? 1 : 0,

            components: $('#is_package').is(':checked') ? ($('#components').val() || []) : [],

            rate: $('#rate').val(),

            standard_discount: $('#standard_discount').val(),

            member_discount: $('#member_discount').val(),

            other_lab_discount: $('#other_lab_discount').val(),

            member_other_lab_discount: $('#member_other_lab_discount').val(),

            test_group_code: $('#test_group_code').val(),

            uom: $('#uom').val(),

            male_range: $('#male_range').val(),

            female_range: $('#female_range').val(),

            common_range: $('#common_range').val(),

            method: $('#method').val(),

            report_days: $('#report_days').val(),

            status: $('#item_status').val()

        },

        success: function (response) {

            Swal.close();

            if (!response.status) {

                showValidationErrors(response.errors);

                return;

            }

            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').html('');

            modal.hide();

            Swal.fire({

                icon: 'success',

                title: 'Saved',

                timer: 1200,

                showConfirmButton: false

            });

            table.ajax.reload(null, false);

        },

        error: function (xhr) {

            Swal.close();

            let message = 'Unable to save record.';

            if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {

                showValidationErrors(xhr.responseJSON.errors);

                return;
            }

            Swal.fire('Error', message, 'error');

        }

    });

}

function showValidationErrors(errors) {

    $('.is-invalid').removeClass('is-invalid');
    $('.invalid-feedback').html('');

    $.each(errors, function (key, value) {

        $('#' + key).addClass('is-invalid');

        $('#error_' + key).html(value[0]);

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

$('#btnExcel').on('click', function () {

    window.location.href = routeExcel;

});

$('#searchText').keyup(function () {

    table.search($(this).val()).draw();

});

$('#btnReload').click(function () {

    table.ajax.reload(null, false);

});

$('#packagesOnlyFilter').change(function () {

    table.ajax.reload(null, false);

});

$('#itemDetailModal').on('hidden.bs.modal', function () {

    clearForm();

    $('#item_code')
        .prop('disabled', false)
        .val(null)
        .trigger('change.select2');

    $('#item_name').val('');

    $('#item_type').val('');

    editId = 0;

});

$('#item_description_sub').on('input', function () {
    $(this).val($(this).val().toUpperCase());
});

$('#uom').on('input', function () {
    $(this).val($(this).val().toUpperCase());
});

$('#method').on('input', function () {
    $(this).val($(this).val().toUpperCase());
});
$('.numeric').on('input', function () {

    let value = parseFloat($(this).val());

    if (value < 0) {

        $(this).val(0);

    }

});
$('.numeric').blur(function () {

    let value = parseFloat($(this).val());

    if (isNaN(value)) {

        value = 0;

    }

    $(this).val(value.toFixed(2));

});

function initialiseEvents() {

    $('#btnAdd').off('click').on('click', addNew);

    $('#btnSave').off('click').on('click', saveRecord);

    $(document)
        .off('click', '.edit-item')
        .on('click', '.edit-item', editRecord);

    $(document)
        .off('click', '.delete-item')
        .on('click', '.delete-item', deleteRecord);

}
