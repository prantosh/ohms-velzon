/*
|--------------------------------------------------------------------------
| GLOBALS
|--------------------------------------------------------------------------
*/
let rowIndex = 0;
window.isSeniorCitizen = false;
window.isEmployeeMember = false;
window.isInHousePatient = false;

// Populated per-category by the .category change handler; reused for
// client-side test-package expansion without extra AJAX round-trips.
let testsByCategory = {};
let testDataByCode = {};

// Every percent-based discount (Standard Discount %, Additional Discount %)
// is rounded to the nearest multiple of 10 once converted to a currency
// amount, independently of any other discount on the same line -- e.g. a
// 20% discount on a rate of 80 is 16, rounded to 20.
function roundToNearestTen(value) {
    return Math.round(value / 10) * 10;
}
/*
|--------------------------------------------------------------------------
| PAGE LOAD
|--------------------------------------------------------------------------
*/

$(document).ready(function () {
    $('#addRow').prop('disabled', true);
    disableInvoiceSection();

    if ($("#invoiceTable").length) {

        loadInvoices();
    }

    $(document).on(
        "click",
        "#invoiceCategoryTabs .nav-link",
        function () {

            $("#invoiceCategoryTabs .nav-link").removeClass("active");

            $(this).addClass("active");

            currentInvoiceCategory = $(this).data("category");

            loadInvoices();
        }
    );

    $(document).on(
        "click",
        "#invoiceDaysFilter button",
        function () {

            $("#invoiceDaysFilter button")
                .removeClass("btn-primary")
                .addClass("btn-outline-primary");

            $(this)
                .removeClass("btn-outline-primary")
                .addClass("btn-primary");

            currentInvoiceDays = parseInt($(this).data("days")) || 3;

            $("#invoiceDaysLabel").text(currentInvoiceDays);

            loadInvoices();
        }
    );

    if ($("#invoiceForm").length) {

        if (
            window.editMode !== true &&
            $("#testTable tbody tr").length === 0
        ) {
            addTestRow();
            setTimeout(function () {

                $('#testTable tbody tr.mainRow:last .category')
                    .css('pointer-events', 'auto')
                    .removeClass('bg-light');

            }, 100);
        }

        calculateTotal();
    }
});

/*
|--------------------------------------------------------------------------
| LIST PAGE
|--------------------------------------------------------------------------
*/

let currentInvoiceCategory = 'PATHOLOGY';
let currentInvoiceDays = 3;

function loadInvoices() {
    $.ajax({

        url: "/diagnostic-invoice/list",

        type: "GET",

        data: {
            category: currentInvoiceCategory,
            days: currentInvoiceDays
        },

        success: function (response) {
            if ($.fn.DataTable.isDataTable('#invoiceTable')) {

                $('#invoiceTable')
                    .DataTable()
                    .destroy();
            }

            $("#invoiceTable tbody").html('');

            $.each(
                response.data,
                function (i, row) {

                    let action = '';

                    if (row.cancelled === 'Y') {

                        action =
                            `<span class="badge bg-danger">
                            Cancelled
                        </span>`;
                    }
                    else {

                        action = `

                                <a href="${row.status === 'Paid' ? '#' : '/diagnostic-invoice/edit/' + row.id}"
                                   class="btn btn-warning btn-sm ${row.status === 'Paid' ? 'disabled' : ''}"
                                   title="${row.status === 'Paid' ? 'Paid Invoice Cannot Be Edited' : 'Pay Rest Payment'}">

                                   <i class="ri-edit-line"></i>

                                </a>

                                <a
                                    href="/diagnostic-invoice/print/${row.id}"
                                    target="_blank"
                                    class="btn btn-info btn-sm">

                                    <i class="ri-printer-line"></i>

                                </a>
                                <button
                                    class="btn btn-success btn-sm resendWhatsapp"
                                    data-id="${row.id}">
                                    <i class="ri-whatsapp-line"></i>
                                </button>
                                <button
                                    class="btn btn-danger btn-sm deleteBtn"
                                    data-id="${row.id}"
                                    data-invoice="${row.invoice_no}"
                                    data-patient="${row.patient_name}"
                                    data-refund="${row.paid_amount}">

                                    <i class="ri-delete-bin-line"></i>

                                </button>
                                `;
                    }

                    $("#invoiceTable tbody")
                        .append(`

                    <tr>

                        <td>${row.invoice_no ?? ''}</td>

                        <td>${row.patient_id ?? ''}</td>

                        <td>${row.patient_name ?? ''}</td>

                       

                        <td>${row.invoice_date ?? ''}</td>
                        <td>${row.test_date ?? ''}</td>
                        <td>
                            ${row.invoice_category === 'PATHOLOGY'
                                                    ? '<span class="badge bg-success">PATHOLOGY</span>'
                                                    : '<span class="badge bg-info">NON PATHOLOGY</span>'}
                        </td>

                        <td class="text-end">${row.total_amount ?? 0}</td>

                        <td class="text-end">${row.discount ?? 0}</td>

                        <td class="text-end">${row.paid_amount ?? 0}</td>

                        <td class="text-end">${row.due_amount ?? 0}</td>

                        <td class="text-end">${row.doctor_payment_amount ?? 0}</td>
                        

<td>

                        
    ${
                        row.cancelled === 'Y'
                            ? '<span class="badge bg-danger">Cancelled</span>'
                            : row.status === 'Paid'
                                ? '<span class="badge bg-success">Paid</span>'
                                : row.status === 'Partial'
                                    ? '<span class="badge bg-warning text-dark">Partial</span>'
                                    : row.status === 'Due'
                                        ? '<span class="badge bg-info text-dark">Due</span>'
                                        : `<span class="badge bg-secondary">${row.status ?? ''}</span>`
    }
</td>
<td>
                        ${
                        row.whatsapp_status === 'SENT'
                            ? '<span class="badge bg-success">Sent</span>'
                            : row.whatsapp_status === 'FAILED'
                                ? '<span class="badge bg-danger">Failed</span>'
                                : '<span class="badge bg-secondary">Pending</span>'
                        }
                        </td>

                        <td>${action}</td>

                    </tr>
                    `);
                });

            $('#invoiceTable')
                .DataTable({
                    responsive: true
                });
        }
    });
}

function loadPatientDoctorVisits(patientId) {

    $.get(
        '/diagnostic-invoice/patient-doctor-visits/' +
        patientId,
        function (rows) {

            let ddl = $('#doctor_visit_id');

            ddl.html(
                '<option value="">Direct Referral / Walk In</option>'
            );

            $.each(rows, function (i, row) {

                ddl.append(
                    `<option
                        value="${row.id}"
                        data-doctor="${row.doctor_name ?? ''}">
                        ${row.invoice_no} - ${row.invoice_date_fmt} - ${row.doctor_name ?? ''}
                    </option>`
                );
            });
        }
    );
}
$(document).on(
    'click',
    '.resendWhatsapp',
    function () {

        let id = $(this).data('id');
        let btn = $(this);

        btn.prop('disabled', true);

        Swal.fire({
            title: 'Please wait...',
            text: 'We are sending WhatsApp message',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        $.ajax({

            url:
                '/diagnostic-invoice/send-whatsapp/' +
                id,

            type: 'POST',

            data: {
                _token:
                    $('meta[name="csrf-token"]').attr('content')
            },

            success: function (response) {

                btn.prop('disabled', false);

                Swal.fire({
                    icon: 'success',
                    title: response.message
                });

                loadInvoices();
            },

            error: function (xhr) {

                btn.prop('disabled', false);

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text:
                        xhr.responseJSON?.message
                        || 'WhatsApp sending failed.'
                });
            }
        });
    }
);

$(document).on(
    'change',
    '#doctor_visit_id',
    function () {

        let doctorName =
            $(this)
                .find(':selected')
                .data('doctor') || '';

        if (doctorName !== '') {

            $('#referred_doctor')
                .val(doctorName)
                .prop('readonly', true);

        } else {

            $('#referred_doctor')
                .val('Dr. ')
                .prop('readonly', false);
        }

        recalculateStandardDiscountsForAllRows();
    }
);

/*
|--------------------------------------------------------------------------
| RECALCULATE STANDARD DISCOUNTS -- fires when the Doctor Visit Reference
| changes (selecting one grants In-House eligibility for this invoice; see
| Business Rule 3). Re-evaluates every current test row using the
| now-final eligibility state (Senior Citizen / Employee-Member already set
| at patient-selection time, In-House set here) and takes the higher of the
| age-tier and member-tier rate per row, same rule as populateTestRow().
|--------------------------------------------------------------------------
*/

function recalculateStandardDiscountsForAllRows() {

    window.isInHousePatient =
        $('#doctor_visit_id').val() !== '';

    $('#testTable tbody tr.mainRow').each(function () {

        let mainRow = $(this);

        // Auto-added package component rows are zero-rate placeholders,
        // not independently discountable.
        if (mainRow.attr('data-package-owner')) return;

        let itemCodeSub = mainRow.find('.test').val();
        let testData = testDataByCode[itemCodeSub];

        if (!testData) return;

        applyStandardDiscount(
            mainRow,
            parseFloat(testData.discount_percent) || 0,
            parseFloat(testData.discount_other_lab) || 0,
            parseFloat(testData.member_discount) || 0,
            parseFloat(testData.discount_member_other_lab) || 0
        );

        mainRow.find('.standardDiscount').trigger('change');
    });
}
/*
|--------------------------------------------------------------------------
| AUTO-CATEGORY -- staff adding several test lines in a row are usually
| staying within the same category (a pathology panel, several Cardiology
| tests, etc.), so pre-select each newly added row's category with
| whatever the most recently selected row used, instead of making them
| reselect it from the dropdown every time. Purely a convenience default --
| still just a normal (non-locked) selection, so it can be freely changed,
| and the existing "SAME CATEGORY VALIDATION" block in the .category change
| handler still enforces the one real hard rule (Pathology can't mix with
| non-Pathology) regardless of how the value was set.
|--------------------------------------------------------------------------
*/

