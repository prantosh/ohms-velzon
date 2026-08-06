let currentPage = 1;
let lastPage = 1;
let perPage = 10;
let statusFilter = '';
let rentalSettings = { rates: {}, categories: [] };
let lastSettlementPreview = null;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

function resetPatientSection() {

    document.querySelector('#patientResultsWrap').style.display = 'none';
    document.querySelector('#patientResultsBody').innerHTML = '';

    document.querySelector('#patient_id-field').value = '';

    let nameField = document.querySelector('#patient_name-field');
    nameField.value = '';
    nameField.readOnly = true;

    let ageField = document.querySelector('#patient_age-field');
    ageField.value = '';
    ageField.readOnly = true;

    let genderField = document.querySelector('#patient_gender-field');
    genderField.value = '';
    genderField.disabled = true;

    document.querySelector('#generatePatientIdBtn').style.display = 'none';
}

function selectExistingPatient(patient) {

    document.querySelector('#patientResultsWrap').style.display = 'none';

    document.querySelector('#patient_id-field').value = patient.patient_id;

    document.querySelector('#patient_name-field').value = patient.patient_name;
    document.querySelector('#patient_name-field').readOnly = true;

    // Name stays locked for an existing patient, but age/gender are common
    // enough to have been recorded wrong (or simply be out of date) that
    // staff can correct them right here -- issue() propagates the
    // correction back to the patients table.
    document.querySelector('#patient_age-field').value = patient.age ?? '';
    document.querySelector('#patient_age-field').readOnly = false;

    document.querySelector('#patient_gender-field').value = patient.gender ?? '';
    document.querySelector('#patient_gender-field').disabled = false;

    document.querySelector('#generatePatientIdBtn').style.display = 'none';
}

function enableNewPatientEntry() {

    document.querySelector('#patientResultsWrap').style.display = 'none';

    document.querySelector('#patient_id-field').value = '';

    let nameField = document.querySelector('#patient_name-field');
    nameField.value = '';
    nameField.readOnly = false;

    let ageField = document.querySelector('#patient_age-field');
    ageField.value = '';
    ageField.readOnly = false;

    document.querySelector('#patient_gender-field').value = '';
    document.querySelector('#patient_gender-field').disabled = false;

    document.querySelector('#generatePatientIdBtn').style.display = 'block';

    nameField.focus();
}

document.getElementById('search_mobile_no').addEventListener('input', function () {

    this.value = this.value.replace(/\D/g, '').substring(0, 10);
});

document.getElementById('searchPatientBtn').addEventListener('click', async function () {

    let mobile = document.querySelector('#search_mobile_no').value.trim();

    if (!/^[1-9][0-9]{9}$/.test(mobile)) {

        Swal.fire({
            icon: 'warning',
            title: 'Enter valid 10 digit mobile number',
            text: 'Mobile number must be exactly 10 digits and cannot start with 0'
        });

        return;
    }

    resetPatientSection();

    const response = await fetch('/diagnostic-invoice/search-patient', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `mobile_no=${encodeURIComponent(mobile)}`
    });

    const result = await response.json();

    if (result.patients.length > 0) {

        let tbody = document.querySelector('#patientResultsBody');

        result.patients.forEach(row => {

            tbody.innerHTML += `
            <tr class="patientRow" style="cursor:pointer"
                data-patient-id="${row.patient_id}"
                data-patient-name="${row.patient_name}"
                data-age="${row.age ?? ''}"
                data-gender="${row.gender ?? ''}">

                <td>${row.patient_id}</td>
                <td>${row.patient_name}</td>
                <td>${row.age ?? ''}</td>
                <td>${row.gender ?? ''}</td>

            </tr>
            `;
        });

        document.querySelector('#patientResultsWrap').style.display = 'block';

        return;
    }

    let confirm = await Swal.fire({
        icon: 'question',
        title: 'Mobile Number Not Found',
        text: 'Do you want to create a new Patient ID?',
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No'
    });

    if (confirm.isConfirmed) {
        enableNewPatientEntry();
    }
});

