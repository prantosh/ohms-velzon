let currentPage = 1;
let lastPage = 1;
let perPage = 10;
let statusFilter = '';
let rentalSettings = { destinations: [], approvers: [], staff: [] };

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
    document.querySelector('#patient_age-field').value = patient.age ?? '';
    document.querySelector('#patient_gender-field').value = patient.gender ?? '';
    document.querySelector('#patient_gender-field').disabled = true;

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

    const response = await fetch('/ambulance-rental/settings');

    rentalSettings = await response.json();

    let destSelect = document.querySelector('#destination_id-field');

    destSelect.innerHTML = '<option value="">Select Destination</option>';

    rentalSettings.destinations.forEach(destination => {

        destSelect.innerHTML += `
        <option value="${destination.id}" data-fare-ac="${destination.fare_ac}" data-fare-nonac="${destination.fare_nonac}">
            ${destination.destination_name}
        </option>
        `;
    });

    let approverOptions = '<option value="">Select</option>';

    (rentalSettings.approvers ?? []).forEach(approver => {
        approverOptions += `<option value="${approver.id}">${approver.name}</option>`;
    });

    document.querySelector('#discount_approved_by-field').innerHTML = approverOptions;

    let staffOptions = '<option value="">Select</option>';

    (rentalSettings.staff ?? []).forEach(staff => {
        staffOptions += `<option value="${staff.id}">${staff.name}</option>`;
    });

    document.querySelector('#received_by-field').innerHTML = staffOptions;

    recalculateAmount();
}

function toggleDiscountApprovalRequirement() {

    let discountAmount = parseFloat(document.querySelector('#discount_amount-field').value) || 0;

    let approvedByField = document.querySelector('#discount_approved_by-field');
    let asterisk = document.querySelector('#approved-by-required');

    approvedByField.required = discountAmount > 0;
    asterisk.style.display = discountAmount > 0 ? 'inline' : 'none';
}

document.getElementById('discount_amount-field').addEventListener('input', function () {
    toggleDiscountApprovalRequirement();
    recalculateAmount();
});

function recalculateAmount() {

    let select = document.querySelector('#destination_id-field');

    let option = select.options[select.selectedIndex];

    let bookingType = document.querySelector('#booking_type-field').value;

    let fare = 0;

    if (option && option.value) {

        fare = bookingType === 'AC'
            ? parseFloat(option.dataset.fareAc || 0)
            : parseFloat(option.dataset.fareNonac || 0);
    }

    let waitingCharge = parseFloat(document.querySelector('#waiting_charge-field').value) || 0;

    let discountAmount = parseFloat(document.querySelector('#discount_amount-field').value) || 0;

    let receivedAmount = parseFloat(document.querySelector('#received_amount-field').value) || 0;

    let actualAmount = fare + waitingCharge;

    let netPayable = actualAmount - discountAmount;

    let dueAmount = Math.max(0, netPayable - receivedAmount);

    document.querySelector('#actual_amount-display').value = actualAmount.toFixed(2);
    document.querySelector('#due_amount-display').value = dueAmount.toFixed(2);
}

document.getElementById('destination_id-field').addEventListener('change', recalculateAmount);
document.getElementById('booking_type-field').addEventListener('change', recalculateAmount);
document.getElementById('waiting_charge-field').addEventListener('input', recalculateAmount);
document.getElementById('received_amount-field').addEventListener('input', recalculateAmount);

async function loadRentals(page = 1) {

    currentPage = page;

    const response = await fetch(
        `/ambulance-rental/list?page=${page}&per_page=${perPage}&status=${statusFilter}`
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

        let statusBadgeClass = {
            'Active': 'bg-success',
            'Cancelled': 'bg-danger'
        }[raw.status] ?? 'bg-secondary';

        let whatsappBadge = {
            'SENT': '<span class="badge bg-success">Sent</span>',
            'FAILED': '<span class="badge bg-danger">Failed</span>'
        }[raw.whatsapp_status] ?? '<span class="badge bg-secondary">Pending</span>';

        let invoiceActions = `
            <a href="/ambulance-rental/print/${raw.id}" target="_blank" class="btn btn-sm btn-soft-info me-1" title="Print">
                <i class="ri-printer-line"></i>
            </a>
            <button class="btn btn-sm btn-soft-success resend-whatsapp-btn" data-id="${raw.id}" title="Send WhatsApp">
                <i class="ri-whatsapp-line"></i>
            </button>
            <br>${whatsappBadge}
        `;

        let actions = raw.status === 'Active'
            ? `
            <button class="btn btn-sm btn-soft-danger cancel-btn" data-id="${raw.id}" title="Cancel">
                <i class="ri-close-line"></i>
            </button>
            `
            : '-';

        tbody.innerHTML += `
        <tr>

            <td>${raw.invoice_no}</td>

            <td>${raw.patient_name}<br><small class="text-muted">${raw.patient_id ?? ''}</small></td>

            <td>${raw.destination_name ?? ''}<br><small class="text-muted">${raw.from_destination ?? ''}</small></td>

            <td>${raw.booking_type ?? ''}</td>

            <td>${raw.invoice_date ?? ''}</td>

            <td>${Number(raw.total_amount).toFixed(2)}</td>

            <td>${Number(raw.paid_amount).toFixed(2)}</td>

            <td>${Number(raw.due_amount).toFixed(2)}</td>

            <td><span class="badge ${statusBadgeClass}">${raw.status}</span></td>

            <td>${invoiceActions}</td>

            <td>${actions}</td>

        </tr>
        `;
    });
}

