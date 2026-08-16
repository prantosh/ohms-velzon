"use strict";

/*=========================================================
 Doctor Payment Register
=========================================================*/

const DoctorPayableRegister = {

    routes: window.reportRoutes || {},

    form: null,

    reportBody: null,

    summary: null,

    initialize: function () {

        this.form = $("#doctorPaymentHistoryForm");

        this.reportBody = $("#reportBody");

        this.bindEvents();

        this.initializeDatePicker();

    },

    /*=====================================================
        EVENTS
    =====================================================*/

    bindEvents: function () {

        const self = this;


        $("#btnSearch").on("click", function () {

            self.search();

        });

        $("#btnReset").on("click", function () {

            self.reset();

        });

        $("#btnPdf").on("click", function () {

            self.pdf();

        });

        $("#btnExcel").on("click", function () {

            self.excel();

        });

        

    },

    /*=====================================================
        DATE PICKER
    =====================================================*/

    initializeDatePicker: function () {

        if ($(".flatpickr").length) {

            $(".flatpickr").flatpickr({

                dateFormat: "Y-m-d",

                altInput: true,

                altFormat: "d-m-Y",

                allowInput: true

            });

        }

    },

    /*=====================================================
        SEARCH
    =====================================================*/

    search: function () {

        const self = this;

        Loader.show("Loading Doctor Payables...");
        $.ajax({

            url: self.routes.search,

            method: "POST",

            data: self.form.serialize(),

            dataType: "json",

            headers: {

                "X-CSRF-TOKEN":
                    $('meta[name="csrf-token"]').attr("content")

            },

            success: function (response) {

                Loader.hide();

                if (!response.success) {

                    Swal.fire({

                        icon: "error",

                        title: "Error",

                        text: "Unable to load report."

                    });

                    return;

                }

                self.render(response);

            },

            error: function (xhr) {

                Loader.hide();

                let message = "Unknown Error";

                if (xhr.responseJSON &&
                    xhr.responseJSON.message) {

                    message = xhr.responseJSON.message;

                }

                Swal.fire({

                    icon: "error",

                    title: "Error",

                    text: message

                });

            }

        });

    },

    /*=====================================================
        RENDER COMPLETE REPORT
    =====================================================*/

    render: function (response) {

        this.reportBody.empty();

        if (!response.groups ||
            response.groups.length === 0) {

            this.showNoData();

            this.clearSummary();

            return;

        }

        this.renderGroups(response.groups);

        this.updateSummary(response.summary);

    },

    /*=====================================================
        GROUP RENDER
    =====================================================*/

    renderGroups: function (groups) {

        const self = this;

        let html = "";

        groups.forEach(function (group) {

            html += self.renderDoctorHeader(group);

            html += self.renderRows(group);


        });

        self.reportBody.html(html);

    },
    /*=====================================================
        DOCTOR HEADER
    =====================================================*/

    renderDoctorHeader: function (group) {

        return `

        <tr class="table-primary">

            <td colspan="15">

                <div class="d-flex justify-content-between">

                    <div>

                        <h5 class="mb-1">

                            <i class="ri-user-heart-line"></i>

                            ${group.doctor_name}

                        </h5>

                        

                    </div>

                    <div class="text-end">

                        <div>

                            <strong>

                                Transactions :

                            </strong>

                            ${group.transaction_count}

                        </div>

                        <div>

                            <strong>

                                Total Payable :

                            </strong>

                            ₹ ${this.currency(group.payable_amount)}

                        </div>

                    </div>

                </div>

            </td>

        </tr>

    `;

    },

    /*=====================================================
        DETAIL ROWS
    =====================================================*/

    renderRows: function (group) {

        let html = "";

        const self = this;

        group.rows.forEach(function (row, index) {

            let badge = "bg-secondary";

            switch (row.payment_status) {

                case "PENDING":
                    badge = "bg-warning";
                    break;

                case "APPROVED":
                    badge = "bg-info";
                    break;

                case "PARTIALLY_PAID":
                    badge = "bg-primary";
                    break;

                case "PAID":
                    badge = "bg-success";
                    break;

                case "CANCELLED":
                    badge = "bg-danger";
                    break;

                default:
                    badge = "bg-secondary";
            }

            html += `

<tr>

<td>${index + 1}</td>

<td>${row.payable_no}</td>

<td>${row.payable_date}</td>

<td>${row.collection_due ? `<span class="text-danger fw-semibold">${row.user_name}</span>` : (row.user_name ?? '')}</td>

<td>${row.invoice_no}</td>

<td>

<span class="badge bg-primary">

${row.invoice_type}

</span>

</td>

<td>

${row.patient_name}

</td>

<td>

${row.item_description}

</td>

<td class="text-end">

₹ ${self.currency(row.payable_amount)}

</td>

<td class="text-center">

<span class="badge ${badge}">

${row.payment_status}

</span>

</td>

<td class="text-end">

₹ ${self.currency(row.paid_amount)}

</td>

<td>

${row.paid_date ?? ''}

</td>

<td class="text-center">

${row.actions}

</td>

</tr>

`;

        });

        return html;

    },

    /*=====================================================
        GROUP FOOTER
    =====================================================*/

   
    /*=====================================================
    CURRENCY
=====================================================*/

    currency: function (amount) {

        amount = parseFloat(amount);

        if (isNaN(amount))
            amount = 0;

        return amount.toLocaleString(

            "en-IN",

            {

                minimumFractionDigits: 2,

                maximumFractionDigits: 2

            }

        );

    },

    /*=====================================================
        DATE
    =====================================================*/

    formatDate: function (date) {

        if (!date)
            return "";

        return date;

    },
    /*=====================================================
    UPDATE SUMMARY
=====================================================*/

    updateSummary: function (summary) {

        summary = summary || {};

        $("#totalDoctors").text(summary.total_doctors);

        $("#pendingAmount").text(
            "₹ " + this.currency(summary.pending_amount)
        );

        $("#approvedAmount").text(
            "₹ " + this.currency(summary.approved_amount)
        );

        $("#grandPayable").text(
            "₹ " + this.currency(summary.grand_payable)
        );

        $("#footerDoctors").text(summary.total_doctors);

        $("#footerPending").text(
            "₹ " + this.currency(summary.pending_amount)
        );

        $("#footerApproved").text(
            "₹ " + this.currency(summary.approved_amount)
        );

        $("#footerGrand").text(
            "₹ " + this.currency(summary.grand_payable)
        );

    },

    /*=====================================================
        CLEAR SUMMARY
    =====================================================*/

    clearSummary: function () {

        this.updateSummary({

            total_doctors: 0,

            total_transactions: 0,

            total_payment: 0,

            average_payment: 0

        });

    },

    /*=====================================================
        NO DATA
    =====================================================*/

    showNoData: function () {

        this.reportBody.html(`

        <tr>

            <td colspan="11"
                class="text-center text-muted py-5">

                <i class="ri-inbox-line fs-2 d-block mb-2"></i>

                No records found.

            </td>

        </tr>

    `);

    },

    /*=====================================================
        RESET
    =====================================================*/

    reset: function () {

        this.form[0].reset();

        this.initializeDatePicker();

        this.clearSummary();

        this.showNoData();

        $("#doctor_id").focus();

    },
    /*=====================================================
    PDF
=====================================================*/

    pdf: function () {

        window.open(

            this.routes.pdf +

            "?" +

            this.form.serialize(),

            "_blank"

        );

    },
    /*=====================================================
    EXCEL
=====================================================*/

    excel: function () {

        window.location =

            this.routes.excel +

            "?" +

            this.form.serialize();

    },
    /*=====================================================
    PRINT
=====================================================*/

    print: function () {

        window.open(

            this.routes.print +

            "?" +

            this.form.serialize(),

            "_blank"

        );

    },
    /*=====================================================
    AJAX ERROR
=====================================================*/

    ajaxError: function (xhr) {

        let message =

            "Unexpected Error";

        if (

            xhr.responseJSON &&

            xhr.responseJSON.message

        ) {

            message =

                xhr.responseJSON.message;

        }

        Swal.fire({

            icon: "error",

            title: "Error",

            text: message

        });

    },
    /*=====================================================
        WHATSAPP
    =====================================================*/

    bindDynamicEvents: function () {

        const self = this;

        $(document).off("click", ".printDoctorVisitBtn");

        $(document).on("click", ".printDoctorVisitBtn", function () {

            const id = $(this).data("id");

            // The endpoint streams the PDF directly (no disk save, no JSON
            // wrapper) -- same as every other "print" action in this app.
            window.open("/doctor-visit-invoice/print/" + id, "_blank");
        });

        $(document).off("click", ".sendWhatsapp");

        $(document).on("click", ".sendWhatsapp", function (e) {

            e.preventDefault();

            const url = $(this).attr("href");

            Swal.fire({

                title: "Send WhatsApp?",

                text: "Invoice will be sent to patient's WhatsApp.",

                icon: "question",

                showCancelButton: true,

                confirmButtonText: "Send"

            }).then(function (result) {

                if (!result.isConfirmed)
                    return;

                Loader.show("Sending WhatsApp...");

                $.ajax({

                    url: url,

                    type: "GET",

                    success: function (response) {

                        Loader.hide();

                        Swal.fire({

                            icon: response.status ? "success" : "error",

                            title: response.status ? "Success" : "Failed",

                            text: response.message ||
                                (response.status
                                    ? "WhatsApp sent successfully."
                                    : "Unable to send WhatsApp.")

                        });

                    },

                    error: function (xhr) {

                        Loader.hide();

                        let message = "Unable to send WhatsApp.";

                        if (xhr.responseJSON &&
                            xhr.responseJSON.message) {

                            message = xhr.responseJSON.message;

                        }

                        Swal.fire({

                            icon: "error",

                            title: "Error",

                            text: message

                        });

                    }

                });

            });

        });

    },

    /*=====================================================
        LOADER WRAPPER
    =====================================================*/

    showLoader: function (message) {

        if (window.Loader) {

            Loader.show(message || "Loading...");

        }

    },

    hideLoader: function () {

        if (window.Loader) {

            Loader.hide();

        }

    },

    /*=====================================================
        INITIALIZE PAGE
    =====================================================*/

    start: function () {

        this.initialize();

        this.bindDynamicEvents();

    }

};


/*=====================================================
    DOCUMENT READY
=====================================================*/

window.addEventListener("load", function () {

    if (typeof $ === "undefined") {
        console.error("jQuery not loaded.");
        return;
    }

    DoctorPayableRegister.start();

});