document.getElementById('patientResultsBody').addEventListener('click', function (e) {

    let row = e.target.closest('.patientRow');

    if (!row) return;

    selectExistingPatient({
        patient_id: row.dataset.patientId,
        patient_name: row.dataset.patientName,
        age: row.dataset.age,
        gender: row.dataset.gender
    });
});

document.getElementById('generatePatientIdBtn').addEventListener('click', async function () {

    let mobile = document.querySelector('#search_mobile_no').value.trim();
    let name = document.querySelector('#patient_name-field').value.trim();
    let age = document.querySelector('#patient_age-field').value.trim();
    let gender = document.querySelector('#patient_gender-field').value;

    if (!name || !age || !gender) {

        Swal.fire({
            icon: 'warning',
            title: 'Enter Name, Age and Gender'
        });

        return;
    }

    const idResponse = await fetch(`/patient/generate-id?mobile_no=${encodeURIComponent(mobile)}`);

    const idResult = await idResponse.json();

    document.querySelector('#patient_id-field').value = idResult.patient_id;

    await fetch('/diagnostic-invoice/save-patient', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
            patient_id: idResult.patient_id,
            patient_name: name,
            mobile_no: mobile,
            age: age,
            gender: gender
        })
    });

    document.querySelector('#generatePatientIdBtn').style.display = 'none';
});

async function loadSettings() {

    const response = await fetch('/equipment-rental/settings');

    rentalSettings = await response.json();

    let select = document.querySelector('#category_id-field');

    select.innerHTML = '<option value="">Select Category</option>';

    rentalSettings.categories.forEach(category => {

        select.innerHTML += `
        <option value="${category.id}" data-available="${category.available_quantity}">
            ${category.category_name} (Available: ${category.available_quantity})
        </option>
        `;
    });

    let approverOptions = '<option value="">Select</option>';

    (rentalSettings.approvers ?? []).forEach(approver => {
        approverOptions += `<option value="${approver.id}">${approver.name}</option>`;
    });

    document.querySelector('#discount_approved_by-field').innerHTML = approverOptions;
    document.querySelector('#return_discount_approved_by-field').innerHTML = approverOptions;

    recalculateAdvance();
}

function toggleDiscountApprovalRequirement(discountFieldId, approvedByFieldId, asteriskId) {

    let discountAmount = parseFloat(document.querySelector(discountFieldId).value) || 0;

    let approvedByField = document.querySelector(approvedByFieldId);
    let asterisk = document.querySelector(asteriskId);

    approvedByField.required = discountAmount > 0;
    asterisk.style.display = discountAmount > 0 ? 'inline' : 'none';
}

document.getElementById('discount_amount-field').addEventListener('input', function () {
    toggleDiscountApprovalRequirement('#discount_amount-field', '#discount_approved_by-field', '#approved-by-required');
});

document.getElementById('return_discount_amount-field').addEventListener('input', function () {
    toggleDiscountApprovalRequirement('#return_discount_amount-field', '#return_discount_approved_by-field', '#return-approved-by-required');
});

function getMinimumAdvance() {

    const rentalType = document.querySelector('#rental_type-field').value;

    const quantity = parseInt(document.querySelector('#quantity-field').value) || 0;

    const rates = rentalSettings.rates;

    if (!rentalType || !rates) return 0;

    let perUnit = rentalType === 'OXYGEN_RENT'
        ? (rates.oxygen_min_advance ?? 0)
        : (rates.concentrator_min_advance ?? 0);

    return perUnit * quantity;
}

function recalculateAdvance() {

    document.querySelector('#advance_amount-field').value =
        getMinimumAdvance().toFixed(2);

    toggleLowAdvanceReason();
}