function lastSelectedCategoryValue() {

    let lastValue = null;

    $('#testTable tbody tr.mainRow').each(function () {

        let value = $(this).find('.category').val();

        if (value) {
            lastValue = value;
        }
    });

    return lastValue;
}

/*
|--------------------------------------------------------------------------
| ADD TEST ROW
|--------------------------------------------------------------------------
*/

function addTestRow(applyAutoCategory = true) {
    rowIndex++;

    let html = `

<tr class="mainRow">

    <td>
        <select
            name="tests[${rowIndex}][item_code]"
            class="form-select category">

            ${window.categoryOptions}

        </select>
    </td>

    <td>

        <select
            name="tests[${rowIndex}][item_code_sub]"
            class="form-select test">

            <option value="">
                Select Test
            </option>

        </select>

        <input
            type="hidden"
            name="tests[${rowIndex}][test_name]"
            class="test_name">

        <input
            type="hidden"
            name="tests[${rowIndex}][package_parent_item_code_sub]"
            class="packageParentItemCodeSub">

    </td>

    <td>

        <input
            type="number"
            name="tests[${rowIndex}][rate]"
            class="form-control rate"
            readonly>

    </td>

    <td>

        <input
            type="number"
            name="tests[${rowIndex}][standard_discount]"
            class="form-control standardDiscount"
            value="0"
            readonly>

    </td>

    <td>

        <input
            type="number"
            name="tests[${rowIndex}][amount]"
            class="form-control amount"
            readonly>

    </td>

    <td>

        <select
            name="tests[${rowIndex}][doctor_id]"
            class="form-select doctorSelect">

            <option value="">
                Select Doctor / Staff
            </option>

        </select>

    </td>

    <td>

        <input
            type="number"
            name="tests[${rowIndex}][payment_value]"
            class="form-control paymentValue"
            readonly>

        <div class="form-check mt-1 waiveDoctorPaymentWrap" style="display:none;">

            <input
                type="checkbox"
                name="tests[${rowIndex}][doctor_payment_waived]"
                value="1"
                class="form-check-input waiveDoctorPayment"
                id="waive_${rowIndex}">

            <label class="form-check-label small" for="waive_${rowIndex}">
                Doctor waives payment
            </label>

        </div>

    </td>

    <td>

        <button
            type="button"
            class="btn btn-info btn-sm toggleDetails">

            ▼

        </button>

        <button
            type="button"
            class="btn btn-danger btn-sm removeRow">

            X

        </button>

    </td>

</tr>

<tr class="detailRow" style="display:none;">

<td colspan="8">

<div class="row g-2">

    <div class="col-md-3">

        <label>Addl Disc %</label>

        <input
            type="number"
            name="tests[${rowIndex}][additional_discount_percent]"
            class="form-control additionalDiscountPercent"
            value="0">

    </div>

    <div class="col-md-2">

        <label>Addl Amt</label>

        <input
            type="number"
            name="tests[${rowIndex}][additional_discount_amount]"
            class="form-control additionalDiscountAmount"
            value="0">

    </div>

    <div class="col-md-2">

        <label>Approved By</label>

        <select
            name="tests[${rowIndex}][discount_approved_by]"
            class="form-select discountApprovedBy">

            <option value="">
                Select
            </option>

            ${window.discountApproverOptions}

        </select>

    </div>

    <div class="col-md-2">

        <label>Remarks</label>

        <input
            type="text"
            name="tests[${rowIndex}][remarks]"
            class="form-control">

    </div>

</div>

</td>


</tr>

<tr class="reasonRow">

<td colspan="8" class="py-1">

    <small class="text-muted discountReasonText"></small>

</td>

</tr>
`;

    $("#testTable tbody")
        .append(html);

    $('#addRow').prop('disabled', true);
    $('#testTable tbody tr.mainRow .category')
        .addClass('locked-category');

    $('#testTable tbody tr.mainRow:last .category')
        .removeClass('locked-category');

    // Skipped for package-component rows (called with applyAutoCategory =
    // false from the package-expansion loop) -- that code deterministically
    // sets each component's category/test itself right after this call
    // returns. Triggering a real 'change' here would kick off the
    // .category handler's async tests-for-category AJAX call, whose
    // response lands *after* the expansion loop's own synchronous setup
    // and overwrites it -- wiping out the component's selected test and
    // leaving the last row incomplete ("Complete Previous Row First").
    if (applyAutoCategory) {

        let defaultCategory = lastSelectedCategoryValue();

        if (defaultCategory) {

            $('#testTable tbody tr.mainRow:last .category')
                .val(defaultCategory)
                .trigger('change');
        }
    }
}



$(document).on(
    'click',
    '.toggleDetails',
    function () {

        let detailsRow =
            $(this)
                .closest('tr')
                .next('.detailRow');

        detailsRow.toggle();

        $(this).text(
            detailsRow.is(':visible')
                ? '▲'
                : '▼'
        );
    }
);

/*
|--------------------------------------------------------------------------
| ADD ROW
|--------------------------------------------------------------------------
*/

