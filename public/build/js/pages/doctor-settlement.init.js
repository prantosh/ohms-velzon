"use strict";
let previewCanvas = null;
/*--------------------------------------------------------------
Doctor Settlement
--------------------------------------------------------------*/

let settlementTable = null;

let settlementItems = [];

let lastSettlementId = null;

let selectedPayables = [];

let grossAmount = 0;

let deductionAmount = 0;

let netAmount = 0;

let outstandingAmount = 0;


const routePreviewPdf =
    $('#settlementTable').data('pdf-url');

const routePreviewExcel =
    $('#settlementTable').data('excel-url');
const csrfToken =
    $('meta[name="csrf-token"]').attr('content');

const routeDoctorOutstanding =
    $('#settlementTable').data('outstanding-url');

const routePreviewSettlement =
    $('#settlementTable').data('preview-url');

const routeSaveSettlement =
    $('#settlementTable').data('save-url');

const routeSettlementStatus =
    $('#settlementTable').data('settlement-status-url');

$(document).ready(function () {

    initialiseSelect2();

    initialisePaymentMode();

    initialiseButtons();

    initialiseDoctorChange();

    initialiseDeduction();

    initialiseDataTable();

    lockSettlementDate();

});
function initialiseSelect2() {

    $('#doctor_id').select2({

        placeholder: "Select Doctor",

        allowClear: true,

        width: '100%'

    });

    $('#user_id').select2({

        placeholder: "Select User",

        allowClear: true,

        width: '100%'

    });

}

function lockSettlementDate() {

    let fp = document.getElementById('settlement_date')._flatpickr;

    if (fp) {
        fp.set('clickOpens', false);
        if (fp.altInput) fp.altInput.readOnly = true;
    }
}
function initialisePaymentMode() {

    $('#payment_mode').on('change', function () {

        let mode = $(this).val();

        $('#bank_div').hide();

        $('#cheque_div').hide();

        $('#utr_div').hide();

        if (mode === 'BANK') {

            $('#bank_div').show();

            $('#utr_div').show();

        }

        if (mode === 'CHEQUE') {

            $('#bank_div').show();

            $('#cheque_div').show();

        }

        if (mode === 'UPI') {

            $('#utr_div').show();

        }

    });

    $('#payment_mode').trigger('change');

}
function initialiseButtons() {

    $('#btnSettlementPdf').on('click', openSettlementPdf);

    $('#btnSelectAll').on('click', selectAllRows);

    $('#btnUnselectAll').on('click', unselectAllRows);

    $('#btnPreview').on('click', loadPreview);

    $('#btnConfirmSettlement').on('click', saveSettlement);

    $('#btnClear').on('click', clearScreen);

    $('#btnRegister').on('click', showSettlementStatus);

    // Preview Offcanvas Buttons


    $(document).on('click', '#btnFinalSave', saveSettlement);

}
/*--------------------------------------------------------------
Settlement Status (Register button)
--------------------------------------------------------------*/

