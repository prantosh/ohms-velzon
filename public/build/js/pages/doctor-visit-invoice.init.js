/*
|--------------------------------------------------------------------------
| INVOICE DATATABLE
|--------------------------------------------------------------------------
*/

let invoiceDataTable = null;
let cardValidated = false;
let transitioningToConfirm = false;

// The Reference field (last 4 digits of a card, or a UPI ref) only makes
// sense for a non-cash payment -- shown for Card/UPI, hidden and cleared
// for Cash so a stale value never gets submitted alongside a Cash payment.
function togglePaymentReferenceField() {
    let mode = $('#payment_mode').val();
    let show = mode === 'Card' || mode === 'UPI';

    $('#payment_reference-wrap').toggle(show);

    if (!show) {
        $('#payment_reference').val('');
    }
}

$(document).on('change', '#payment_mode', togglePaymentReferenceField);
$(function () { togglePaymentReferenceField(); });

/*
|--------------------------------------------------------------------------
| DISPLAY-ONLY DATE FORMAT (dd-mm-yyyy)
|--------------------------------------------------------------------------
*/

function formatDisplayDate(dateString) {

    if (!dateString) return '';

    let date = new Date(dateString);

    if (isNaN(date.getTime())) return dateString;

    let day = String(date.getDate()).padStart(2, '0');
    let month = String(date.getMonth() + 1).padStart(2, '0');
    let year = date.getFullYear();

    return day + '-' + month + '-' + year;
}

/*
|--------------------------------------------------------------------------
| LOCK/UNLOCK #invoice_date (now a flatpickr field -- toggling readonly on
| the original hidden input alone does not stop the visible altInput from
| opening the calendar, so this also syncs the flatpickr instance itself)
|--------------------------------------------------------------------------
*/

function setInvoiceDateLocked(locked) {

    $("#invoice_date").prop("readonly", locked);

    let fp = document.getElementById("invoice_date")._flatpickr;

    if (fp) {
        fp.set("clickOpens", !locked);
        if (fp.altInput) fp.altInput.readOnly = locked;
    }
}

/*
|--------------------------------------------------------------------------
| LOAD INVOICES
|--------------------------------------------------------------------------
*/

let lastInvoiceData = [];

/*
|--------------------------------------------------------------------------
| ROW HTML -- shared by both the Pending and Invoice Created tables
|--------------------------------------------------------------------------
*/

function buildInvoiceRowHtml(raw) {

    return `

<tr>

    <td>${raw.appointment_no ?? ''}</td>

    <td>${raw.patient_id ?? ''}</td>

    <td>${raw.doctor_name ?? ''}</td>

    <td>${raw.patient_name ?? ''}</td>

    <td>${raw.token_no ?? ''}</td>

    <td>${raw.invoice_no ?? ''}</td>

    <td>${formatDisplayDate(raw.appointment_date)}</td>

    <td>${raw.consultation_fee_total ?? 0}</td>

    <td>${raw.paid_amount ?? 0}</td>


    <td>

                        ${raw.cancelled === 'Y'

                                        ? `<span class="badge bg-danger">
                        Cancelled
                   </span>`

                                        : raw.invoice_no

                                            ? `<span class="badge bg-success">
                        Invoice Created
                   </span>`

                                            : `<span class="badge bg-warning">
                        Pending
                   </span>`
                }
    </td>

    <td>

                ${raw.invoice_no ? (

                                        raw.cancelled === 'Y'

                                            ?

                                            `<span>

                     </span>`

                                            :

                                            `

                    <button class="btn btn-sm btn-info printBtn"

                        data-id="${raw.invoice_id}">

                        <i class="ri-printer-line"></i>

                    </button>
                    <button class="btn btn-sm btn-secondary printPrescriptionBtn"

                        data-id="${raw.invoice_id}"
                        title="Print Prescription">

                        <i class="ri-file-list-3-line"></i>

                    </button>
                    <button class="btn btn-sm btn-success whatsappBtn"
                        data-id="${raw.invoice_id}">
                        <i class="ri-whatsapp-line"></i>
                    </button>

                    <button class="btn btn-sm btn-danger deleteBtn"

                        data-id="${raw.invoice_id}">

                        <i class="ri-delete-bin-line"></i>

                    </button>

                    `

                                    ) : `

                <button class="btn btn-sm btn-primary addBtn"

                    data-appointment_id="${raw.id}"
                    data-patient_id="${raw.patient_id ?? ''}"
                    data-doctor_id="${raw.doctor_id ?? ''}"
                    data-patient_age="${raw.patient_age ?? ''}"
                    data-patient_gender="${raw.patient_gender ?? ''}"
                    data-appointment_no="${raw.appointment_no ?? ''}"
                    data-patient_name="${raw.patient_name ?? ''}"
                    data-doctor_name="${raw.doctor_name ?? ''}"
                    data-appointment_date="${raw.appointment_date ?? ''}"
                    data-visit_time="${raw.visit_time ?? ''}"
                    data-consultation_fee="${raw.consultation_fee_total ?? 0}">

                    <i class="ri-add-line"></i>

                </button>

                `}

    </td>
</tr>
                `;
}