$(document).on("click", "#addRow", function () {

    let lastRow =
        $('#testTable tbody tr.mainRow:last');

    let category =
        lastRow.find('.category').val();

    let test =
        lastRow.find('.test').val();

    if (!category || !test) {

        Swal.fire({
            icon: 'warning',
            title: 'Complete Previous Row First'
        });

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | LOCK PREVIOUS ROW CATEGORY
    |--------------------------------------------------------------------------
    */

    lastRow.find('.category')
        .addClass('locked-category');

    addTestRow();
});
function checkLastRowCompletion() {

    let lastMainRow =
        $('#testTable tbody tr.mainRow:last');

    if (lastMainRow.length === 0) {

        $('#addRow').prop('disabled', true);

        return;
    }

    let category =
        lastMainRow.find('.category').val();

    let test =
        lastMainRow.find('.test').val();

    $('#addRow').prop(
        'disabled',
        !(category && test)
    );
}
/*
|--------------------------------------------------------------------------
| REMOVE ROW
|--------------------------------------------------------------------------
*/

$(document).on(
    "click",
    ".removeRow",
    function () {

        let mainRow =
            $(this).closest('tr');

        // If this row is itself a package (its component rows are hidden,
        // not gone -- see the package-expansion loop), removing it must
        // take its components with it, or they'd linger in the DOM and
        // still get submitted with no visible trace on screen.
        removePackageComponentRows(mainRow);

        let detailRow =
            mainRow.next('.detailRow');

        detailRow.next('.reasonRow').remove();
        detailRow.remove();

        mainRow.remove();

        /*
        |--------------------------------------------------------------------------
        | LOCK ALL CATEGORIES
        |--------------------------------------------------------------------------
        */

        $('#testTable tbody tr.mainRow .category')
            .addClass('locked-category');

        /*
        |--------------------------------------------------------------------------
        | UNLOCK LAST ROW ONLY
        |--------------------------------------------------------------------------
        */

        $('#testTable tbody tr.mainRow:last .category')
            .removeClass('locked-category');

        calculateTotal();

        checkLastRowCompletion();
    }
);

/*
|--------------------------------------------------------------------------
| LOAD TESTS
|--------------------------------------------------------------------------
*/

$(document).on(
    "change",
    ".category",
    function () {

        let mainRow = $(this).closest('tr');

        let detailRow =
            mainRow.next('.detailRow');

        let categoryName =
            ($(this)
                .find(':selected')
                .data('category-name') || '')
                .toUpperCase();

        /*
        |--------------------------------------------------------------------------
        | MANUAL CATEGORY
        |--------------------------------------------------------------------------
        */

        if (categoryName.includes('MANUAL')) {

            mainRow.find('.rate')
                .val('')
                .prop('readonly', true);

            mainRow.find('.standardDiscount')
                .val(0)
                .prop('readonly', true);

            detailRow.find('.additionalDiscountPercent')
                .val(0)
                .prop('readonly', true);

            detailRow.find('.additionalDiscountAmount')
                .val(0)
                .prop('readonly', true);

            detailRow.find('.discountApprovedBy')
                .val('')
                .prop('disabled', true);

            mainRow.find('.amount')
                .val('')
                .prop('readonly', false);
        } else {

            mainRow.find('.rate')
                .prop('readonly', true);

            mainRow.find('.standardDiscount')
                .val(0)
                .prop('readonly', true);

            detailRow.find('.additionalDiscountPercent')
                .val(0)
                .prop('readonly', false);

            detailRow.find('.additionalDiscountAmount')
                .val(0)
                .prop('readonly', false);

            detailRow.find('.discountApprovedBy')
                .val('')
                .prop('disabled', false);

            mainRow.find('.amount')
                .prop('readonly', true);
        }

        let category = $(this).val();


        /*
|--------------------------------------------------------------------------
| SAME CATEGORY VALIDATION
|--------------------------------------------------------------------------
*/

        let firstCategoryName = null;

        $('#testTable tbody tr.mainRow').each(function () {

            let catName =
                (
                    $(this)
                        .find('.category option:selected')
                        .data('category-name') || ''
                ).toUpperCase();

            if (catName) {

                firstCategoryName = catName;

                return false;
            }
        });

        let currentCategoryName =
            (
                $(this)
                    .find(':selected')
                    .data('category-name') || ''
            ).toUpperCase();

        let firstIsPathology =
            firstCategoryName.includes('PATHOLOGY');

        let currentIsPathology =
            currentCategoryName.includes('PATHOLOGY');

        if (
            firstCategoryName &&
            firstIsPathology !== currentIsPathology
        ) {

            Swal.fire({
                icon: 'warning',
                title: 'Invalid Category',
                text:
                    firstIsPathology
                        ? 'Only Pathology categories can be selected.'
                        : 'Pathology category cannot be mixed with other categories.'
            });

            $(this).val('');

            return;
        }

        let dropdown =
            mainRow.find('.test');

        mainRow.find('.test').val('');
        mainRow.find('.amount').val('');
        mainRow.find('.rate').val('');
        mainRow.find('.test_name').val('');
        mainRow.find('.doctorSelect').html(
            '<option value="">Select Doctor / Staff</option>'
        );
        mainRow.find('.paymentValue').val('').removeData('waived-actual-value');
        mainRow.find('.amount').removeData('pre-waiver-amount');
        mainRow.find('.waiveDoctorPayment').prop('checked', false);
        mainRow.find('.waiveDoctorPaymentWrap').hide();
        calculateTotal();

        $.ajax({

            url:
                '/diagnostic-invoice/tests/' +
                category,

            type: 'GET',

            success: function (data) {

                testsByCategory[category] = data;

                $.each(data, function (i, row) {
                    testDataByCode[row.item_code_sub] = row;
                });

                buildTestDropdown(dropdown, data);
            }
        });
        checkLastRowCompletion();
    }

);

/*
|--------------------------------------------------------------------------
| BUILD TEST DROPDOWN OPTIONS (shared by category-change AJAX and
| test-package expansion, which reuses already-loaded category data)
|--------------------------------------------------------------------------
*/

function buildTestDropdown(dropdown, tests) {

    dropdown.html(`
        <option value="">
            Select Test
        </option>
    `);

    $.each(tests, function (i, row) {

        dropdown.append(`

            <option
                value="${row.item_code_sub}"
                data-rate="${row.rate}"
                data-name="${row.item_description_sub}"
                data-discount-percent="${row.discount_percent || 0}"
                data-discount-other-lab="${row.discount_other_lab || 0}"
                data-discount-member="${row.member_discount || 0}"
                data-discount-member-other-lab="${row.discount_member_other_lab || 0}">

                ${row.item_description_sub}

            </option>

        `);
    });
}


function enableInvoiceSection() {

    $('#testSection').show();
    $('#invoiceSection').show();
}

function disableInvoiceSection() {

    $('#testSection').hide();
    $('#invoiceSection').hide();
}


/*
|--------------------------------------------------------------------------
| TEST CHANGE
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| APPLY STANDARD DISCOUNT -- shared by populateTestRow() (new/changed test
| row) and recalculateStandardDiscountsForAllRows() (Doctor Visit Reference
| toggled). Senior Citizen / Member-Family eligibility always applies; an
| In-House link (Doctor Visit Reference selected) grants age-tier
| eligibility in its own right and overrides the result only if its rate is
| higher -- Math.max() naturally implements "override if higher, else keep
| existing". Writes .standardDiscount and the per-row reason line, and
| returns the numbers for the caller's own .amount calculation.
|--------------------------------------------------------------------------
*/

function applyStandardDiscount(mainRow, d1, d2, d3, d4) {

    let ageOrInHouseRate =
        (window.isSeniorCitizen || window.isInHousePatient)
            ? Math.max(d1, d2)
            : 0;

    let memberRate =
        window.isEmployeeMember
            ? Math.max(d3, d4)
            : 0;

    let standardDiscount =
        Math.max(ageOrInHouseRate, memberRate);

    let reasons = [];

    if (standardDiscount > 0) {

        if (ageOrInHouseRate === standardDiscount) {

            if (window.isSeniorCitizen) reasons.push('Senior Citizen');
            if (window.isInHousePatient) reasons.push('In-House Patient');
        }

        if (memberRate === standardDiscount) {
            reasons.push('Member / Family Member');
        }
    }

    let reasonText =
        reasons.length
            ? ('Std Discount ' + standardDiscount + '% applied under: ' + reasons.join(' + ') + ' category.')
            : '';

    mainRow.find('.standardDiscount')
        .val(standardDiscount);

    mainRow.next('.detailRow').next('.reasonRow')
        .find('.discountReasonText')
        .text(reasonText);

    return { standardDiscount, reasonText };
}

/*
|--------------------------------------------------------------------------
| POPULATE A ROW FROM TEST DATA (rate/discount/amount + doctor-payment
| lookup). Shared by direct test selection and test-package expansion.
| `testData` shape matches a getTests() row: item_code_sub,
| item_description_sub, rate, discount_percent, discount_other_lab,
| member_discount, discount_member_other_lab.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| MANUAL CATEGORY -- FALLBACK SPECIALISATION (for the "no preset
| doctor_test_payment_masters rule" branch of loadDoctorDropdownForItem()).
| Dental Manual items are all dental work, so when an item has no configured
| doctor_test_payment_masters row, the fallback offers every DENTAL-
| specialisation doctor instead, amount typed by hand. Every other manual
| category (e.g. Miscellaneous Manual) has no such fallback -- the doctor/
| staff dropdown there is driven strictly by doctor_test_payment_masters:
| only doctors/staff with a configured row for the selected item show up
| (a payment_value of 0 still counts as configured), and if none are
| configured the row simply has no doctor to pick, same as any regular
| test with no payment rule.
|--------------------------------------------------------------------------
*/

function getManualFallbackSpecialisation(categoryName) {

    return (categoryName.includes('MANUAL') && categoryName.includes('DENTAL'))
        ? 'DENTAL'
        : null;
}

function populateTestRow(mainRow, testData, forceZeroAmount = false) {

    let detailRow =
        mainRow.next('.detailRow');

    let rate =
        forceZeroAmount ? 0 : (parseFloat(testData.rate) || 0);

    let categoryName =
        (
            mainRow
                .find('.category option:selected')
                .data('category-name') || ''
        ).toUpperCase();

    loadDoctorDropdownForItem(mainRow, testData.item_code_sub, categoryName);

    let d1 =
        forceZeroAmount ? 0 : (parseFloat(testData.discount_percent) || 0);

    let d2 =
        forceZeroAmount ? 0 : (parseFloat(testData.discount_other_lab) || 0);

    let d3 =
        forceZeroAmount ? 0 : (parseFloat(testData.member_discount) || 0);

    let d4 =
        forceZeroAmount ? 0 : (parseFloat(testData.discount_member_other_lab) || 0);

    mainRow.find('.rate')
        .val(rate);

    detailRow.find('.additionalDiscountPercent')
        .val(0);

    detailRow.find('.additionalDiscountAmount')
        .val(0);

    detailRow.find('.discountApprovedBy')
        .val('');

    mainRow.find('.test_name')
        .val(testData.item_description_sub);

    // Senior Citizen / Member-Family eligibility applies to every row as
    // soon as it's known (no gating on the Doctor Visit Reference). If the
    // patient additionally has an In-House link (a Doctor Visit Reference
    // selected for this invoice), that grants age-tier eligibility in its
    // own right and overrides the currently-applied rate *only if higher*
    // -- see applyStandardDiscount(), which both this function and
    // recalculateStandardDiscountsForAllRows() share. Setting .standardDiscount
    // and triggering 'change' (rather than computing .amount inline here)
    // routes through the single shared formula below, which also enforces
    // the doctor-payable floor -- the patient's amount for a line can never
    // be discounted below what's owed to the doctor for it, if any.
    applyStandardDiscount(mainRow, d1, d2, d3, d4);

    mainRow.find('.standardDiscount').trigger('change');
}

/*
|--------------------------------------------------------------------------
| LOAD DOCTOR DROPDOWN FOR AN ITEM -- shared by populateTestRow() (regular,
| non-manual tests) and the manual-category branch of the .test change
| handler (manual categories skip populateTestRow() entirely, since there's
| no rate/discount to look up -- but they still need this doctor-payment
| lookup). `categoryName` is '' for regular tests, or the selected row's
| category name (upper-cased) for manual categories.
|
| For a MANUAL category, any doctor_test_payment_masters row found is only
| a starting suggestion, never a fixed preset: the total amount for a
| manual line is itself typed by hand (see the .rate handling in the .test
| change handler), so the doctor/staff's share of it has to be typeable
| too -- data-editable-payment="1" keeps .paymentValue editable instead of
| readonly once a doctor is picked (see the .doctorSelect handler below).
|--------------------------------------------------------------------------
*/

function loadDoctorDropdownForItem(mainRow, itemCodeSub, categoryName) {

    categoryName = categoryName || '';

    let isManualCategory =
        categoryName.includes('MANUAL');

    let manualFallbackSpecialisation =
        getManualFallbackSpecialisation(categoryName);

    // Any waiver in effect belonged to the previous doctor/fee for this
    // row -- reset before the dropdown (and paymentValue it depends on)
    // gets rebuilt below.
    mainRow.find('.amount').removeData('pre-waiver-amount');
    mainRow.find('.paymentValue').removeData('waived-actual-value');
    mainRow.find('.waiveDoctorPayment').prop('checked', false);
    mainRow.find('.waiveDoctorPaymentWrap').hide();

    $.get(
        '/diagnostic-invoice/doctor-payments/' +
        itemCodeSub,

        function (rows) {

            let doctorDropdown =
                mainRow.find('.doctorSelect');

            doctorDropdown.html('');
            doctorDropdown.removeClass('border border-danger');
            doctorDropdown.removeAttr('data-manual-payment');
            doctorDropdown.removeAttr('data-editable-payment');

            if (rows.length === 0) {

                /*
                |--------------------------------------------------------------------------
                | DENTAL MANUAL, NO PRESET RULE FOR THIS ITEM -- most Dental
                | Manual items have no doctor_test_payment_masters entry at
                | all, unlike other categories. Offer every DENTAL-
                | specialisation doctor instead, optional, with the payment
                | amount typed by hand (there's nothing to preset it from).
                | Other manual categories get no such fallback -- see
                | getManualFallbackSpecialisation().
                |--------------------------------------------------------------------------
                */

                if (manualFallbackSpecialisation) {

                    loadDentalDoctorsForRow(mainRow);

                    return;
                }

                mainRow.find('.paymentValue')
                    .val('')
                    .prop('readonly', true);

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | ONLY ALL DOCTORS RECORD
            |--------------------------------------------------------------------------
            */

            if (
                rows.length === 1 &&
                rows[0].doctor_id == 999
            ) {

                doctorDropdown.append(
                    '<option value="999">ALL DOCTORS</option>'
                );

                doctorDropdown.val('999');

                if (isManualCategory) {
                    doctorDropdown.attr('data-editable-payment', '1');
                }

                mainRow.find('.paymentValue')
                    .val(rows[0].payment_value)
                    .prop('readonly', !isManualCategory);

                return;
            }

            doctorDropdown.append(
                '<option value="">Select Doctor / Staff</option>'
            );

            /*
            |--------------------------------------------------------------------------
            | DOCTOR SELECTION REQUIRED
            |--------------------------------------------------------------------------
            */
            doctorDropdown.addClass('border border-danger');
            $.each(rows, function (i, row) {

                if (row.doctor_id != 999) {

                    doctorDropdown.append(
                        `<option
                            value="${row.doctor_id}"
                            data-payment="${row.payment_value}">
                            ${row.doctor_name}
                        </option>`
                    );
                }
            });

            if (isManualCategory) {
                doctorDropdown.attr('data-editable-payment', '1');
            }

            mainRow.find('.paymentValue').prop('readonly', !isManualCategory);
        }
    );
}

/*
|--------------------------------------------------------------------------
| DENTAL MANUAL -- DOCTOR LIST BY SPECIALISATION
|--------------------------------------------------------------------------
| Used only when getDoctorPayments() found no preset rule for the selected
| Dental Manual item. Doctor selection is optional here (unlike the normal,
| preset-rule flow where a doctor is required) and the payment amount, if
| any, is typed by hand once a doctor is picked -- see the .doctorSelect
| change handler below. Other manual categories (e.g. Miscellaneous Manual)
| have no such fallback -- see getManualFallbackSpecialisation().
|--------------------------------------------------------------------------
*/

function loadDentalDoctorsForRow(mainRow) {

    let doctorDropdown =
        mainRow.find('.doctorSelect');

    mainRow.find('.paymentValue')
        .val('')
        .prop('readonly', true);

    $.get(
        '/diagnostic-invoice/doctors-by-specialisation/DENTAL',

        function (doctors) {

            doctorDropdown.html(
                '<option value="">Select Doctor / Staff (Optional)</option>'
            );

            $.each(doctors, function (i, doctor) {

                doctorDropdown.append(
                    `<option value="${doctor.id}">
                        ${doctor.doctor_name}
                    </option>`
                );
            });

            doctorDropdown.attr('data-manual-payment', '1');
        }
    );
}

/*
|--------------------------------------------------------------------------
| REMOVE A PACKAGE'S PREVIOUSLY ADDED COMPONENT ROWS
|--------------------------------------------------------------------------
| Called whenever the row that a package was selected on changes to a
| different item (another package, a plain test, or is cleared) -- the
| stale component rows it added no longer apply and must not linger.
*/

function removePackageComponentRows(mainRow) {

    let token =
        mainRow.attr('data-package-token');

    if (!token) {
        return;
    }

    $('#testTable tbody tr[data-package-owner="' + token + '"]').each(function () {
        let detailRow = $(this).next('.detailRow');
        detailRow.next('.reasonRow').remove();
        detailRow.remove();
        $(this).remove();
    });

    mainRow.removeAttr('data-package-token');

    $('#testTable tbody tr.mainRow .category')
        .addClass('locked-category');

    $('#testTable tbody tr.mainRow:last .category')
        .removeClass('locked-category');

    calculateTotal();
    checkLastRowCompletion();
}

$(document).on(
    "change",
    ".test",
    function () {
        let selected =
            $(this)
                .find(':selected');

        let mainRow =
            $(this)
                .closest('tr');

        // Whatever this row was previously (a package with auto-added
        // components), it no longer applies now that the selection is
        // changing -- clear out its stale component rows first.
        removePackageComponentRows(mainRow);

        let categoryName =
            (
                mainRow.find('.category option:selected')
                    .data('category-name') || ''
            ).toUpperCase();

        let detailRow =
            mainRow.next('.detailRow');

        if (categoryName.includes('MANUAL')) {

            mainRow.find('.rate').val('');

            mainRow.find('.standardDiscount').val(0);

            detailRow.find('.additionalDiscountPercent').val(0);

            detailRow.find('.additionalDiscountAmount').val(0);

            detailRow.find('.discountApprovedBy').val('');

            mainRow.find('.test_name')
                .val(selected.data('name'));

            // Switching tests invalidates any waiver against the previous
            // test's fee -- clear it before the doctor dropdown is rebuilt
            // below.
            mainRow.find('.amount').removeData('pre-waiver-amount');
            mainRow.find('.paymentValue').removeData('waived-actual-value');
            mainRow.find('.waiveDoctorPayment').prop('checked', false);
            mainRow.find('.waiveDoctorPaymentWrap').hide();

            /*
            |--------------------------------------------------------------------------
            | MANUAL CATEGORIES -- some items in any manual category DO have
            | a configured doctor_test_payment_masters row (checked here same
            | as any regular test, via getDoctorPayments()), but the amount
            | stays editable rather than a locked-in preset -- see
            | loadDoctorDropdownForItem(). For Dental Manual specifically,
            | items with no configured row fall back to a doctor list,
            | optional, with the amount typed by hand -- other manual
            | categories get no such fallback. See loadDentalDoctorsForRow()
            | / getManualFallbackSpecialisation().
            |--------------------------------------------------------------------------
            */

            if ($(this).val()) {

                loadDoctorDropdownForItem(mainRow, $(this).val(), categoryName);
            }

            checkLastRowCompletion();
            calculateTotal();

            return;
        }

        let selectedCode = $(this).val();

        let testData = testDataByCode[selectedCode];

        /*
        |--------------------------------------------------------------------------
        | TEST PACKAGE -- billed as the package itself, at the package's own
        | rate (e.g. LIPID PROFILE = Rs.600 flat, regardless of what its
        | components would cost individually). Component tests are still
        | added as separate rows -- at Rs.0 each -- purely so every
        | component gets its own row for Test Result Entry to fill in.
        |--------------------------------------------------------------------------
        */

        if (testData && testData.is_package) {

            if (!testData.components || testData.components.length === 0) {

                Swal.fire({
                    icon: 'warning',
                    title: 'Empty Package',
                    text: 'This package has no component tests configured yet.'
                });

                $(this).val('');

                return;
            }

            let categoryCode =
                mainRow.find('.category').val();

            let categoryTests =
                testsByCategory[categoryCode] || [];

            // Tag this row with a fresh token so its component rows can be
            // found and removed as a set if the selection changes again.
            let packageToken =
                'pkg_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);

            mainRow.attr('data-package-token', packageToken);

            let packageItemCodeSub =
                mainRow.find('.test').val();

            // The row the user actually selected the package on stays the
            // billed package line, at the package's own rate/discounts.
            populateTestRow(mainRow, testData);

            // Every component gets its own added row, at zero rate, so it
            // can be resulted individually without adding to the bill. The
            // components of a package are fixed by Test Detail Master
            // configuration, not something staff can swap out mid-invoice,
            // so both dropdowns on these rows are locked immediately.
            for (let i = 0; i < testData.components.length; i++) {

                addTestRow(false);

                let newRow =
                    $('#testTable tbody tr.mainRow:last');

                newRow.attr('data-package-owner', packageToken);

                // Marks this row as a package component, not a separately
                // billed line -- invoice print excludes it, only the
                // package row above prints. Still saved to invoice_details
                // like any other row, since Test Result Entry needs its
                // own row per component.
                newRow.find('.packageParentItemCodeSub')
                    .val(packageItemCodeSub);

                newRow.find('.category').val(categoryCode);

                buildTestDropdown(newRow.find('.test'), categoryTests);

                newRow.find('.test').val(testData.components[i].item_code_sub);

                populateTestRow(newRow, testData.components[i], true);

                newRow.find('.category').addClass('locked-category');
                newRow.find('.test').addClass('locked-test');

                newRow.next('.detailRow')
                    .find('.additionalDiscountPercent, .additionalDiscountAmount, .discountApprovedBy')
                    .addClass('locked-discount');

                // Component rows are still fully part of the submitted
                // form (Test Result Entry / test reports need their own
                // invoice_details row per component, unchanged) -- just no
                // longer shown on the invoice-creation screen, since
                // listing every sub-item there was more clutter than help;
                // only the package's own row needs to be visible here.
                let componentDetailRow =
                    newRow.next('.detailRow');

                newRow.hide();
                componentDetailRow.hide();
                componentDetailRow.next('.reasonRow').hide();
            }

            checkLastRowCompletion();
            calculateTotal();

            return;
        }

        if (!testData) {

            checkLastRowCompletion();
            calculateTotal();

            return;
        }

        populateTestRow(mainRow, testData);

        checkLastRowCompletion();
        calculateTotal();
    }

);



$(document).on(
    'change',
    '.doctorSelect',
    function () {

        let tr =
            $(this).closest('tr');

        /*
        |--------------------------------------------------------------------------
        | DENTAL MANUAL, NO PRESET RULE -- doctor is optional and, once
        | picked, the payment amount is typed by hand instead of being
        | filled from a data-payment attribute (there isn't one).
        |--------------------------------------------------------------------------
        */

        if ($(this).attr('data-manual-payment') === '1') {

            let hasDoctor = !!$(this).val();

            tr.find('.paymentValue')
                .prop('readonly', !hasDoctor);

            if (!hasDoctor) {
                tr.find('.paymentValue').val('');
            }

            return;
        }

        let selected =
            $(this).find(':selected');

        let payment =
            selected.data('payment') || 0;

        tr.find('.paymentValue')
            .val(payment);

        /*
        |--------------------------------------------------------------------------
        | MANUAL CATEGORY WITH A CONFIGURED doctor_test_payment_masters ROW --
        | that value is only a starting suggestion (it's part of the total
        | amount typed by hand for this line), so keep it editable rather
        | than locking it read-only like a normal preset test fee.
        |--------------------------------------------------------------------------
        */

        if ($(this).attr('data-editable-payment') === '1') {

            tr.find('.paymentValue').prop('readonly', false);
        }

        if ($(this).val()) {

            $(this)
                .removeClass('border border-danger');
        }

        /*
        |--------------------------------------------------------------------------
        | DOCTOR WAIVES PAYMENT -- only offered when there's a real preset
        | fee to waive. Changing the doctor resets any previous waiver on
        | this row (undoing its amount reduction first) rather than
        | silently carrying a stale waiver over to a different doctor/fee.
        |--------------------------------------------------------------------------
        */

        let waiveCheckbox =
            tr.find('.waiveDoctorPayment');

        if (waiveCheckbox.is(':checked')) {
            waiveCheckbox.prop('checked', false).trigger('change');
        }

        tr.find('.waiveDoctorPaymentWrap')
            .toggle(parseFloat(payment) > 0);

        // Re-enforce the doctor-payable floor against this newly-picked
        // doctor's fee -- a discount already applied to this line (e.g.
        // an auto-applied Std Discount) must never leave the patient's
        // amount below what's now owed to the doctor.
        tr.find('.standardDiscount').trigger('change');
    }
);

/*
|--------------------------------------------------------------------------
| DENTAL MANUAL PAYMENT TYPED BY HAND -- same doctor-payable floor as a
| preset fee, just entered manually (no data-payment attribute to read
| from for this category -- see the .doctorSelect handler above).
|--------------------------------------------------------------------------
*/

$(document).on(
    'input change',
    '.paymentValue',
    function () {

        $(this)
            .closest('tr')
            .find('.standardDiscount')
            .trigger('change');
    }
);

/*
|--------------------------------------------------------------------------
| DOCTOR WAIVES PAYMENT -- reduces the patient's amount for this line by
| exactly the doctor's scheduled fee (the relief comes out of what the
| doctor would have earned, not an extra clinic discount). Reversible.
|--------------------------------------------------------------------------
*/

$(document).on(
    'change',
    '.waiveDoctorPayment',
    function () {

        let tr =
            $(this).closest('tr');

        let amountInput =
            tr.find('.amount');

        let paymentValueInput =
            tr.find('.paymentValue');

        let currentAmount =
            parseFloat(amountInput.val()) || 0;

        if ($(this).is(':checked')) {

            let payment =
                parseFloat(paymentValueInput.val()) || 0;

            amountInput.data(
                'pre-waiver-amount',
                currentAmount
            );

            amountInput.val(
                Math.max(0, currentAmount - payment)
            );

            // The submitted payment_value must stay the doctor's real
            // scheduled fee (kept in the DB for record-keeping), but the
            // on-screen field should read 0 so staff can see the waiver
            // took effect -- the real figure is restored just before the
            // form is serialized, in saveInvoice().
            paymentValueInput.data('waived-actual-value', payment);
            paymentValueInput.val(0);

        } else {

            let payment =
                paymentValueInput.data('waived-actual-value');

            if (payment !== undefined) {
                paymentValueInput.val(payment);
                paymentValueInput.removeData('waived-actual-value');
            } else {
                payment = parseFloat(paymentValueInput.val()) || 0;
            }

            let restored =
                amountInput.data('pre-waiver-amount');

            amountInput.val(
                restored !== undefined
                    ? restored
                    : currentAmount + payment
            );

            amountInput.removeData('pre-waiver-amount');
        }

        calculateTotal();
    }
);

/*
|--------------------------------------------------------------------------
| AMOUNT BECOMES RATE
|--------------------------------------------------------------------------
*/

$(document).on(
    "input change",
    ".standardDiscount, .additionalDiscountPercent, .additionalDiscountAmount",
    function () {

        // .standardDiscount now lives in the mainRow itself; the other two
        // fields still live in the collapsed detailRow -- resolve mainRow
        // accordingly depending on which field triggered this handler.
        let mainRow =
            $(this).hasClass('standardDiscount')
                ? $(this).closest('.mainRow')
                : $(this).closest('tr').prev('.mainRow');

        let detailRow =
            mainRow.next('.detailRow');

        let rate =
            parseFloat(
                mainRow.find('.rate').val()
            ) || 0;

        let standardDiscount =
            parseFloat(
                mainRow.find('.standardDiscount').val()
            ) || 0;

        let additionalPercent =
            parseFloat(
                detailRow.find('.additionalDiscountPercent').val()
            ) || 0;

        let additionalAmount =
            parseFloat(
                detailRow.find('.additionalDiscountAmount').val()
            ) || 0;

        let standardDiscountAmount =
            roundToNearestTen((rate * standardDiscount) / 100);

        let percentAmount =
            roundToNearestTen((rate * additionalPercent) / 100);

        let amount =
            rate
            - standardDiscountAmount
            - percentAmount
            - additionalAmount;

        amount = Math.max(0, amount);

        // The doctor's payable for this item is fixed regardless of
        // discount, so the discount can never push the payable amount
        // below what's owed to the doctor -- mirrors the server-side
        // check in DiagnosticInvoiceController::store(). Exempted when the
        // doctor is waiving their fee for this line -- there the amount is
        // *expected* to drop below it, by exactly that fee.
        let isWaived =
            mainRow.find('.waiveDoctorPayment').is(':checked');

        let doctorPayable =
            isWaived
                ? 0
                : (parseFloat(mainRow.find('.paymentValue').val()) || 0);

        if (doctorPayable > 0 && amount < doctorPayable) {

            amount = doctorPayable;

            Swal.fire({
                icon: 'warning',
                title: 'Discount Too High',
                text: 'The payable amount cannot go below the doctor\'s fixed payable of ' +
                    doctorPayable.toFixed(2) + '.',
                timer: 3000,
                showConfirmButton: false
            });
        }

        if (isWaived) {

            // Keep the waived reduction applied on top of whatever the
            // discount fields just computed, and refresh the restore point
            // so unchecking later still gives back the right amount. The
            // visible .paymentValue reads 0 while waived (see the
            // .waiveDoctorPayment handler), so the real fee comes from the
            // stashed data attribute instead.
            let payment =
                parseFloat(mainRow.find('.paymentValue').data('waived-actual-value')) || 0;

            mainRow.find('.amount').data('pre-waiver-amount', amount);

            amount = Math.max(0, amount - payment);
        }

        mainRow.find('.amount')
            .val(amount);

        calculateTotal();
    }
);

$(document).on(
    'change',
    '.discountApprovedBy',
    function () {

        if ($(this).val()) {

            $(this)
                .removeClass('border border-danger');
        }
    }
);
/*
|--------------------------------------------------------------------------
| DISCOUNT CHANGE
|--------------------------------------------------------------------------
*/



/*
|--------------------------------------------------------------------------
| TOTAL CALCULATION
|--------------------------------------------------------------------------
*/

function calculateTotal() {
    let total = 0;

    let totalDiscount = 0;

    $(".amount").each(function () {

        total +=
            parseFloat(
                $(this).val()
            ) || 0;
    });

    $("#testTable tbody tr.mainRow").each(function () {

        let mainRow = $(this);

        let detailRow =
            mainRow.next('.detailRow');

        let rate =
            parseFloat(
                mainRow.find('.rate').val()
            ) || 0;

        let standard =
            parseFloat(
                mainRow.find('.standardDiscount').val()
            ) || 0;

        let addlPercent =
            parseFloat(
                detailRow.find('.additionalDiscountPercent').val()
            ) || 0;

        let addlAmount =
            parseFloat(
                detailRow.find('.additionalDiscountAmount').val()
            ) || 0;

        // Must mirror the per-line .amount calculation in the
        // standardDiscount/additionalDiscountPercent handler above (and
        // DiagnosticInvoiceController::roundToNearestTen() server-side) --
        // otherwise Gross Amount (summed from already-rounded .amount
        // fields) and Total Discount disagree by the rounding difference
        // whenever a percent discount doesn't land on an exact multiple of 10.
        totalDiscount +=
            roundToNearestTen((rate * standard) / 100)
            + roundToNearestTen((rate * addlPercent) / 100)
            + addlAmount;
    });

    $("#total_amount")
        .val(total);

    $("#discount")
        .val(totalDiscount);

    let paid =
        parseFloat(
            $("#paid_amount").val()
        ) || 0;

    if (paid > total) {

        Swal.fire({
            icon: 'warning',
            title: 'Invalid Paid Amount',
            text: 'Paid amount cannot be greater than Gross Amount.'
        });

        $('#paid_amount').val(total);

        paid = total;
    }



    $("#due_amount")
        .val(
            total - paid
        );
}
function resetInvoiceDetails() {

    // A previous patient's discount eligibility must never carry over to
    // the next one selected in the same session.
    window.isSeniorCitizen = false;
    window.isEmployeeMember = false;
    window.isInHousePatient = false;

    // Clear test rows
    $("#testTable tbody").html('');

    // Add one blank row
    addTestRow();

    // Clear payment section
    $("#total_amount").val(0);
    $("#discount").val(0);
    $("#paid_amount").val('');
    $("#due_amount").val(0);

    $("#payment_mode").val('Cash');
    

    $("#remarks").val('');
}
/*
|--------------------------------------------------------------------------
| PAID AMOUNT
|--------------------------------------------------------------------------
*/
$(document).on(
    "input change",
    ".amount",
    function () {

        // MANUAL categories have no catalogue rate -- .amount is readonly
        // for every other category (only ever set via JS, never by direct
        // typing, so this never fires for them). The amount typed by hand
        // here *is* the rate for this transaction; keep .rate in sync so
        // the doctor-payable floor check (fires when a doctor/staff amount
        // is entered, via the .standardDiscount change handler) recomputes
        // amount = rate - discounts against the real typed figure instead
        // of a blank rate -- otherwise it zeroes this field out.
        if (!$(this).prop('readonly')) {
            $(this).closest('tr').find('.rate').val($(this).val());
        }

        calculateTotal();
    }
);
$(document).on(
    "keyup change",
    "#paid_amount",
    function () {
        calculateTotal();
    }
);

/*
|--------------------------------------------------------------------------
| REVIEW & CONFIRM (OFFCANVAS) - CREATE MODE ONLY
|--------------------------------------------------------------------------
*/

function showConfirmOffcanvas() {

    $('#review-patient_id').text($('#patient_id').val() || '-');
    $('#review-patient_name').text($('#patient_name').val() || '-');
    $('#review-patient_age_gender').text(
        ($('#patient_age').val() || '-') + ' / ' + ($('#patient_gender').val() || '-')
    );
    $('#review-patient_mobile_no').text($('#patient_mobile_no').val() || '-');
    $('#review-test_date').text($('#test_date').val() || '-');
    $('#review-doctor_visit_id').text(
        $('#doctor_visit_id option:selected').text() || '-'
    );
    $('#review-referred_doctor').text($('#referred_doctor').val() || '-');

    let tbody = $('#reviewTestsBody');

    tbody.html('');

    $('#testTable tbody tr.mainRow').each(function () {

        let categoryText =
            $(this).find('.category option:selected').text() || '-';

        let testText =
            $(this).find('.test option:selected').text() || '-';

        let amount =
            parseFloat($(this).find('.amount').val()) || 0;

        tbody.append(
            '<tr>' +
            '<td>' + categoryText + '</td>' +
            '<td>' + testText + '</td>' +
            '<td class="text-end">' + amount.toFixed(2) + '</td>' +
            '</tr>'
        );
    });

    $('#review-total_amount').text(
        (parseFloat($('#total_amount').val()) || 0).toFixed(2)
    );
    $('#review-discount').text(
        (parseFloat($('#discount').val()) || 0).toFixed(2)
    );
    $('#review-paid_amount').text(
        (parseFloat($('#paid_amount').val()) || 0).toFixed(2)
    );
    $('#review-due_amount').text(
        (parseFloat($('#due_amount').val()) || 0).toFixed(2)
    );
    $('#review-payment_mode').text($('#payment_mode').val() || '-');
    $('#review-remarks').text($('#remarks').val() || '-');

    new bootstrap.Offcanvas(
        document.getElementById('confirmDiagnosticOffcanvas')
    ).show();
}

$('#btnBackToEditDiagnostic').on('click', function () {

    bootstrap.Offcanvas.getInstance(
        document.getElementById('confirmDiagnosticOffcanvas')
    )?.hide();
});

$('#btnConfirmSaveDiagnostic').on('click', function () {

    bootstrap.Offcanvas.getInstance(
        document.getElementById('confirmDiagnosticOffcanvas')
    )?.hide();

    saveInvoice();
});

/*
|--------------------------------------------------------------------------
| DOCTOR PAYMENT WAIVER (EDIT MODE - DUE PAYMENT SCREEN)
|--------------------------------------------------------------------------
*/

function getWaiverReduction() {

    let total = 0;

    $('.waiveLine:checked').each(function () {
        total += parseFloat($(this).data('payment-value')) || 0;
    });

    return total;
}

// #total_amount / #due_amount are mutated in place as waive checkboxes are
// toggled, so their server-rendered starting values are stashed once (on
// first use) as the base every recompute works from -- otherwise a second
// toggle would reduce an already-reduced number instead of the original.
function getOriginalAmount(selector) {

    let el = $(selector);

    if (el.data('original') === undefined) {
        el.data('original', parseFloat(el.val()) || 0);
    }

    return el.data('original');
}

function getAdjustedDueAmount() {

    return parseFloat($('#due_amount').val()) || 0;
}

$(document).on('change', '.waiveLine', function () {

    // Reflect the waiver on this specific row too -- its own Amount and
    // Doctor Payment cells, not just the invoice-level totals -- so staff
    // can see exactly which line dropped and by how much.
    let tr = $(this).closest('tr');
    let payment = parseFloat($(this).data('payment-value')) || 0;
    let rowAmount = tr.find('.amount');
    let rowDoctorPayment = tr.find('.rowDoctorPayment');

    if (rowAmount.data('original') === undefined) {
        rowAmount.data('original', parseFloat(rowAmount.val()) || 0);
    }

    if (rowDoctorPayment.data('original') === undefined) {
        rowDoctorPayment.data('original', parseFloat(rowDoctorPayment.val()) || 0);
    }

    if ($(this).is(':checked')) {

        rowAmount.val(
            Math.max(0, rowAmount.data('original') - payment).toFixed(2)
        );

        rowDoctorPayment.val((0).toFixed(2));

    } else {

        rowAmount.val(rowAmount.data('original').toFixed(2));
        rowDoctorPayment.val(rowDoctorPayment.data('original').toFixed(2));
    }

    let reduction = getWaiverReduction();

    let newTotal =
        Math.max(0, getOriginalAmount('#total_amount') - reduction);

    let newDue =
        Math.max(0, getOriginalAmount('#due_amount') - reduction);

    $('#total_amount').val(newTotal.toFixed(2));
    $('#due_amount').val(newDue.toFixed(2));
    $('#additional_paid_amount').val(newDue.toFixed(2));
});

/*
|--------------------------------------------------------------------------
| REVIEW & CONFIRM (OFFCANVAS) - PAY REST PAYMENT (EDIT MODE)
|--------------------------------------------------------------------------
*/

function showConfirmOffcanvasDuePayment() {

    $('#review-due-due_amount').text(
        getAdjustedDueAmount().toFixed(2)
    );
    $('#review-due-additional_paid_amount').text(
        (parseFloat($('#additional_paid_amount').val()) || 0).toFixed(2)
    );
    $('#review-due-payment_mode').text($('#payment_mode').val() || '-');
    $('#review-due-remarks').text($('#remarks').val() || '-');

    new bootstrap.Offcanvas(
        document.getElementById('confirmDuePaymentOffcanvas')
    ).show();
}

$('#btnBackToEditDuePayment').on('click', function () {

    bootstrap.Offcanvas.getInstance(
        document.getElementById('confirmDuePaymentOffcanvas')
    )?.hide();
});

$('#btnConfirmDuePayment').on('click', function () {

    bootstrap.Offcanvas.getInstance(
        document.getElementById('confirmDuePaymentOffcanvas')
    )?.hide();

    saveInvoice();
});

/*
|--------------------------------------------------------------------------
| SAVE / UPDATE
|--------------------------------------------------------------------------
*/
function saveInvoice() {

    let url;

    if (
        typeof window.editMode !== 'undefined' &&
        window.editMode === true
    ) {

        url =
            "/diagnostic-invoice/update/" +
            window.invoiceId;

    } else {

        url =
            "/diagnostic-invoice/store";
    }
    $('#saveInvoiceBtn')
        .prop('disabled', true)
        .text('Saving...');

    // Waived rows show 0 in .paymentValue for the operator's benefit, but
    // the doctor's real scheduled fee must still be what's submitted (kept
    // in the DB for record-keeping) -- swap the true figure back in just
    // for serialization, then restore the 0 display afterwards.
    let waivedFields = [];

    $('.paymentValue').each(function () {

        let actual = $(this).data('waived-actual-value');

        if (actual !== undefined) {
            waivedFields.push($(this));
            $(this).val(actual);
        }
    });

    let formData = $("#invoiceForm").serialize();

    waivedFields.forEach(function (field) {
        field.val(0);
    });

    $.ajax({

        url: url,

        type: "POST",

        data: formData,

        success: function (response) {

            console.log(response);
            $('#saveInvoiceBtn')
                .prop('disabled', false)
                .text('Save Invoice');
            Swal.fire({
                icon: "success",
                title: "Success",
                text: response.message,
                confirmButtonText: "OK"
            }).then(() => {

                if (response.invoice_id) {

                    window.open(
                        '/diagnostic-invoice/print/' + response.invoice_id,
                        '_blank'
                    );
                }

                window.location.href = "/diagnostic-invoice/create";

            });
        },

        error: function (xhr) {

            console.log(xhr);
            $('#saveInvoiceBtn')
                .prop('disabled', false)
                .text('Save Invoice');
            Swal.fire({
                icon: "error",
                title: "Error",
                text:
                    xhr.responseJSON?.message ||
                    xhr.responseText
            });
        }
    });
}
$(document).on(
    "submit",
    "#invoiceForm",
    function (e) {

        e.preventDefault();

        /*
        |--------------------------------------------------------------------------
        | EDIT MODE - DUE PAYMENT COLLECTION
        |--------------------------------------------------------------------------
        */
        if (window.editMode === true) {

            let dueAmount =
                getAdjustedDueAmount();

            let additionalPaid =
                parseFloat($('#additional_paid_amount').val()) || 0;

            let paymentEditCount =
                parseInt($('#payment_edit_count').val()) || 0;

            /*
|--------------------------------------------------------------------------
| NON-PATHOLOGY RULE
|--------------------------------------------------------------------------
*/

            if (
                !window.isPathology &&
                paymentEditCount === 0 &&
                additionalPaid !== dueAmount
            ) {

                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Amount',
                    text: 'Payment must clear the entire Due Amount.'
                });

                return;
            }

            if (additionalPaid <= 0 && dueAmount > 0) {

                Swal.fire({
                    icon: 'warning',
                    title: 'Enter Payment Amount',
                    text: 'Please enter the amount received.'
                });

                return;
            }

            if (additionalPaid > dueAmount) {

                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Amount',
                    text: 'Payment amount cannot exceed Due Amount.'
                });

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | FIRST DUE PAYMENT
            |--------------------------------------------------------------------------
            */
            /*
|--------------------------------------------------------------------------
| PATHOLOGY - FIRST DUE PAYMENT
|--------------------------------------------------------------------------
*/

            if (
                window.isPathology &&
                paymentEditCount === 0 &&
                additionalPaid > dueAmount
            ) {

                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Amount',
                    text: 'Payment cannot exceed Due Amount.'
                });

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | FINAL DUE PAYMENT
            |--------------------------------------------------------------------------
            */
            if (
                window.isPathology &&
                paymentEditCount === 1 &&
                additionalPaid !== dueAmount
            ) {

                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Amount',
                    text: 'Final payment must clear the entire Due Amount.'
                });

                return;
            }
            if (
                !window.isPathology &&
                paymentEditCount >= 1
            ) {

                Swal.fire({
                    icon: 'warning',
                    title: 'Payment Complete',
                    text: 'Phase 2 payment is not allowed for this category.'
                });

                return;
            }
            showConfirmOffcanvasDuePayment();

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE MODE
        |--------------------------------------------------------------------------
        */

        let testSelected = false;

        $('#testTable tbody tr').each(function () {

            let testCode =
                $(this)
                    .find('.test')
                    .val();

            if (
                testCode &&
                testCode !== ''
            ) {

                testSelected = true;

                return false;
            }
        });

        if (!testSelected) {

            Swal.fire({
                icon: 'warning',
                title: 'No Test Selected',
                text: 'Please select at least one diagnostic test before saving.'
            });

            return;
        }
        let invalidRowNo = null;

        $('#testTable tbody tr.mainRow').each(function (index) {

            let category =
                $(this).find('.category').val();

            let test =
                $(this).find('.test').val();

            if (
                category &&
                category !== '' &&
                (!test || test === '')
            ) {

                invalidRowNo = index + 1;

                return false;
            }
        });

        if (invalidRowNo !== null) {

            Swal.fire({
                icon: 'warning',
                title: 'Incomplete Test Details',
                text:
                    'Test Name is required in Row ' +
                    invalidRowNo
            });

            return;
        }
        let grossAmount =
            parseFloat($('#total_amount').val()) || 0;

        if (grossAmount <= 0) {

            Swal.fire({
                icon: 'warning',
                title: 'Invalid Amount',
                text: 'Please select at least one valid test.'
            });

            return;
        }

        let paidAmount =
            parseFloat($('#paid_amount').val()) || 0;

        let totalDoctorPayment = 0;

        $('.paymentValue').each(function () {

            // A waived line has nothing to protect here -- exclude it,
            // same as the server-side sum in store().
            let isWaived =
                $(this).closest('tr').find('.waiveDoctorPayment').is(':checked');

            if (isWaived) {
                return;
            }

            totalDoctorPayment +=
                parseFloat($(this).val()) || 0;
        });

        let maxPartialPayment =
            grossAmount - totalDoctorPayment;

        /*
        |--------------------------------------------------------------------------
        | DOCTOR PAYMENT PROTECTION
        |--------------------------------------------------------------------------
        |
        | If part payment is made and doctor payment exists,
        | patient cannot pay more than:
        | Gross Amount - Doctor Payment
        |
        | Full payment is always allowed.
        |--------------------------------------------------------------------------
        */

        if (
            totalDoctorPayment > 0 &&
            paidAmount < grossAmount &&
            paidAmount > maxPartialPayment
        ) {

            Swal.fire({
                icon: 'warning',
                title: 'Invalid Part Payment',
                html:
                    'Maximum allowed part payment is <b>' +
                    maxPartialPayment.toFixed(2) +
                    '</b><br><br>' +
                    'Gross Amount : ' +
                    grossAmount.toFixed(2) +
                    '<br>' +
                    'Doctor Payment : ' +
                    totalDoctorPayment.toFixed(2)
            });

            return;
        }

        if (paidAmount > grossAmount) {

            Swal.fire({
                icon: 'warning',
                title: 'Invalid Paid Amount',
                text: 'Paid amount cannot be greater than Gross Amount.'
            });

            return;
        }
        let approvalMissing = false;

        $('#testTable tbody tr.mainRow').each(function () {

            let mainRow = $(this);

            let detailRow =
                mainRow.next('.detailRow');

            let addlPercent =
                parseFloat(
                    detailRow.find('.additionalDiscountPercent').val()
                ) || 0;

            let addlAmount =
                parseFloat(
                    detailRow.find('.additionalDiscountAmount').val()
                ) || 0;

            let approvedBy =
                detailRow.find('.discountApprovedBy').val();

            if (
                (addlPercent > 0 || addlAmount > 0)
                &&
                (!approvedBy || approvedBy === '')
            ) {

                approvalMissing = true;

                detailRow.show();

                mainRow
                    .find('.toggleDetails')
                    .text('▲');

                return false;
            }
        });

        if (approvalMissing) {

            Swal.fire({
                icon: 'warning',
                title: 'Approval Required',
                text: 'Select Approved By when Additional Discount is entered.'
            });

            return;
        }
        let doctorMissing = false;

        $('#testTable tbody tr.mainRow').each(function () {

            let mainRow = $(this);

            let doctorDropdown =
                mainRow.find('.doctorSelect');

            let doctorCount =
                doctorDropdown.find('option').length;

            let selectedDoctor =
                doctorDropdown.val();

            /*
            |--------------------------------------------------------------------------
            | If doctor list exists, selection is mandatory -- EXCEPT the
            | Dental Manual "no preset rule" fallback list
            | (data-manual-payment="1", see loadDentalDoctorsForRow()),
            | which is offered but always optional.
            |--------------------------------------------------------------------------
            */

            if (
                doctorDropdown.attr('data-manual-payment') === '1'
            ) {

                return;
            }

            if (
                doctorCount > 1 &&
                (!selectedDoctor || selectedDoctor === '')
            ) {

                doctorMissing = true;

                return false;
            }
        });

        if (doctorMissing) {

            Swal.fire({
                icon: 'warning',
                title: 'Doctor Selection Required',
                text: 'Please select a Doctor for all tests having doctor payment rules.'
            });

            return;
        }
        if (paidAmount === 0) {

            Swal.fire({
                icon: 'warning',
                title: 'Paid Amount is Zero',
                text: 'Do you want to continue and save this invoice?',
                showCancelButton: true,
                confirmButtonText: 'Yes, Save',
                cancelButtonText: 'No'
            }).then((result) => {

                if (result.isConfirmed) {

                    showConfirmOffcanvas();
                }
            });

            return;
        }
        showConfirmOffcanvas();
    }
);

