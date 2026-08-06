"use strict";

/* ============================================================
 | Doctor History Report
 | Version : 2.0
 | Laravel : 13
 | Author  : OpenAI
 ============================================================ */

$(function () {

    //----------------------------------------------------------
    // Routes
    //----------------------------------------------------------

    const routes = window.doctorHistoryRoutes;

    //----------------------------------------------------------
    // DataTable Instance
    //----------------------------------------------------------

    let table = null;

    //----------------------------------------------------------
    // Page Initializer
    //----------------------------------------------------------

    initialize();

    //----------------------------------------------------------
    // Initialize Everything
    //----------------------------------------------------------

    function initialize() {

        initializeDatePickers();

        initializeDataTable();

        bindEvents();

    }

    //----------------------------------------------------------
    // Flatpickr
    //----------------------------------------------------------

    function initializeDatePickers() {

        $(".flatpickr").flatpickr({

            dateFormat: "Y-m-d",

            allowInput: true

        });

    }

    //----------------------------------------------------------
    // DataTable
    //----------------------------------------------------------

    function initializeDataTable() {

        if ($.fn.DataTable.isDataTable("#doctorHistoryTable")) {

            $("#doctorHistoryTable").DataTable().destroy();

        }

        table = $("#doctorHistoryTable").DataTable({

            destroy: true,

            processing: false,

            serverSide: false,

            searching: false,

            ordering: true,

            paging: true,

            info: true,

            responsive: true,

            autoWidth: false,

            pageLength: 25,

            lengthMenu: [

                [10, 25, 50, 100, -1],

                [10, 25, 50, 100, "All"]

            ],

            language: {

                emptyTable: "No Doctor History Found"

            }

        });

    }

    //----------------------------------------------------------
    // Event Binder
    //----------------------------------------------------------

  
    //----------------------------------------------------------
    // Loader
    //----------------------------------------------------------

    function showLoader() {

        const modalEl = document.getElementById("loadingModal");

        if (!modalEl) return;

        let instance = bootstrap.Modal.getInstance(modalEl);

        if (!instance) {
            instance = new bootstrap.Modal(modalEl);
        }

        instance.show();
    }

    function hideLoader() {

        console.log("hideLoader executed");

        const modalEl = document.getElementById("loadingModal");

        // Bootstrap
        const modal = bootstrap.Modal.getInstance(modalEl);

        if (modal) {
            modal.hide();
            modal.dispose();
        }

        // Force hide
        $("#loadingModal").removeClass("show");
        $("#loadingModal").css("display", "none");
        $("#loadingModal").attr("aria-hidden", "true");

        $(".modal-backdrop").remove();

        $("body")
            .removeClass("modal-open")
            .css({
                overflow: "",
                paddingRight: ""
            });
    }

    //----------------------------------------------------------
    // Money Formatter
    //----------------------------------------------------------

   

    //----------------------------------------------------------
    // Summary Cards
    //----------------------------------------------------------

    function updateSummary(summary) {

        summary = summary || {};

        $("#totalPatients").text(summary.total_patients || 0);

        $("#malePatients").text(summary.male_patients || 0);

        $("#femalePatients").text(summary.female_patients || 0);

        $("#totalPatientsFooter").text(summary.total_patients || 0);

        

       

    }

    //----------------------------------------------------------
    // Reset Summary
    //----------------------------------------------------------

    function resetSummary() {

        updateSummary({

            total_patients: 0,

            male_patients: 0,

            female_patients: 0,

           

        });
        $("#totalPatientsFooter").text(0);
    }

    //----------------------------------------------------------
    // Reset Grid
    //----------------------------------------------------------

    function resetGrid() {

        if (table) {

            table.clear().draw(false);

        }

        resetSummary();

    }

    //----------------------------------------------------------
    // Build DataTable Rows
    //----------------------------------------------------------

    function buildRows(rows) {

        let data = [];

        let sl = 1;

        $.each(rows, function (i, row) {

            data.push([

                sl++,

                row.visit_date,

                row.visit_time,

                row.invoice_no,

                row.patient_name,

                row.patient_gender,

                row.patient_age,

                row.patient_mobile_no,

                row.doctor_name,

                row.specialisation,

                

                row.actions

            ]);

        });

        return data;

    }
    //----------------------------------------------------------
    // Search Doctor History
    //----------------------------------------------------------

    function searchDoctorHistory() {

        $.ajax({

            url: routes.search,

            type: "POST",

            dataType: "json",

            beforeSend: function () {

                showLoader();

            },

            data: {

                _token: $('meta[name="csrf-token"]').attr("content"),

                doctor_id: $("#doctor_id").val(),

                from_date: $("#from_date").val(),

                to_date: $("#to_date").val()

            },

            success: function (response) {

                try {

                    if (!response.success) {

                        Swal.fire({
                            icon: "warning",
                            title: "Warning",
                            text: response.message || "No data found."
                        });

                        resetGrid();
                        return;
                    }
                    console.log("Table draw completed");
                    updateSummary(response.summary);

                    let rows = buildRows(response.data);

                    table.clear();
                    table.rows.add(rows);
                    table.draw(false);

                } finally {

                    hideLoader();
                    console.log($("#loadingModal").attr("class"));
                }

            },

            error: function (xhr) {

                try {

                    let message = "Unable to load doctor history.";

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }

                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: message
                    });

                    resetGrid();

                } finally {

                    hideLoader();

                }

            }

        });

    }

    //----------------------------------------------------------
    // Bind Events
    //----------------------------------------------------------

    function bindEvents() {

        //------------------------------------------------------
        // Search
        //------------------------------------------------------

        $("#btnSearch").off("click").on("click", function (e) {

            e.preventDefault();

            searchDoctorHistory();

        });

        //------------------------------------------------------
        // Reset
        //------------------------------------------------------

        $("#btnReset").off("click").on("click", function (e) {

            e.preventDefault();

            $("#doctorHistoryForm")[0].reset();

            resetGrid();

        });

        //------------------------------------------------------
        // Enter Key Search
        //------------------------------------------------------

        $("#doctorHistoryForm").off("keypress").on("keypress", function (e) {

            if (e.which === 13) {

                e.preventDefault();

                searchDoctorHistory();

            }

        });

        //------------------------------------------------------
        // PDF
        //------------------------------------------------------

        $("#btnPdf").off("click").on("click", function () {

            let url = routes.pdf + "?" + $.param({

                doctor_id: $("#doctor_id").val(),

                from_date: $("#from_date").val(),

                to_date: $("#to_date").val()

            });

            window.open(url, "_blank");

        });

        //------------------------------------------------------
        // Excel
        //------------------------------------------------------

        $("#btnExcel").off("click").on("click", function () {

            let url = routes.excel + "?" + $.param({

                doctor_id: $("#doctor_id").val(),

                from_date: $("#from_date").val(),

                to_date: $("#to_date").val()

            });

            window.location.href = url;

        });

        //------------------------------------------------------
        // Print
        //------------------------------------------------------

        $("#btnPrint").off("click").on("click", function () {

            let url = routes.pdf + "?" + $.param({

                doctor_id: $("#doctor_id").val(),

                from_date: $("#from_date").val(),

                to_date: $("#to_date").val()

            });

            window.open(url, "_blank");

        });

        //------------------------------------------------------
        // Initial Load
        //------------------------------------------------------

        searchDoctorHistory();

    }
    //----------------------------------------------------------
    // WhatsApp
    //----------------------------------------------------------

    $(document).on("click", ".sendWhatsapp", function () {

        let id = $(this).data("id");

        showLoader();

        $.ajax({

            url: routes.whatsapp + "/" + id,

            type: "GET",

            dataType: "json",

            success: function (response) {

                Swal.fire({

                    icon: response.status ? "success" : "error",

                    title: response.status ? "Success" : "Error",

                    text: response.message

                });

            },

            error: function () {

                Swal.fire({

                    icon: "error",

                    title: "Error",

                    text: "Unable to send WhatsApp."

                });

            },

            complete: function () {

                hideLoader();

            }

        });

    });


    //----------------------------------------------------------
    // Edit
    //----------------------------------------------------------

    $(document).on("click", ".editInvoice", function () {

        let id = $(this).data("id");

        window.location.href =

            routes.editInvoice + "/" + id;

    });


    //----------------------------------------------------------
    // Cancel
    //----------------------------------------------------------

    $(document).on("click", ".cancelInvoice", function () {

        let id = $(this).data("id");

        Swal.fire({

            title: "Cancel Invoice?",

            text: "Are you sure?",

            icon: "warning",

            showCancelButton: true,

            confirmButtonText: "Yes",

            cancelButtonText: "No"

        }).then(function (result) {

            if (!result.isConfirmed) {

                return;

            }

            showLoader();

            $.ajax({

                url: routes.cancelInvoice + "/" + id,

                type: "DELETE",

                data: {

                    _token: $('meta[name="csrf-token"]').attr("content")

                },

                success: function (response) {

                    Swal.fire({

                        icon: response.status ? "success" : "error",

                        title: response.status ? "Success" : "Error",

                        text: response.message

                    });

                    searchDoctorHistory();

                },

                error: function () {

                    Swal.fire({

                        icon: "error",

                        title: "Error",

                        text: "Unable to cancel invoice."

                    });

                },

                complete: function () {

                    hideLoader();

                }

            });

        });

    });

});