/*
|--------------------------------------------------------------------------
| DOCTOR FILTER -- distinct doctor names across today's appointments,
| independent of which tab is active so switching tabs keeps the filter.
|--------------------------------------------------------------------------
*/

function populateDoctorFilter(data) {

    let select = $('#doctorFilter');
    let previousValue = select.val();

    let doctorNames = [...new Set(
        data.map(row => row.doctor_name).filter(name => !!name)
    )].sort();

    select.html('<option value="">All Doctors</option>');

    doctorNames.forEach(name => {
        select.append(`<option value="${name}">${name}</option>`);
    });

    if (doctorNames.includes(previousValue)) {
        select.val(previousValue);
    }
}

/*
|--------------------------------------------------------------------------
| RENDER BOTH TABLES -- split by invoice_no presence into Pending /
| Invoice Created, apply the doctor filter, sort each tab's own default
| order, and (re)init a separate DataTable per tab.
|--------------------------------------------------------------------------
*/

function renderInvoiceTables() {

    let doctorFilter = $('#doctorFilter').val();

    let filtered = doctorFilter
        ? lastInvoiceData.filter(row => row.doctor_name === doctorFilter)
        : lastInvoiceData;

    let pendingRows = filtered.filter(row => !row.invoice_no);
    let createdRows = filtered.filter(row => !!row.invoice_no);

    // Pending: natural queue order (today's token/serial number).
    pendingRows.sort((a, b) => (a.token_no ?? 0) - (b.token_no ?? 0));

    // Invoice Created: most recently created invoice on top.
    createdRows.sort((a, b) =>
        new Date(b.invoice_created_at ?? 0) - new Date(a.invoice_created_at ?? 0)
    );

    $('#pendingCount').text(pendingRows.length);
    $('#createdCount').text(createdRows.length);

    [['#pendingTable', pendingRows], ['#createdTable', createdRows]].forEach(([selector, rows]) => {

        if ($.fn.DataTable.isDataTable(selector)) {
            $(selector).DataTable().destroy();
        }

        $(`${selector} tbody`).html(rows.map(buildInvoiceRowHtml).join(''));

        $(selector).DataTable({
            responsive: true,
            destroy: true,
            // Preserve the default order just built above -- DataTables
            // otherwise auto-sorts by the first column on init. Clicking a
            // column header still works normally from here.
            order: [],
            // Action column was auto-sized by DataTables to ~75px; widened
            // to double that (150px). autoWidth must be off, otherwise
            // DataTables recalculates and overrides this fixed width based
            // on content instead of respecting it.
            autoWidth: false,
            columnDefs: [
                { targets: -1, width: '150px' }
            ]
        });
    });
}

// DataTables mis-sizes columns initialized while its tab-pane is hidden --
// re-measure once the Invoice Created tab actually becomes visible.
$(document).on('shown.bs.tab', 'a[data-bs-toggle="tab"]', function () {
    $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
});