$(document).on(
    "input change",
    ".additionalDiscountPercent, .additionalDiscountAmount",
    function () {

        let detailRow =
            $(this).closest('tr');

        let addlPercent =
            parseFloat(
                detailRow.find('.additionalDiscountPercent').val()
            ) || 0;

        let addlAmount =
            parseFloat(
                detailRow.find('.additionalDiscountAmount').val()
            ) || 0;

        let approved =
            detailRow.find('.discountApprovedBy');

        if (addlPercent > 0 || addlAmount > 0) {

            approved.prop('required', true);

            approved.addClass('border border-danger');

        } else {

            approved.prop('required', false);

            approved.removeClass('border border-danger');
        }
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
        

        

        let id = $(this).data('id');

        let invoiceNo = $(this).data('invoice');

        let patientName = $(this).data('patient');

        let refundAmount = $(this).data('refund') || 0;



        Swal.fire({
            title: 'Cancel Invoice?',
            html:
                '<b>Invoice No:</b> ' + invoiceNo + '<br>' +
                '<b>Patient Name:</b> ' + patientName + '<br>' +
                '<b>Refund Amount:</b> ₹ ' + refundAmount + '<br><br>' +
                'Refund transaction will be generated.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Cancel Invoice',
            cancelButtonText: 'No'
        }).then((result) => {

            if (result.isConfirmed) {
                $.ajax({

                    url:
                        '/diagnostic-invoice/delete/' +
                        id,

                    type:
                        'DELETE',

                    data:
                    {
                        _token:
                            $('meta[name="csrf-token"]')
                                .attr('content')
                    },

                    success:
                        function (response) {
                            Swal.fire({

                                icon:
                                    response.status ? 'success' : 'error',

                                title:
                                    response.status ? 'Success' : 'Error',

                                text:
                                    response.message
                            });

                            if (response.status) {
                                loadInvoices();
                            }
                        },

                    error:
                        function (xhr) {
                            Swal.fire({

                                icon:
                                    'error',

                                title:
                                    'Error',

                                text:
                                    xhr.responseJSON?.message ||
                                    'Unable to cancel invoice.'
                            });
                        }
                });
            }
        });
    }
);