function toggleLowAdvanceReason() {

    const advanceAmount = parseFloat(document.querySelector('#advance_amount-field').value) || 0;

    const minimum = getMinimumAdvance();

    const wrap = document.querySelector('#lowAdvanceReasonWrap');
    const reasonField = document.querySelector('#low_advance_reason-field');

    const isLow = minimum > 0 && advanceAmount < minimum;

    wrap.style.display = isLow ? 'block' : 'none';
    reasonField.required = isLow;

    if (!isLow) {
        reasonField.value = '';
    }
}

async function loadRentals(page = 1) {
    currentPage = page;

    const response = await fetch(
        `/equipment-rental/list?page=${page}&per_page=${perPage}&status=${statusFilter}`
    );

    const result = await response.json();

    let tbody = document.querySelector('#rentalTable tbody');

    tbody.innerHTML = '';

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText =
        `Page ${result.pagination.current_page}`;

    document.querySelector('#pagination-info').innerText =
        `Total Records : ${result.pagination.total}`;

    result.data.forEach(raw => {

        let typeLabel = raw.invoice_type === 'OXYGEN_RENT'
            ? 'Oxygen Cylinder'
            : 'Concentrator';

        let statusBadgeClass = {
            'Issued': 'bg-warning',
            'Returned': 'bg-success',
            'Cancelled': 'bg-danger'
        }[raw.status] ?? 'bg-secondary';

        let actions = '';

        if (raw.status === 'Issued') {

            actions += `
            <button class="btn btn-sm btn-soft-success return-btn me-1"
                    data-id="${raw.id}"
                    data-type="${raw.invoice_type}"
                    title="Return">
                <i class="ri-arrow-go-back-line"></i> Return
            </button>

            <button class="btn btn-sm btn-soft-danger cancel-btn"
                    data-id="${raw.id}"
                    title="Cancel">
                <i class="ri-close-line"></i>
            </button>
            `;

        } else {

            actions = '-';
        }

        let advanceWhatsappBadge = {
            'SENT': '<span class="badge bg-success">WhatsApp Sent</span>',
            'FAILED': '<span class="badge bg-danger">WhatsApp Failed</span>'
        }[raw.whatsapp_status] ?? '<span class="badge bg-secondary">WhatsApp Pending</span>';

        let advanceInvoiceActions = `
            <a href="/equipment-rental/print/${raw.id}" target="_blank" class="btn btn-sm btn-soft-info me-1" title="Print">
                <i class="ri-printer-line"></i>
            </a>
            <button class="btn btn-sm btn-soft-success resend-whatsapp-btn" data-id="${raw.id}" title="Send WhatsApp">
                <i class="ri-whatsapp-line"></i>
            </button>
            <br>${advanceWhatsappBadge}
        `;

        let finalInvoiceActions = '-';

        if (raw.final_invoice_id) {

            let finalWhatsappBadge = {
                'SENT': '<span class="badge bg-success">WhatsApp Sent</span>',
                'FAILED': '<span class="badge bg-danger">WhatsApp Failed</span>'
            }[raw.final_whatsapp_status] ?? '<span class="badge bg-secondary">WhatsApp Pending</span>';

            finalInvoiceActions = `
                <a href="/equipment-rental/print/${raw.final_invoice_id}" target="_blank" class="btn btn-sm btn-soft-info me-1" title="Print">
                    <i class="ri-printer-line"></i>
                </a>
                <button class="btn btn-sm btn-soft-success resend-whatsapp-btn" data-id="${raw.final_invoice_id}" title="Send WhatsApp">
                    <i class="ri-whatsapp-line"></i>
                </button>
                <br>${finalWhatsappBadge}
            `;
        }

        tbody.innerHTML += `
        <tr>

            <td>
                <input type="checkbox">
            </td>

            <td>${raw.invoice_no}</td>

            <td>${typeLabel}<br><small class="text-muted">${raw.category_name ?? ''}</small></td>

            <td>${raw.patient_name}<br><small class="text-muted">${raw.patient_id ?? ''}</small></td>

            <td>${raw.quantity ?? ''}</td>

            <td>${raw.invoice_date ?? ''}</td>

            <td>${raw.rental_return_date ?? '-'}</td>

            <td>${Number(raw.total_amount).toFixed(2)}</td>

            <td>${raw.final_invoice_amount ? Number(raw.final_invoice_amount).toFixed(2) : '-'}</td>

            <td><span class="badge ${statusBadgeClass}">${raw.status}</span></td>

            <td>${advanceInvoiceActions}</td>

            <td>${finalInvoiceActions}</td>

            <td>${actions}</td>

        </tr>
        `;
    });
}