$(document).on('change', '#doctorFilter', renderInvoiceTables);

/*
|--------------------------------------------------------------------------
| LOAD INVOICES
|--------------------------------------------------------------------------
*/

function loadInvoices() {

    $.ajax({

        url: "/doctor-visit-invoice/list",

        type: "GET",

        dataType: "json",

        success: function (response) {

            if (
                response.status !== true ||
                !Array.isArray(response.data)
            ) {

                console.log("Invalid response");

                return;
            }

            lastInvoiceData = response.data;

            populateDoctorFilter(lastInvoiceData);

            renderInvoiceTables();
        },

        error: function (xhr) {

            console.log(xhr.responseText);
        }
    });
}


/*
|--------------------------------------------------------------------------
| LOAD APPOINTMENTS
|--------------------------------------------------------------------------
*/

function loadAppointments() {

    $.ajax({

        url: "/doctor-visit-invoice/appointments",

        type: "GET",

        success: function (response) {

            let dropdown =
                $("#appointment_id");

            dropdown.html('');

            dropdown.append(`

<option value="">

    Select Appointment

</option>

            `);

            response.forEach(function (row) {

                dropdown.append(`

<option value="${row.id}">

    ${row.patient_name}
    (${row.patient_mobile_no})

</option>

                `);
            });
        }
    });
}