$(document).on('click', '#searchPatient', function () {
    resetInvoiceDetails();
    $('#discountMessage').html('');
    $('#patientSection').hide();
    $('#patientSearchResult').hide();


    $('#patient_id').val('');
    disableInvoiceSection();
    $('#patient_name').val('');
    $('#patient_age').val('');

    $('#patient_gender').val('');

    $('#generatePatientIdBtn').hide();
    $('#formActionButtons').show();

    $('#invoiceSection').hide();
    let mobile =
        $('#search_mobile_no')
            .val()
            .trim();
    if (!/^[1-9][0-9]{9}$/.test(mobile)) {

        Swal.fire({
            icon: 'warning',
            title: 'Enter valid 10 digit mobile number',
            text: 'Mobile number must be exactly 10 digits and cannot start with 0'
        });

        return;
    }
    if (mobile === '') {

        Swal.fire({
            icon: 'warning',
            title: 'Enter Mobile Number'
        });

        return;
    }

    $('#patient_mobile_no')
        .val(mobile);

    $.post(
        '/diagnostic-invoice/search-patient',
        {
            _token:
                $('meta[name="csrf-token"]').attr('content'),
            mobile_no: mobile
        },
        function (response) {

            $('#patientSection').show();
            $('#patientSearchResult').show();

            if (response.patients.length > 0) {
                $('#addNewPatientContainer').show();
               

                $('#patientListBody').html('');

                $.each(response.patients, function (i, row) {

                                    $('#patientListBody').append(`

                        <tr
                            class="patientRow ${i % 2 === 0 ? 'table-light' : 'table-info'}"
                            data-patient-id="${row.patient_id}">

                            <td class="text-center">
                                ✓
                            </td>

                            <td>
                                ${row.patient_id}
                            </td>

                            <td>
                                <strong>
                                ${row.patient_name}
                                </strong>
                            </td>

                            <td class="text-center">
                                ${row.age ?? ''}
                            </td>

                            <td>
                                ${row.gender ?? ''}
                            </td>

                            <td>

                                <label class="me-3">

                                    <input
                                        type="checkbox"
                                        class="form-check-input addMobile"
                                        data-patient-id="${row.patient_id}">

                                    Add Another Mobile

                                </label>

                                <label>

                                    <input
                                        type="checkbox"
                                        class="form-check-input deactivatePatient"
                                        data-patient-id="${row.patient_id}">

                                    Deactivate 

                                </label>

                            </td>

                        </tr>

                    `);

                });

                $('#patientSearchResult').show();

               

                $('#patientSearchResult').show();
            }
            else {

                Swal.fire({
                    icon: 'question',
                    title: 'Mobile Number Not Found',
                    text: 'Do you want to create a new Patient ID?',
                    showCancelButton: true,
                    confirmButtonText: 'Yes',
                    cancelButtonText: 'No'
                }).then((result) => {

                    if (result.isConfirmed) {

                        $('#addNewPatientContainer').hide();

                        $('#patientSection').show();

                        $('#patientSearchResult').hide();

                        $('#patient_id').val('');

                        disableInvoiceSection();

                        $('#patient_name')
                            .val('')
                            .prop('readonly', false);

                        $('#patient_age')
                            .val('')
                            .prop('readonly', false);

                        $('#patient_gender')
                            .val('')
                            .prop('disabled', false);

                        $('#generatePatientIdBtn')
                            .show();

                        $('#formActionButtons').hide();

                        $('#patient_name').focus();

                    } else {

                        $('#patientSection').hide();
                        $('#patientSearchResult').hide();

                        $('#patient_id').val('');
                        $('#patient_name').val('');
                        $('#patient_age').val('');
                        $('#patient_gender').val('');

                        disableInvoiceSection();
                    }
                });
            }
        }
    );
});
$(document).on('input', '#search_mobile_no', function () {

    this.value = this.value.replace(/\D/g, '');

    if (this.value.length > 10) {
        this.value = this.value.substring(0, 10);
    }
});
/*
|--------------------------------------------------------------------------
| MOBILE NUMBER VALIDATION + TAB SEARCH
|--------------------------------------------------------------------------
*/