document.addEventListener('DOMContentLoaded', function () {

    loadSettings();
    loadRentals();
});

document.getElementById('bookingModal').addEventListener('show.bs.modal', function () {

    loadSettings();

    document.querySelector('#search_mobile_no').value = '';

    resetPatientSection();

    document.querySelector('#address-field').value = '';
    document.querySelector('#from_destination-field').value = '';
    setFlatpickrValue('booking_date-field', new Date().toISOString().split('T')[0]);
    document.querySelector('#pickup_time-field').value = '';
    document.querySelector('#release_time-field').value = '';
    document.querySelector('#odometer_pickup_km-field').value = '';
    document.querySelector('#odometer_release_km-field').value = '';
    document.querySelector('#waiting_charge-field').value = 0;
    document.querySelector('#received_amount-field').value = '';
    document.querySelector('#remarks-field').value = '';

    document.querySelector('#discount_amount-field').value = 0;
    document.querySelector('#discount_approved_by-field').value = '';
    document.querySelector('#discount_remarks-field').value = '';
    document.querySelector('#discount_approved_by-field').required = false;
    document.querySelector('#approved-by-required').style.display = 'none';

    recalculateAmount();
});

document.getElementById('bookingForm').addEventListener('submit', async function (e) {

    e.preventDefault();

    if (!document.querySelector('#patient_id-field').value) {

        Swal.fire({
            icon: 'warning',
            title: 'Select or Create a Patient',
            text: 'Search by mobile number and pick an existing patient, or generate a new Patient ID first.'
        });

        return;
    }

    let formData = new FormData();

    formData.append('destination_id', document.querySelector('#destination_id-field').value);
    formData.append('booking_type', document.querySelector('#booking_type-field').value);
    formData.append('from_destination', document.querySelector('#from_destination-field').value);
    formData.append('address', document.querySelector('#address-field').value);

    formData.append('patient_id', document.querySelector('#patient_id-field').value);
    formData.append('patient_name', document.querySelector('#patient_name-field').value);
    formData.append('patient_mobile_no', document.querySelector('#search_mobile_no').value);
    formData.append('patient_age', document.querySelector('#patient_age-field').value);
    formData.append('patient_gender', document.querySelector('#patient_gender-field').value);

    formData.append('booking_date', document.querySelector('#booking_date-field').value);
    formData.append('pickup_time', document.querySelector('#pickup_time-field').value);
    formData.append('release_time', document.querySelector('#release_time-field').value);

    formData.append('odometer_pickup_km', document.querySelector('#odometer_pickup_km-field').value);
    formData.append('odometer_release_km', document.querySelector('#odometer_release_km-field').value);

    formData.append('waiting_charge', document.querySelector('#waiting_charge-field').value || 0);

    formData.append('discount_amount', document.querySelector('#discount_amount-field').value || 0);
    formData.append('discount_approved_by', document.querySelector('#discount_approved_by-field').value);
    formData.append('discount_remarks', document.querySelector('#discount_remarks-field').value);

    formData.append('received_amount', document.querySelector('#received_amount-field').value);
    formData.append('payment_mode', document.querySelector('#payment_mode-field').value);
    formData.append('received_by', document.querySelector('#received_by-field').value);

    formData.append('remarks', document.querySelector('#remarks-field').value);

    const response = await fetch('/ambulance-rental/store', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken()
        },
        body: formData
    });

    const result = await response.json();

    if (!response.ok || !result.status) {

        let errorText = result.errors
            ? Object.values(result.errors).flat().join(', ')
            : (result.message ?? 'Unable to create ambulance rental invoice.');

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: errorText
        });

        return;
    }

    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: result.message
    });

    bootstrap.Modal.getInstance(document.getElementById('bookingModal')).hide();

    this.reset();
    document.querySelector('#search_mobile_no').value = '';
    resetPatientSection();

    loadRentals(currentPage);

    window.open(`/ambulance-rental/print/${result.invoice_id}`, '_blank');
});

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

        const response = await fetch(`/ambulance-rental/send-whatsapp/${id}`, {
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

    let cancelBtn = e.target.closest('.cancel-btn');

    if (cancelBtn) {

        let id = cancelBtn.dataset.id;

        let confirm = await Swal.fire({
            title: 'Cancel this invoice?',
            text: 'The received amount will be refunded and the invoice marked cancelled.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0ab39c',
            cancelButtonColor: '#f06548',
            confirmButtonText: 'Yes, Cancel'
        });

        if (!confirm.isConfirmed) return;

        let response = await fetch(`/ambulance-rental/cancel/${id}`, {
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