document.addEventListener('DOMContentLoaded', function () {

    loadSettings();
    loadRentals();
});

document.getElementById('issueModal').addEventListener('show.bs.modal', function () {

    loadSettings();

    document.querySelector('#search_mobile_no').value = '';

    resetPatientSection();

    document.querySelector('#discount_amount-field').value = 0;
    document.querySelector('#discount_approved_by-field').value = '';
    document.querySelector('#discount_remarks-field').value = '';
    document.querySelector('#discount_approved_by-field').required = false;
    document.querySelector('#approved-by-required').style.display = 'none';

    document.querySelector('#lowAdvanceReasonWrap').style.display = 'none';
    document.querySelector('#low_advance_reason-field').value = '';
    document.querySelector('#low_advance_reason-field').required = false;
});

document.getElementById('rental_type-field').addEventListener('change', recalculateAdvance);
document.getElementById('quantity-field').addEventListener('input', recalculateAdvance);
document.getElementById('advance_amount-field').addEventListener('input', toggleLowAdvanceReason);

document.getElementById('issueForm').addEventListener('submit', async function (e) {

    e.preventDefault();

    if (!document.querySelector('#patient_id-field').value) {

        Swal.fire({
            icon: 'warning',
            title: 'Select or Create a Patient',
            text: 'Search by mobile number and pick an existing patient, or generate a new Patient ID first.'
        });

        return;
    }

    const lowAdvanceReasonField = document.querySelector('#low_advance_reason-field');

    if (lowAdvanceReasonField.required && !lowAdvanceReasonField.value.trim()) {

        Swal.fire({
            icon: 'warning',
            title: 'Reason Required',
            text: 'Advance is below the usual minimum -- please enter a reason.'
        });

        return;
    }

    let formData = new FormData();

    formData.append('rental_type', document.querySelector('#rental_type-field').value);
    formData.append('category_id', document.querySelector('#category_id-field').value);
    formData.append('quantity', document.querySelector('#quantity-field').value);
    formData.append('advance_amount', document.querySelector('#advance_amount-field').value);
    formData.append('low_advance_reason', lowAdvanceReasonField.value);
    formData.append('payment_mode', document.querySelector('#payment_mode-field').value);
    formData.append('patient_id', document.querySelector('#patient_id-field').value);
    formData.append('patient_name', document.querySelector('#patient_name-field').value);
    formData.append('patient_mobile_no', document.querySelector('#search_mobile_no').value);
    formData.append('patient_age', document.querySelector('#patient_age-field').value);
    formData.append('patient_gender', document.querySelector('#patient_gender-field').value);
    formData.append('remarks', document.querySelector('#remarks-field').value);
    formData.append('discount_amount', document.querySelector('#discount_amount-field').value || 0);
    formData.append('discount_approved_by', document.querySelector('#discount_approved_by-field').value);
    formData.append('discount_remarks', document.querySelector('#discount_remarks-field').value);

    const response = await fetch('/equipment-rental/issue', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken()
        },
        body: formData
    });

    const result = await response.json();

    if (!response.ok || !result.status) {

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: result.message ?? 'Unable to issue equipment.'
        });

        return;
    }

    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: result.message
    });

    bootstrap.Modal.getInstance(document.getElementById('issueModal')).hide();

    this.reset();
    document.querySelector('#search_mobile_no').value = '';
    resetPatientSection();

    loadRentals(currentPage);

    window.open(`/equipment-rental/print/${result.invoice_id}`, '_blank');
});