$(document).on('keydown', '#search_mobile_no', function (e) {

    if (e.key === 'Tab') {

        let mobile = $(this).val().trim();

        if (!/^[1-9][0-9]{9}$/.test(mobile)) {

            e.preventDefault();

            Swal.fire({
                icon: 'warning',
                title: 'Enter valid 10 digit mobile number',
                text: 'Mobile number cannot start with 0'
            });

            return false;
        }

        e.preventDefault();

        $('#searchPatient').trigger('click');
    }
});


$(document).on(
    'click',
    '#addNewPatientForMobile',
    function () {

        $('#patientSearchResult').hide();

        $('#patientSection').show();

        $('#patient_id').val('');

        $('#patient_name')
            .val('')
            .prop('readonly', false);

        $('#patient_age')
            .val('')
            .prop('readonly', false);

        $('#patient_gender')
            .val('')
            .prop('disabled', false);

        $('#generatePatientIdBtn')
            .show();

        $('#formActionButtons').hide();

        disableInvoiceSection();

        $('#patient_name').focus();
    }
);
$(document).on(
    'click',
    '.patientRow',
    function () {

        let patientId =
            $(this).data('patient-id');

        resetInvoiceDetails();

        $.get(
            '/diagnostic-invoice/get-patient/' +
            patientId,

            function (response) {

                let p =
                    response.patient;

                $('#patient_id')
                    .val(p.patient_id);
                loadPatientDoctorVisits(patientId);

                $('#patient_name')
                    .val(p.patient_name)
                    .prop('readonly', true);

                $('#patient_age')
                    .val(p.age);

                $('#patient_gender')
                    .val(p.gender)
                    .prop('disabled', false);

                $('#generatePatientIdBtn')
                    .hide();

                $('#formActionButtons').show();

                $('#patientSearchResult')
                    .hide();

                enableInvoiceSection();

                $.get(
                    '/diagnostic-invoice/patient-discount/' + patientId,

                    function (d) {

                        if (d.messages && d.messages.length > 0) {

                            $('#discountMessage').html(
                                d.messages.join(' | ')
                            );

                        } else {

                            $('#discountMessage').html(
                                'Patient is not eligible for any standard discount.'
                            );
                        }

                        window.isEmployeeMember = d.is_member;
                        window.isSeniorCitizen = parseInt(p.age) >= 60;
                    }
                );
            }
        );
    }
);