function showSettlementStatus() {

    let userId = $('#user_id').val();
    let invoiceDate = $('#invoice_date').val();

    if (!userId || !invoiceDate) {

        Swal.fire(
            "Information",
            "Please select a User and an Invoice Date first.",
            "info"
        );

        return;
    }

    $.ajax({

        url: routeSettlementStatus,

        type: "GET",

        data: {

            user_id: userId,

            invoice_date: invoiceDate

        },

        success: function (response) {

            if (!response.status) {

                Swal.fire(
                    "Error",
                    response.message,
                    "error"
                );

                return;
            }

            renderSettlementStatus(response.data);

            $('#statusUserName').text($('#user_id option:selected').text());

            $('#statusInvoiceDate').text($('#invoice_date').val() ? formatDmy($('#invoice_date').val()) : '');

            $('#settlementStatusModal').modal('show');

        },

        error: function () {

            Swal.fire(
                "Error",
                "Unable to load settlement status.",
                "error"
            );

        }

    });

}
function formatDmy(isoDate) {

    let parts = isoDate.split('-');

    if (parts.length !== 3) return isoDate;

    return parts[2] + '-' + parts[1] + '-' + parts[0];

}
const statusBadgeClass = {

    PENDING: 'bg-warning text-dark',

    APPROVED: 'bg-info text-dark',

    PAID: 'bg-success',

    CANCELLED: 'bg-secondary'

};
function renderSettlementStatus(rows) {

    let tbody = $('#settlementStatusBody');

    tbody.empty();

    if (!rows.length) {

        $('#settlementStatusEmpty').show();

        return;
    }

    $('#settlementStatusEmpty').hide();

    rows.forEach(function (row) {

        let badgeClass = statusBadgeClass[row.payment_status] || 'bg-light text-dark';

        tbody.append(`
            <tr>
                <td>${row.invoice_no ?? ''}</td>
                <td>${row.doctor_name ?? ''}</td>
                <td>${row.patient_name ?? ''}</td>
                <td>${row.item_description ?? ''}</td>
                <td class="text-end">${money(row.payable_amount)}</td>
                <td class="text-end">${money(row.paid_amount)}</td>
                <td class="text-end">${money(row.balance_amount)}</td>
                <td><span class="badge ${badgeClass}">${row.payment_status}</span></td>
                <td>${row.last_settlement_no ?? '-'}</td>
                <td>${row.last_settlement_date ?? '-'}</td>
            </tr>
        `);

    });

}
function initialiseDoctorChange() {

    $('#doctor_id, #user_id').on('change', maybeLoadOutstanding);

    $('#invoice_date').on('change', maybeLoadOutstanding);

}

function maybeLoadOutstanding() {

    let doctorId = $('#doctor_id').val();
    let userId = $('#user_id').val();
    let invoiceDate = $('#invoice_date').val();

    if (!doctorId || !userId || !invoiceDate) {

        clearTable();

        return;

    }

    loadOutstandingPayables(doctorId);

}
function initialiseDeduction() {

    $('#preview_deduction').on('input', function () {

        deductionAmount = parseFloat($(this).val()) || 0;

        calculateTotals();

        updatePreviewSummary();

    });

}
function initialiseDataTable() {

    settlementTable = $('#settlementTable').DataTable({

        processing: true,

        searching: true,

        paging: true,

        ordering: true,

        info: true,

        responsive: true,

        autoWidth: false,

        pageLength: 25,

        language: {

            emptyTable: "Select a doctor"

        },
        drawCallback: function () {

            settlementItems.forEach(function (item) {

                $('.row-check[data-id="' + item.payable_id + '"]')
                    .prop('checked', item.selected);

                $('.settlement-input[data-id="' + item.payable_id + '"]')
                    .val(item.settlement_amount);

            });

        },
        columns: [

            { data: 'check' },

            { data: 'invoice_no' },

            { data: 'invoice_date' },

            { data: 'invoice_type' },

            { data: 'patient_id' },

            { data: 'patient_name' },

            { data: 'description' },

            { data: 'payable_amount' },

            { data: 'paid_amount' },

            { data: 'balance_amount' },

            { data: 'settlement_amount' }

        ]

    });

}
function clearScreen() {

    $('#doctor_id').val('').trigger('change');

    $('#user_id').val('').trigger('change');

    $('#doctorInfo').hide();

    settlementTable.clear().draw();

    

    selectedPayables = [];

    settlementItems = [];

    grossAmount = 0;

    deductionAmount = 0;

    netAmount = 0;

    outstandingAmount = 0;
    $('#previewBody').empty();

    $('#previewGross').text('0.00');

    $('#previewDeduction').text('0.00');

    $('#previewNet').text('0.00');

    $('#previewRemaining').text('0.00');

    $('#previewInvoiceTotal').text('0.00');

    $('#previewInvoiceCount').text('0');

    $('#preview_deduction').val(0);

    $('#gross_amount').val(0);

    $('#net_payment').val(0);

    $('#selected_payable_ids').val('');

    $('#settlement_json').val('');

    updateSummary();

}
function clearTable() {

    settlementTable.clear().draw();

    $('#doctorInfo').hide();

    updateSummary();

}
function money(value) {

    value = parseFloat(value);

    if (isNaN(value)) {

        value = 0;

    }

    return value.toLocaleString(

        'en-IN',

        {

            minimumFractionDigits:2,

            maximumFractionDigits:2

        }

    );

}
/*--------------------------------------------------------------
Load Outstanding Payables
--------------------------------------------------------------*/