function computeCurrentBalance() {

    if (!lastSettlementPreview) return null;

    let discount = parseFloat(document.querySelector('#return_discount_amount-field').value) || 0;

    let netRent = lastSettlementPreview.rent_amount - discount;

    return Math.round((netRent - lastSettlementPreview.advance_amount) * 100) / 100;
}

function renderSettlementBalance() {

    let balance = computeCurrentBalance();

    if (balance === null) return;

    let balanceEl = document.querySelector('#preview-balance');
    let labelEl = document.querySelector('#preview-balance-label');

    if (balance > 0) {
        labelEl.textContent = 'Due from Patient';
        balanceEl.textContent = balance.toFixed(2);
        balanceEl.className = 'fw-semibold text-danger';
    } else if (balance < 0) {
        labelEl.textContent = 'Refund to Patient';
        balanceEl.textContent = Math.abs(balance).toFixed(2);
        balanceEl.className = 'fw-semibold text-success';
    } else {
        labelEl.textContent = 'Balance';
        balanceEl.textContent = '0.00';
        balanceEl.className = 'fw-semibold';
    }
}

let currentReturnType = null;

async function fetchSettlementPreview() {

    let id = document.querySelector('#return-invoice-id').value;
    let returnDate = document.querySelector('#return_date-field').value;
    let units = document.querySelector('#return_units-field').value;

    document.querySelector('#settlementPreview').style.display = 'none';

    if (!id || !returnDate) return;

    // Oxygen's charge depends on units consumed -- nothing to preview
    // until that's entered too.
    if (currentReturnType === 'OXYGEN_RENT' && !units) return;

    let body = { return_date: returnDate };

    if (currentReturnType === 'OXYGEN_RENT') {
        body.units = units;
    }

    const response = await fetch(`/equipment-rental/preview-settlement/${id}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(body)
    });

    const result = await response.json();

    if (!response.ok || !result.status) {
        lastSettlementPreview = null;
        return;
    }

    lastSettlementPreview = result;

    document.querySelector('#preview-advance').textContent = result.advance_amount.toFixed(2);
    document.querySelector('#preview-days').textContent = result.days_used;
    document.querySelector('#preview-rent').textContent = result.rent_amount.toFixed(2);

    renderSettlementBalance();

    document.querySelector('#settlementPreview').style.display = 'block';
}

document.getElementById('return_date-field').addEventListener('change', fetchSettlementPreview);
document.getElementById('return_units-field').addEventListener('input', fetchSettlementPreview);

document.getElementById('return_discount_amount-field').addEventListener('input', renderSettlementBalance);

document.getElementById('returnForm').addEventListener('submit', function (e) {

    e.preventDefault();

    let balance = computeCurrentBalance();

    if (balance === null) {

        Swal.fire({
            icon: 'warning',
            title: 'Settlement Not Ready',
            text: currentReturnType === 'OXYGEN_RENT'
                ? 'Please pick the return date and enter units (kg) consumed so the settlement amount can be calculated.'
                : 'Please pick the return date so the settlement amount can be calculated.'
        });

        return;
    }

    let confirmHtml = balance > 0
        ? `An amount of <b>${balance.toFixed(2)}</b> will be <b>received</b> from the patient.`
        : balance < 0
            ? `An amount of <b>${Math.abs(balance).toFixed(2)}</b> will be <b>refunded</b> to the patient.`
            : 'No balance is due either way.';

    Swal.fire({
        icon: 'question',
        title: 'Confirm Settlement',
        html: confirmHtml + '<br>Proceed to generate the final invoice?',
        showCancelButton: true,
        confirmButtonText: 'Yes, Settle',
        cancelButtonText: 'Cancel'
    }).then(function (confirmResult) {

        if (confirmResult.isConfirmed) {
            submitReturn();
        }
    });
});

async function submitReturn() {

    let id = document.querySelector('#return-invoice-id').value;

    const response = await fetch(`/equipment-rental/return/${id}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            return_date: document.querySelector('#return_date-field').value,
            units: document.querySelector('#return_units-field').value,
            payment_mode: document.querySelector('#return_payment_mode-field').value,
            discount_amount: document.querySelector('#return_discount_amount-field').value || 0,
            discount_approved_by: document.querySelector('#return_discount_approved_by-field').value,
            discount_remarks: document.querySelector('#return_discount_remarks-field').value
        })
    });

    const result = await response.json();

    if (!response.ok || !result.status) {

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: result.message ?? 'Unable to settle return.'
        });

        return;
    }

    bootstrap.Modal.getInstance(document.getElementById('returnModal')).hide();

    let settlementText = result.settlement_amount > 0
        ? `Additional ${result.settlement_amount.toFixed(2)} collected from patient.`
        : result.settlement_amount < 0
            ? `${Math.abs(result.settlement_amount).toFixed(2)} refunded to patient.`
            : 'No balance due.';

    Swal.fire({
        icon: 'success',
        title: 'Equipment Returned',
        html: `Days used: <b>${result.days_used}</b><br>Final amount: <b>${result.final_amount.toFixed(2)}</b><br>${settlementText}`
    });

    loadRentals(currentPage);

    window.open(`/equipment-rental/print/${result.final_invoice_id}`, '_blank');
}