$(document).on(
    "click",
    ".whatsappBtn",
    function () {

        let id = $(this).data("id");
        let btn = $(this);

        btn.prop("disabled", true);

        Swal.fire({
            title: "Please wait...",
            text: "We are sending WhatsApp message",
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        $.get(
            "/doctor-visit-invoice/send-whatsapp/" + id,
            function (response) {

                btn.prop("disabled", false);

                Swal.fire({
                    icon: response.status ? "success" : "error",
                    title: response.status ? "Sent" : "Failed",
                    text: response.message
                });

            }
        ).fail(function () {

            btn.prop("disabled", false);

            Swal.fire({
                icon: "error",
                title: "Failed",
                text: "Something went wrong. Please try again."
            });
        });
    }
);


/*
|--------------------------------------------------------------------------
| VALIDATE CARD
|--------------------------------------------------------------------------
*/

$(document).on(
    "click",
    "#validateCardBtn",
    function () {

        let cardNumber =
            $("#card_number").val();

        let patientName =
            $("#patient_name").val();

        if (cardNumber == '') {

            Swal.fire({

                icon: "warning",

                title: "Card Number Required",

                text:
                    "Please enter card number"
            });

            return;
        }

       

        $.ajax({

            url:
                "/doctor-visit-invoice/validate-card",

            type: "POST",

            data: {

                card_number:
                    cardNumber,

                patient_name:
                    patientName,

                doctor_id:
                    $("#doctor_id").val(),

                _token:
                    $('meta[name="csrf-token"]')
                        .attr('content')
            },

            success: function (response) {
                if ((response.discount ?? 0) > 0) {

                    $("#patient_name").prop("readonly", true);

                } else {

                    $("#patient_name").prop("readonly", false);
                }

                if (response.status) {

                    cardValidated = true;

                    $("#discount")
                        .val(response.discount ?? 0)
                        .trigger("change");

                    $("#card_validation_status")
                        .html(
                            '<span class="text-success">Validated</span>'
                        );

                    Swal.fire({

                        icon: "success",

                        title: "Success",

                        text:
                            response.message
                    });

                } else {

                    cardValidated = false;

                    $("#discount")
                        .val(0)
                        .trigger("change");

                    $("#card_validation_status")
                        .html(
                            '<span class="text-danger">Invalid</span>'
                        );

                    Swal.fire({

                        icon: "error",

                        title: "Validation Failed",

                        text:
                            response.message
                    });
                }
            }
        });
    }
);
/*
|--------------------------------------------------------------------------
| EDIT
|--------------------------------------------------------------------------
*/

$(document).on(
    "click",
    ".addBtn",
    function () {

        $("#invoiceForm")[0].reset();

        $("#edit-id").val('');

        $("#appointment_id")
            .val($(this).data("appointment_id"));

        $("#patient_id")
            .val($(this).data("patient_id"));

        $("#doctor_id")
            .val($(this).data("doctor_id"));

        $("#appointment_no")
            .val($(this).data("appointment_no"));

        $("#patient_name")
            .val($(this).data("patient_name"));

        $("#patient_age")
            .val($(this).data("patient_age"));

        $("#patient_gender")
            .val($(this).data("patient_gender"));

        $("#doctor_name")
            .val($(this).data("doctor_name"));

        $("#visit_date")
            .val($(this).data("appointment_date"));

        $("#visit_time")
            .val($(this).data("visit_time"));

        $("#consultation_fee")
            .val($(this).data("consultation_fee"));
        calculatePayable();
        setFlatpickrValue(
            "invoice_date",
            new Date()
                .toISOString()
                .split('T')[0]
        );

        $("#patient_name").prop("readonly", false);
        $("#consultation_fee").prop("readonly", true);
        setInvoiceDateLocked(false);
        $("#payment_mode").prop("disabled", false);
        $("#paid_amount").prop("readonly", false);
        $("#is_card_holder").prop("disabled", false).prop("checked", false);
        $("#card_number").prop("readonly", false);
        $("#validateCardBtn").prop("disabled", false);
        $("#card_number_div").hide();
        $("#validate_card_div").hide();
        $("#card_validation_status").html('');
        cardValidated = false;

        $(".modal-title")
            .text("Create Invoice");

        $("#invoiceModal")
            .modal("show");
    }
);

/*
|--------------------------------------------------------------------------
| EDIT BUTTON
|--------------------------------------------------------------------------
*/

$(document).on(
    "click",
    ".editBtn",
    function () {

        $("#invoiceForm")[0].reset();

        $("#edit-id")
            .val($(this).data("id"));

        $("#appointment_id")
            .val($(this).data("appointment_id"));

        $("#appointment_no")
            .val($(this).data("appointment_no"));

        $("#patient_id")
            .val($(this).data("patient_id"));

        $("#patient_name")
            .val($(this).data("patient_name"));

        $("#patient_age")
            .val($(this).data("patient_age"));

        $("#patient_gender")
            .val($(this).data("patient_gender"));

        $("#doctor_name")
            .val($(this).data("doctor_name"));

        setFlatpickrValue("invoice_date", $(this).data("invoice_date"));

        $("#consultation_fee")
            .val($(this).data("consultation_fee"));
        
        $("#discount")
            .val($(this).data("discount"));
        calculatePayable();
       

        

        $("#payment_mode")
            .val($(this).data("payment_mode"));
        togglePaymentReferenceField();

        $("#visit_date")
            .val($(this).data("visit_date"));

        $("#visit_time")
            .val($(this).data("visit_time"));

        

        $("#remarks")
            .val($(this).data("remarks"));

        $(".modal-title")
            .text("Edit Invoice");
        $("#doctor_id")
            .val($(this).data("doctor_id"));

        $("#invoiceModal")
            .modal("show");
        $("#card_number")
            .val($(this).data("card_number"));

        if ($(this).data("is_card_holder") == 1) {

            $("#is_card_holder").prop("checked", true);

            $("#card_number_div").show();

            $("#validate_card_div").show();

        } else {

            $("#is_card_holder").prop("checked", false);

            $("#card_number_div").hide();

            $("#validate_card_div").hide();
        }
        let editCardNumber =
            parseFloat($(this).data("card_number")) || 0;

        if (editCardNumber !== 0) {

            $("#patient_name").prop("readonly", true);

        } else {

            $("#patient_name").prop("readonly", false);
        }

        /*
        |--------------------------------------------------------------------------
        | LOCK FINANCIAL / SCHEDULING FIELDS ON EDIT (ALWAYS, REGARDLESS OF CARD)
        |--------------------------------------------------------------------------
        */

        $("#consultation_fee").prop("readonly", true);
        setInvoiceDateLocked(true);
        $("#payment_mode").prop("disabled", true);
        $("#paid_amount").prop("readonly", true);
        $("#is_card_holder").prop("disabled", true);
        $("#card_number").prop("readonly", true);
        $("#validateCardBtn").prop("disabled", true);

        if ($(this).data("is_card_holder") == 1) {

            cardValidated = true;

            $("#card_validation_status").html(
                '<span class="text-success">Validated</span>'
            );

        } else {

            cardValidated = false;

            $("#card_validation_status").html('');
        }

    }
);
/*
|--------------------------------------------------------------------------
| PRINT
|--------------------------------------------------------------------------
*/

$(document).on(
    "click",
    ".printBtn",
    function () {

        let id = $(this).data("id");

        // The endpoint streams the PDF directly (no disk save, no JSON
        // wrapper) -- same as every other "print" action in this app.
        window.open(
            "/doctor-visit-invoice/print/" + id,
            "_blank"
        );
    }
);
/*
|--------------------------------------------------------------------------
| PRINT PRESCRIPTION
|--------------------------------------------------------------------------
*/

$(document).on(
    "click",
    ".printPrescriptionBtn",
    function () {

        let id = $(this).data("id");

        window.open(
            "/doctor-visit-invoice/print-prescription/" + id,
            "_blank"
        );
    }
);
/*
|--------------------------------------------------------------------------
| SAVE INVOICE
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| REVIEW & CONFIRM (OFFCANVAS)
|--------------------------------------------------------------------------
*/

$(document).on(
    "submit",
    "#invoiceForm",
    function (e) {

        e.preventDefault();

        /*
        |--------------------------------------------------------------------------
        | PATIENT AGE / GENDER VALIDATION
        |--------------------------------------------------------------------------
        */

        if (!$("#patient_age").val() || !$("#patient_gender").val()) {

            Swal.fire({

                icon: "warning",

                title: "Age and Gender Required",

                text:
                    "Please fill in the patient's age and gender"
            });

            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | CARD VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($("#is_card_holder").is(":checked")) {

            if ($("#card_number").val() == '') {

                Swal.fire({

                    icon: "warning",

                    title: "Card Number Required",

                    text:
                        "Please enter card number"
                });

                return false;
            }

            if (cardValidated === false) {

                Swal.fire({

                    icon: "warning",

                    title: "Validation Required",

                    text:
                        "Please validate patient card first"
                });

                return false;
            }
        }

        let isCardHolder = $("#is_card_holder").is(":checked");

        $("#review-appointment_no").text($("#appointment_no").val() || '-');
        $("#review-patient_id").text($("#patient_id").val() || '-');
        $("#review-patient_name").text($("#patient_name").val() || '-');
        $("#review-patient_age").text($("#patient_age").val() || '-');
        $("#review-patient_gender").text($("#patient_gender").val() || '-');
        $("#review-doctor_name").text($("#doctor_name").val() || '-');
        $("#review-visit_date").text(formatDisplayDate($("#visit_date").val()) || '-');
        $("#review-visit_time").text($("#visit_time").val() || '-');
        $("#review-invoice_date").text(formatDisplayDate($("#invoice_date").val()) || '-');
        $("#review-consultation_fee").text($("#consultation_fee").val() || '0');
        $("#review-is_card_holder").text(isCardHolder ? 'Yes' : 'No');
        $("#review-card_number").text(isCardHolder ? ($("#card_number").val() || '-') : '-');
        $("#review-discount").text($("#discount").val() || '0');
        $("#review-paid_amount").text($("#paid_amount").val() || '0');
        $("#review-payment_mode").text($("#payment_mode").val() || '-');
        $("#review-remarks").text($("#remarks").val() || '-');

        transitioningToConfirm = true;

        $("#invoiceModal").modal("hide");

        new bootstrap.Offcanvas(
            document.getElementById("confirmInvoiceOffcanvas")
        ).show();
    }
);

$("#btnBackToEditInvoice").on("click", function () {

    bootstrap.Offcanvas.getInstance(
        document.getElementById("confirmInvoiceOffcanvas")
    )?.hide();

    $("#invoiceModal").modal("show");
});

$("#btnConfirmSaveInvoice").on("click", function () {

    let id =
        $("#edit-id").val();

    let url =
        id
            ? "/doctor-visit-invoice/update/" + id
            : "/doctor-visit-invoice/add";

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

            appointment_id:
                $("#appointment_id").val(),

            patient_id:
                $("#patient_id").val(),

            patient_name:
                $("#patient_name").val(),
            patient_age:
                $("#patient_age").val(),
            patient_gender:
                $("#patient_gender").val(),
            invoice_date:
                $("#invoice_date").val(),

            consultation_fee:
                $("#consultation_fee").val(),

            discount:
                $("#discount").val(),



            paid_amount:
                $("#paid_amount").val(),


            payment_mode:
                $("#payment_mode").val(),

            payment_reference:
                $("#payment_reference").val(),

            is_card_holder:
                $("#is_card_holder").is(":checked") ? 1 : 0,

            card_number:
                $("#card_number").val(),

            remarks:
                $("#remarks").val(),

            _token:
                $('meta[name="csrf-token"]')
                    .attr('content')
        },

        success: function (response) {

            if (
                parseFloat($("#discount").val()) > 0
            ) {
                $("#patient_name").prop("readonly", true);
            } else {
                $("#patient_name").prop("readonly", false);
            }

            if (response.invoice_id) {

                window.open(
                    '/doctor-visit-invoice/print/' +
                    response.invoice_id,
                    '_blank'
                );
            }

            Swal.fire({

                icon: "success",

                title: "Success",

                text: response.message
            });

            $("#invoiceForm")[0].reset();
            setFlatpickrValue("invoice_date", "");

            bootstrap.Offcanvas.getInstance(
                document.getElementById("confirmInvoiceOffcanvas")
            )?.hide();

            loadInvoices();
        },

        error: function (xhr) {

            console.log(xhr.responseText);

            Swal.fire({

                icon: "error",

                title: "Error",

                text:
                    xhr.responseJSON?.message ||
                    xhr.responseText
            });
        }
    });
});
$(document).on(
    "keyup change",
    "#card_number, #patient_name",
    function () {

        cardValidated = false;

        $("#card_validation_status").html('');
    }
);
/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/