function loadOutstandingPayables(doctorId) {

    $.ajax({

        url: routeDoctorOutstanding,

        type: "POST",

        data: {

            doctor_id: doctorId,

            user_id: $('#user_id').val(),

            invoice_date: $('#invoice_date').val(),

            _token: csrfToken

        },

        beforeSend: function () {

            settlementTable.clear().draw();

        },

        success: function (response) {

            if (!response.status) {

                Swal.fire(
                    "Information",
                    response.message,
                    "info"
                );

                return;
            }

            settlementItems = response.data;
            settlementItems.forEach(function (item) {

                item.selected = false;

                item.settlement_amount = Number(item.balance_amount);

            });
            populateSettlementTable();
            calculateTotals();

            updateFooterTotals();

            loadDoctorSummary(response.summary);

        },

        error: function (xhr) {

            Swal.fire(
                "Error",
                "Unable to load outstanding payables.",
                "error"
            );

        }

    });

}
function populateSettlementTable() {

    settlementTable.clear();

    settlementItems.forEach(function (item) {

        settlementTable.row.add({

            check:

                '<input type="checkbox" ' +
                'class="row-check" ' +
                'data-id="' + item.payable_id + '">',

            invoice_no: item.invoice_no,

            invoice_date: item.invoice_date,

            invoice_type:

                item.invoice_type == 'DOCTOR_VISIT'
                ? '<span class="badge bg-primary">CONSULTATION</span>'
                : '<span class="badge bg-success">DIAGNOSTIC</span>',

            patient_id: item.patient_id,

            patient_name: item.patient_name,

            description: item.item_description,

            payable_amount:
                '<span class="payable">' +
                money(item.payable_amount) +
                '</span>',

            paid_amount:

                '<span class="paid">' +
                money(item.paid_amount) +
                '</span>',

            balance_amount:

                '<span class="balance">' +
                money(item.balance_amount) +
                '</span>',

            settlement_amount:

                '<input type="number"' +
                ' class="form-control settlement-input"' +
                ' value="' + item.balance_amount + '"' +
                ' min="0"' +
                ' max="' + item.balance_amount + '"' +
                ' data-id="' + item.payable_id + '">'

        });

    });

    settlementTable.draw();

}
function loadDoctorSummary(summary) {

    $('#doctorInfo').show();

    $('#doctor_fee').text(

        money(summary.consultation_fee)

    );

    $('#doctor_invoice_count').text(

        summary.invoice_count

    );

    $('#doctor_outstanding').text(

        money(summary.outstanding_amount)

    );

    $('#doctor_last_settlement').text(

        summary.last_settlement

    );

    outstandingAmount = summary.outstanding_amount;
    $('#doctorMonthAmount').text(
        money(summary.month_amount)
    );

    $('#doctorYearAmount').text(
        money(summary.year_amount)
    );
    updateSummary();
    updateFooterTotals();


}

$(document).on('change', '.row-check', function () {

    let id = $(this).data('id');

    let item = settlementItems.find(x => x.payable_id == id);

    if (!item) return;

    item.selected = this.checked;

    item.settlement_amount = this.checked
        ? Number(item.balance_amount)
        : 0;

    $('.settlement-input[data-id="' + id + '"]')
        .val(item.settlement_amount.toFixed(2));

    calculateTotals();

    updateFooterTotals();

});
$(document).on('input', '.settlement-input', function () {

    let id = $(this).data('id');

    let item = settlementItems.find(x => x.payable_id == id);

    if (!item) return;

    let balance = Number(item.balance_amount);

    let amount = parseFloat($(this).val()) || 0;

    if (amount > balance) {

        amount = balance;

        $(this).val(balance.toFixed(2));

    }

    if (amount < 0) {

        amount = 0;

        $(this).val('0.00');

    }

    // Update settlementItems array
    

   

    if (item) {

        item.settlement_amount = amount;

    }

    calculateTotals();

    updateFooterTotals();

});