document.addEventListener('click', async function (e) {

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadRentals(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadRentals(currentPage + 1);
        return;
    }

    let whatsappBtn = e.target.closest('.resend-whatsapp-btn');

    if (whatsappBtn) {

        let id = whatsappBtn.dataset.id;

        whatsappBtn.disabled = true;

        Swal.fire({
            title: 'Please wait',
            text: 'Whatsapp message is under process.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        const response = await fetch(`/equipment-rental/send-whatsapp/${id}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken()
            }
        });

        const result = await response.json();

        whatsappBtn.disabled = false;

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Sent' : 'Error',
            text: result.message
        });

        if (result.status) {
            loadRentals(currentPage);
        }

        return;
    }

    let returnBtn = e.target.closest('.return-btn');

    if (returnBtn) {

        document.querySelector('#return-invoice-id').value = returnBtn.dataset.id;
        currentReturnType = returnBtn.dataset.type;

        lastSettlementPreview = null;
        document.querySelector('#settlementPreview').style.display = 'none';

        document.querySelector('#return_discount_amount-field').value = 0;
        document.querySelector('#return_discount_approved_by-field').value = '';
        document.querySelector('#return_discount_remarks-field').value = '';
        document.querySelector('#return_discount_approved_by-field').required = false;
        document.querySelector('#return-approved-by-required').style.display = 'none';

        let isOxygen = currentReturnType === 'OXYGEN_RENT';

        document.querySelector('#returnUnitsWrap').style.display = isOxygen ? 'block' : 'none';
        document.querySelector('#return_units-field').required = isOxygen;
        document.querySelector('#return_units-field').value = '';

        new bootstrap.Modal(document.getElementById('returnModal')).show();

        setFlatpickrValue('return_date-field', new Date().toISOString().split('T')[0]);

        return;
    }

    let cancelBtn = e.target.closest('.cancel-btn');

    if (cancelBtn) {

        let id = cancelBtn.dataset.id;

        let confirm = await Swal.fire({
            title: 'Cancel this rental?',
            text: 'The advance collected will be refunded and the stock restored.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0ab39c',
            cancelButtonColor: '#f06548',
            confirmButtonText: 'Yes, Cancel'
        });

        if (!confirm.isConfirmed) return;

        let response = await fetch(`/equipment-rental/cancel/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken()
            }
        });

        let result = await response.json();

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Cancelled' : 'Error',
            text: result.message
        });

        if (result.status) {
            loadRentals(currentPage);
        }
    }
});

document.addEventListener('change', function (e) {

    if (e.target.id === 'perPage') {

        perPage = parseInt(e.target.value);
        loadRentals(1);
    }

    if (e.target.id === 'statusFilter') {

        statusFilter = e.target.value;
        loadRentals(1);
    }
});