$(document).on(
    "click",
    ".deleteBtn",
    function () {

        let id =
            $(this).data("id");

        Swal.fire({

            title: "Are you sure?",

            text: "Invoice will be cancelled and refund transaction will be created.",

            icon: "warning",

            input: "textarea",

            inputLabel: "Reason for cancellation",

            inputPlaceholder: "Why is this invoice being cancelled?",

            inputValidator: (value) => {
                if (!value || !value.trim()) {
                    return "Please enter a reason for cancellation.";
                }
            },

            showCancelButton: true,

            confirmButtonColor: "#d33",

            cancelButtonColor: "#3085d6",

            confirmButtonText: "Yes, cancel it!"

        }).then((result) => {

            if (result.isConfirmed) {

                $.ajax({

                    url:
                        "/doctor-visit-invoice/delete/" + id,

                    type: "DELETE",

                    data: {

                        _token:
                            $('meta[name="csrf-token"]')
                                .attr('content'),

                        reason: result.value
                    },

                    success: function (response) {

                        Swal.fire({

                            icon: response.status ? "success" : "error",

                            title: response.status ? "Cancelled" : "Error",

                            text: response.message
                        });

                        if (response.status) {
                            loadInvoices();
                        }
                    },

                    error: function (xhr) {

                        console.log(xhr.responseText);

                        Swal.fire({

                            icon: "error",

                            title: "Error",

                            text:
                                xhr.responseJSON?.message ||
                                "Unable to cancel invoice."
                        });
                    }
                });
            }
        });
    }
);