function toggleSettlement(payableId, checked) {

    const item = settlementItems.find(
        x => x.payable_id == payableId
    );

    if (!item) {
        return;
    }

    item.selected = checked;

    item.settlement_amount =
        checked
            ? parseFloat(item.balance_amount)
            : 0;

    $('.settlement-input[data-id="' + payableId + '"]')
        .val(item.settlement_amount);

    calculateTotals();

}

function selectAllRows() {

    $('.row-check').prop('checked', true);

    settlementItems.forEach(function (item) {

        item.selected = true;
        item.settlement_amount = item.balance_amount;

        $('.settlement-input[data-id="' + item.payable_id + '"]')
            .val(item.balance_amount);

    });

    calculateTotals();

}

function unselectAllRows() {

    $('.row-check').prop('checked', false);

    settlementItems.forEach(function (item) {

        item.selected = false;
        item.settlement_amount = 0;

        $('.settlement-input[data-id="' + item.payable_id + '"]')
            .val(0);

    });

    calculateTotals();

}

        $('#checkAll').on(

    'change',

    function () {

        if ($(this).prop('checked'))

            selectAllRows();

        else

            unselectAllRows();

    }

);
function calculateTotals() {

    grossAmount = 0;

    netAmount = 0;

    deductionAmount = 0;

    selectedPayables = [];

    let selectedCount = 0;

    settlementItems.forEach(function (item) {

        if (item.selected) {

            selectedCount++;

            selectedPayables.push(item.payable_id);

            grossAmount += Number(item.settlement_amount);

        }

    });

    $('#selected_payable_ids')
        .val(selectedPayables.join(','));

    $('#settlement_json')
        .val(JSON.stringify(
            settlementItems.filter(x => x.selected)
        ));

    deductionAmount =
        Number($('#preview_deduction').val()) || 0;

    if (deductionAmount > grossAmount) {

        deductionAmount = grossAmount;

        $('#preview_deduction').val(
            deductionAmount.toFixed(2)
        );

    }

    netAmount = grossAmount - deductionAmount;

    $('#gross_amount').val(grossAmount.toFixed(2));

    $('#net_payment').val(netAmount.toFixed(2));

    updateSummary();

    updatePreviewSummary(selectedCount);

}
function updateSummary() {

    let outstanding = 0;

    settlementItems.forEach(function (item) {

        outstanding += Number(item.balance_amount);

    });

    let remaining = outstanding - grossAmount;

    $('#ribbonOutstanding').text(money(outstanding));

    $('#ribbonSelected').text(selectedPayables.length);

    $('#ribbonDeduction').text(money(deductionAmount));

    $('#ribbonNet').text(money(netAmount));

    $('#summaryOutstanding').text(money(outstanding));

    $('#summarySelected').text(selectedPayables.length);

    $('#summarySettlement').text(money(grossAmount));

    $('#summaryDeduction').text(money(deductionAmount));

    $('#summaryNet').text(money(netAmount));

    $('#summaryRemaining').text(money(remaining));

    $('#btnConfirmSettlement')
        .prop('disabled', grossAmount <= 0);

}
function updateFooterTotals() {

    let payable = 0;
    let paid = 0;
    let balance = 0;
    let settlement = 0;

    settlementItems.forEach(function (item) {

        payable += Number(item.payable_amount);

        paid += Number(item.paid_amount);

        balance += Number(item.balance_amount);

        if (item.selected) {

            settlement += Number(item.settlement_amount);

        }

    });

    $('#footerPayable').text(money(payable));

    $('#footerPaid').text(money(paid));

    $('#footerBalance').text(money(balance));

    $('#footerSettlement').text(money(settlement));

}
function updatePreviewSummary(selectedCount) {

    $('#previewGross').text(money(grossAmount));

    $('#previewDeduction').text(money(deductionAmount));

    $('#previewNet').text(money(netAmount));
    const remaining = Math.max(
        outstandingAmount - grossAmount,
        0
    );
    $('#remaining_balance').text(
        money(remaining)
    );

    $('#previewRemaining').text(
        money(remaining)
    );

    $('#previewInvoiceTotal').text(money(grossAmount));

    $('#previewInvoiceCount').text(selectedCount);

}