$(document).on(
    'click',
    '.addMobile, .deactivatePatient',
    function (e) {

        e.stopPropagation();

    }
);

$(document).on(
    'change',
    '.addMobile',
    async function () {

        let patientId =
            $(this).data('patient-id');

        let checkbox =
            $(this);

        const result = await Swal.fire({

            title: 'Add Mobile Number',

            html: `
                <input
                    id="newMobile"
                    class="swal2-input"
                    placeholder="Enter mobile number"
                    maxlength="15">
            `,

            icon: 'info',

            showCancelButton: true,

            confirmButtonText: 'Save',

            cancelButtonText: 'Cancel',

            preConfirm: () => {

                const mobile =
                    document.getElementById('newMobile').value;

                if (!mobile) {

                    Swal.showValidationMessage(
                        'Mobile number is required'
                    );

                    return false;
                }

                return mobile;
            }
        });

        if (!result.isConfirmed) {

            checkbox.prop(
                'checked',
                false
            );

            return;
        }

        $.post(
            '/patient/add-mobile',
            {
                _token:
                    $('meta[name="csrf-token"]').attr('content'),

                patient_id:
                    patientId,

                mobile_no:
                    result.value
            },

            function (response) {

                Swal.fire({

                    icon:
                        response.status
                            ? 'success'
                            : 'error',

                    title:
                        response.status
                            ? 'Success'
                            : 'Error',

                    text:
                        response.message
                });

                checkbox.prop(
                    'checked',
                    false
                );
            }
        );
    }
);