/*
|--------------------------------------------------------------------------
| AUTO CALCULATION
|--------------------------------------------------------------------------
*/

function calculatePayable() {

    let fee =
        parseFloat($("#consultation_fee").val()) || 0;

    let discount =
        parseFloat($("#discount").val()) || 0;

    let payable = fee - discount;

    if (payable < 0) {
        payable = 0;
    }

    $("#paid_amount").val(payable);
}

$(document).on(
    "keyup change",
    "#consultation_fee, #discount",
    calculatePayable
);
/*
|--------------------------------------------------------------------------
| CARD HOLDER
|--------------------------------------------------------------------------
*/
$(document).on(
    "change",
    "#is_card_holder",
    function () {

        if ($(this).is(":checked")) {

            $("#card_number_div").show();

            $("#validate_card_div").show();

            $("#card_number")
                .attr("required", true);

        } else {

            $("#card_number_div").hide();

            $("#validate_card_div").hide();

            $("#card_number")
                .removeAttr("required");

            $("#card_number").val('');

            $("#card_validation_status").html('');

            cardValidated = false;
        }
        
    }
);
/*
|--------------------------------------------------------------------------
| RESET MODAL
|--------------------------------------------------------------------------
*/

$("#invoiceModal").on(
    "hidden.bs.modal",
    function () {

        if (transitioningToConfirm) {

            transitioningToConfirm = false;
            return;
        }

        $("#invoiceForm")[0].reset();
        setFlatpickrValue("invoice_date", "");

        $("#edit-id").val('');
        cardValidated = false;

        $("#card_validation_status").html('');

        $("#patient_name").prop("readonly", false);
        $("#consultation_fee").prop("readonly", true);
        setInvoiceDateLocked(false);
        $("#payment_mode").prop("disabled", false);
        $("#paid_amount").prop("readonly", false);
        $("#is_card_holder").prop("disabled", false);
        $("#card_number").prop("readonly", false);
        $("#validateCardBtn").prop("disabled", false);

        $(".modal-title")
            .text("Add Invoice");
    }
);