function loadPreview() {

    if (selectedPayables.length === 0) {

        Swal.fire(
            'Information',
            'Please select at least one invoice.',
            'info'
        );

        return;
    }

    buildPreviewTable();

    if (previewCanvas === null) {

        previewCanvas = new bootstrap.Offcanvas(
            document.getElementById('previewCanvas')
        );

    }

    previewCanvas.show();

}
/*--------------------------------------------------------------
Save Settlement
--------------------------------------------------------------*/
function saveSettlement() {

    let doctorId = $('#doctor_id').val();

    if (!doctorId) {

        Swal.fire(
            'Validation',
            'Please select a doctor.',
            'warning'
        );

        return;
    }

    if (selectedPayables.length === 0) {

        Swal.fire(
            'Validation',
            'Please select at least one invoice.',
            'warning'
        );

        return;
    }

    if (grossAmount <= 0) {

        Swal.fire(
            'Validation',
            'Settlement amount must be greater than zero.',
            'warning'
        );

        return;
    }

    let formData = {

        _token: csrfToken,

        doctor_id: doctorId,

        settlement_date: $('#settlement_date').val(),

        payment_mode: $('#payment_mode').val(),

        bank_name: $('#bank_name').val(),

        cheque_no: $('#cheque_no').val(),

        utr_no: $('#utr_no').val(),

        remarks: $('#remarks').val(),

        internal_notes: $('#internal_notes').val(),

        deduction_amount: deductionAmount,

        gross_amount: grossAmount,

        net_amount: netAmount,

        payable_ids: selectedPayables,

        settlement_items: settlementItems
            .filter(item => item.selected)
            .map(item => ({
                payable_id: item.payable_id,
                invoice_id: item.invoice_id,
                invoice_no: item.invoice_no,
                settlement_amount: item.settlement_amount,
                balance_amount: item.balance_amount
            }))

    };

    $('#btnConfirmSettlement')
        .prop('disabled', true)
        .html(
            '<span class="spinner-border spinner-border-sm"></span> Saving...'
        );

    $.ajax({

        url: routeSaveSettlement,

        type: "POST",

        data: formData,

        success: function (response) {

            $('#btnConfirmSettlement')
                .prop('disabled', false)
                .html(
                    '<i class="ri-check-double-line"></i> Confirm Settlement'
                );

            if (!response.status) {

                Swal.fire(
                    'Error',
                    response.message,
                    'error'
                );

                return;
            }
            lastSettlementId = response.settlement_id;

            $('#btnSettlementPdf')
                .prop('disabled', false)
                .data('id', response.settlement_id);
            

            Swal.fire({

                icon: 'success',

                title: 'Settlement Completed',

                html:
                    '<b>Settlement No :</b> ' +
                    response.settlement_no +
                    '<br><br>' +
                    response.message

            });

            
            Swal.fire({
                icon: 'success',
                title: 'Settlement Completed',
                html:
                    '<b>Settlement No :</b> ' +
                    response.settlement_no +
                    '<br><br>' +
                    response.message
            }).then(function () {

                if (previewCanvas) {
                    previewCanvas.hide();
                }

                const doctorId = $('#doctor_id').val();

                clearScreen();

                if (doctorId) {

                    $('#doctor_id')
                        .val(doctorId)
                        .trigger('change');

                }

            });
            

        },

        error: function (xhr) {

            $('#btnConfirmSettlement')
                .prop('disabled', false)
                .html(
                    '<i class="ri-check-double-line"></i> Confirm Settlement'
                );

            let message = 'Unable to save settlement.';

            if (xhr.responseJSON &&
                xhr.responseJSON.message) {

                message = xhr.responseJSON.message;

            }

            Swal.fire(
                'Error',
                message,
                'error'
            );

        }

    });

}