$(document).on(
    'change',
    '.deactivatePatient',
    function () {

        let patientId =
            $(this).data('patient-id');

        let checkbox =
            $(this);

        Swal.fire({

            title:
                'Deactivate Patient?',

            icon:
                'warning',

            showCancelButton:
                true

        }).then((result) => {

            if (
                result.isConfirmed
            ) {

                $.post(
                    '/patient/deactivate',

                    {
                        _token:
                            $('meta[name="csrf-token"]').attr('content'),

                        patient_id:
                            patientId
                    },

                    function (response) {

                        Swal.fire(
                            'Success',
                            response.message,
                            'success'
                        );

                        $('#searchPatient')
                            .click();
                    }
                );

            } else {

                checkbox.prop(
                    'checked',
                    false
                );
            }
        });
    }
);


$(document).on(
    'click',
    '#generatePatientIdBtn',
    function () {

        if (
            $('#patient_name').val().trim() === '' ||
            $('#patient_age').val().trim() === '' ||
            $('#patient_gender').val() === ''
        ) {

            Swal.fire({
                icon: 'warning',
                title: 'Enter Name, Age and Gender'
            });

            return;
        }

        $.get(
            '/patient/generate-id',
            {
                mobile_no:
                    $('#patient_mobile_no').val()
            },
            function (response) {

                $('#patient_id')
                    .val(response.patient_id);

                enableInvoiceSection();

                $('#generatePatientIdBtn')
                    .hide();

                $('#formActionButtons').show();

                $('#invoiceSection')
                    .show();

                // SAVE PATIENT IMMEDIATELY

                $.ajax({

                    url: '/diagnostic-invoice/save-patient',

                    type: 'POST',

                    data: {

                        _token:
                            $('meta[name="csrf-token"]').attr('content'),

                        patient_id:
                            response.patient_id,

                        patient_name:
                            $('#patient_name').val(),

                        mobile_no:
                            $('#patient_mobile_no').val(),

                        age:
                            $('#patient_age').val(),

                        gender:
                            $('#patient_gender').val()
                    },

                    success: function (r) {

                        console.log(
                            'SAVE PATIENT SUCCESS',
                            r
                        );

                      //  alert('Patient Saved');

                        $.ajax({

                            url: '/diagnostic-invoice/new-patient-discount',

                            type: 'POST',

                            data: {

                                _token:
                                    $('meta[name="csrf-token"]').attr('content'),

                                age:
                                    $('#patient_age').val(),

                                mobile_no:
                                    $('#patient_mobile_no').val(),

                                patient_name:
                                    $('#patient_name').val()
                            },

                            success: function (d) {

                                if (
                                    d.messages &&
                                    d.messages.length > 0
                                ) {

                                    $('#discountMessage').html(
                                        d.messages.join(' | ')
                                    );

                                } else {

                                    $('#discountMessage').html(
                                        'Patient is not eligible for any standard discount.'
                                    );
                                }

                                window.isEmployeeMember =
                                    d.is_member;

                                window.isSeniorCitizen =
                                    parseInt($('#patient_age').val()) >= 60;
                            },

                            error: function (xhr) {

                                alert(
                                    'DISCOUNT ERROR: ' +
                                    xhr.status
                                );

                                console.log(xhr.responseText);
                            }
                        });
                    },

                    error: function (xhr) {

                        console.log(
                            'SAVE PATIENT ERROR'
                        );

                        console.log(xhr);

                        alert(xhr.responseText);
                    }
                });
            }
        );
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
        let id =
            $(this).data('id');

        $.ajax({

            url:
                '/diagnostic-invoice/print/' +
                id,

            type:
                'GET',

            success:
                function (response) {
                    if (
                        response.status
                    ) {
                        window.open(
                            response.pdf_url,
                            '_blank'
                        );
                    }
                }
        });
    }
);