/*
|--------------------------------------------------------------------------
| ADD BUTTON
|--------------------------------------------------------------------------
*/

$("#addInvoiceBtn").click(function () {

    $("#invoiceForm")[0].reset();
    setFlatpickrValue("invoice_date", "");

    $("#edit-id").val('');

    $(".modal-title")
        .text("Add Invoice");
});

/*
|--------------------------------------------------------------------------
| ADD / CHANGE PRIMARY / DEACTIVATE MOBILE -- acts on whichever patient is
| currently loaded into the invoice modal (#patient_id), same endpoints and
| flow as the Diagnostic Invoice / Patient History patient search.
|--------------------------------------------------------------------------
*/

$(document).on("click", "#btnAddMobileDV", async function () {

    let patientId = $("#patient_id").val();

    if (!patientId) {
        return;
    }

    // Bootstrap's modal focus-trap fights SweetAlert2's input for focus --
    // every click into the Swal input gets immediately refocused back into
    // #invoiceModal, so typing never registers. Hiding the Bootstrap modal
    // while any Swal is open (and reshowing it only once EVERY Swal in this
    // flow -- including the result confirmation below -- has fully closed)
    // avoids the conflict. Reshowing it earlier, while the result Swal is
    // still about to open, left both overlay systems fighting over the
    // page's scroll-lock state and froze the form underneath.
    // transitioningToConfirm tells the "hidden.bs.modal" handler below to
    // skip its usual form-reset -- this hide is temporary, not a real close
    // (same flag used when transitioning to the Confirm Review offcanvas).
    transitioningToConfirm = true;

    $("#invoiceModal").modal("hide");

    const result = await Swal.fire({
        title: "Add Mobile Number",
        html: `<input id="newMobileDV" class="swal2-input" placeholder="Enter mobile number" maxlength="15">`,
        icon: "info",
        showCancelButton: true,
        confirmButtonText: "Save",
        cancelButtonText: "Cancel",
        preConfirm: function () {
            const mobile = document.getElementById("newMobileDV").value;
            if (!mobile) {
                Swal.showValidationMessage("Mobile number is required");
                return false;
            }
            return mobile;
        }
    });

    if (!result.isConfirmed) {
        $("#invoiceModal").modal("show");
        return;
    }

    try {

        const response = await $.post(
            "/patient/add-mobile",
            {
                _token: $('meta[name="csrf-token"]').attr("content"),
                patient_id: patientId,
                mobile_no: result.value
            }
        );

        await Swal.fire({
            icon: response.status ? "success" : "error",
            title: response.status ? "Success" : "Error",
            text: response.message
        });

    } catch (xhr) {

        await Swal.fire({
            icon: "error",
            title: "Error",
            text: xhr.responseJSON?.message ?? "Could not add the mobile number."
        });
    }

    $("#invoiceModal").modal("show");
});