function openSettlementPdf() {

    let settlementId = $('#btnSettlementPdf').data('id');

    if (!settlementId) {

        Swal.fire(

            'Information',

            'Please complete the settlement first.',

            'info'

        );

        return;

    }

    window.open(

        '/doctor-settlement/pdf/' + settlementId,

        '_blank'

    );

}
/*--------------------------------------------------------------
Build Preview Table
--------------------------------------------------------------*/
function buildPreviewTable() {

    let tbody = $('#previewBody');

    tbody.empty();

    let invoiceTotal = 0;

    let rowNo = 1;

    // Header Information

    const doctorName = $('#doctor_id option:selected')
        .text()
        .replace(/^[^-]*-\s*/, '');

    $('#previewDoctor').text(doctorName);

    const dt = $('#settlement_date').val();

    if (dt) {

        const p = dt.split('-');

        $('#previewSettlementDate').text(

            p[2] + '/' + p[1] + '/' + p[0]

        );

    }

    $('#previewPaymentMode').text(
        $('#payment_mode option:selected').text()
    );

    // Build Invoice List

    settlementItems.forEach(function (item) {

        if (!item.selected) {
            return;
        }

        let amount = parseFloat(item.settlement_amount) || 0;

        invoiceTotal += amount;

        tbody.append(

            '<tr>' +

            '<td class="text-center">' +
            rowNo +
            '</td>' +

            '<td>' + item.invoice_no + '</td>' +

            '<td>' +

            (item.invoice_type === 'DOCTOR_VISIT'

                ? '<span class="badge bg-primary">CONSULTATION</span>'

                : '<span class="badge bg-success">DIAGNOSTIC</span>')

            +

            '</td>' +

            '<td>' + item.patient_name + '</td>' +

            '<td class="text-end">' +
            money(item.balance_amount) +
            '</td>' +

            '<td class="text-end fw-bold">' +
            money(item.settlement_amount) +
            '</td>' +

            

            '</tr>'

        );

        rowNo++;

    });

    // No invoice selected

    if (rowNo === 1) {

        tbody.append(

            '<tr>' +

            '<td colspan="6" class="text-center text-muted">' +

            'No invoice selected.'

            +

            '</td>' +

            '</tr>'

        );

    }

    // Footer

    $('#previewInvoiceTotal').text(
        money(invoiceTotal)
    );

    $('#previewInvoiceCount').text(
        rowNo - 1
    );

    $('#previewGross').text(
        money(grossAmount)
    );

    $('#previewDeduction').text(
        money(deductionAmount)
    );

    $('#previewNet').text(
        money(netAmount)
    );

    $('#previewRemaining').text(
        money(
            Math.max(
                outstandingAmount - grossAmount,
                0
            )
        )
    );

    // Enable / Disable Confirm Button

    $('#btnConfirmSettlement').prop(

        'disabled',

        rowNo === 1 || grossAmount <= 0

    );

}
function printPreview() {

    const body = document.getElementById('previewBody').innerHTML;

    const w = window.open('', '_blank');

    w.document.write(`
        <html>
        <head>
            <title>Doctor Settlement Preview</title>

            <link rel="stylesheet"
                  href="/build/css/bootstrap.min.css">

            <style>

                body{
                    padding:20px;
                    font-size:13px;
                }

                table{
                    width:100%;
                    border-collapse:collapse;
                }

                table th,
                table td{
                    border:1px solid #ccc;
                    padding:6px;
                }

            </style>

        </head>

        <body>

            <h3>Doctor Settlement Preview</h3>

            ${body}

        </body>

        </html>
    `);

    w.document.close();

    w.focus();

    setTimeout(function () {

        w.print();

        w.close();

    }, 500);

}
function exportPreviewPdf() {

    const form = $('<form>', {
        method: 'POST',
        action: routePreviewPdf,
        target: '_blank'
    });

    form.append(
        $('<input>', {
            type: 'hidden',
            name: '_token',
            value: csrfToken
        })
    );

    form.append(
        $('<input>', {
            type: 'hidden',
            name: 'settlement_items',
            value: JSON.stringify(
                settlementItems.filter(x => x.selected)
            )
        })
    );

    $('body').append(form);

    form.submit();

    form.remove();

}
function exportPreviewExcel() {

    window.open(
        routePreviewExcel +
        '?doctor_id=' +
        $('#doctor_id').val(),
        '_blank'
    );

}
function sendWhatsappPreview() {

    Swal.fire({
        title: 'Not implemented',
        text: 'Send WhatsApp preview',
        icon: 'info'
    });

}
function sendEmailPreview() {

    Swal.fire({
        title: 'Not implemented',
        text: 'Send email preview',
        icon: 'info'
    });

}