$(document).on("click", "#btnChangePrimaryMobileDV", async function () {

    let patientId = $("#patient_id").val();

    if (!patientId) {
        return;
    }

    // See #btnAddMobileDV above for why the Bootstrap modal is hidden here
    // (and not reshown until every Swal in this flow has fully closed), and
    // why transitioningToConfirm must be set first.
    transitioningToConfirm = true;

    $("#invoiceModal").modal("hide");

    const result = await Swal.fire({
        title: "Change Primary Mobile Number",
        html: `<input id="newPrimaryMobileDV" class="swal2-input" placeholder="Enter new primary mobile number" maxlength="10">`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Change",
        cancelButtonText: "Cancel",
        preConfirm: function () {
            const mobile = document.getElementById("newPrimaryMobileDV").value.trim();
            if (!/^[1-9][0-9]{9}$/.test(mobile)) {
                Swal.showValidationMessage("Enter a valid 10 digit mobile number (cannot start with 0)");
                return false;
            }
            return mobile;
        }
    });

    if (!result.isConfirmed) {
        $("#invoiceModal").modal("show");
        return;
    }

    try {

        const response = await $.post(
            "/patient/change-primary-mobile",
            {
                _token: $('meta[name="csrf-token"]').attr("content"),
                patient_id: patientId,
                mobile_no: result.value
            }
        );

        await Swal.fire({
            icon: response.status ? "success" : "error",
            title: response.status ? "Success" : "Error",
            text: response.message
        });

    } catch (xhr) {

        await Swal.fire({
            icon: "error",
            title: "Error",
            text: xhr.responseJSON?.message ?? "Could not change the primary mobile number."
        });
    }

    $("#invoiceModal").modal("show");
});

$(document).on("click", "#btnDeactivatePatientDV", async function () {

    let patientId = $("#patient_id").val();

    if (!patientId) {
        return;
    }

    const confirmResult = await Swal.fire({
        title: "Deactivate Patient?",
        icon: "warning",
        showCancelButton: true
    });

    if (!confirmResult.isConfirmed) {
        return;
    }

    try {

        const response = await $.post(
            "/patient/deactivate",
            {
                _token: $('meta[name="csrf-token"]').attr("content"),
                patient_id: patientId
            }
        );

        // Wait for the success alert to actually close before touching the
        // Bootstrap modal -- hiding it while this Swal is still open/closing
        // left both overlay systems fighting over the page's scroll-lock
        // state and froze the form underneath (same fix as
        // #btnAddMobileDV/#btnChangePrimaryMobileDV above).
        await Swal.fire("Success", response.message, "success");

    } catch (xhr) {

        await Swal.fire({
            icon: "error",
            title: "Error",
            text: xhr.responseJSON?.message ?? "Could not deactivate the patient."
        });

        return;
    }

    $("#invoiceModal").modal("hide");

    loadInvoices();
});

/*
|--------------------------------------------------------------------------
| PAGE LOAD
|--------------------------------------------------------------------------
*/

$(document).ready(function () {

    loadInvoices();

    loadAppointments();
});
