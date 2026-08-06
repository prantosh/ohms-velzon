let currentPatient = null;
let currentPeriod = '3';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function fmtDate(value) {
    if (!value) return '';
    let d = new Date(value);
    if (isNaN(d.getTime())) return value;
    return d.toLocaleDateString('en-GB');
}

function fmtMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function printLink(url) {
    return `<a href="${url}" target="_blank" class="print-link bg-primary-subtle text-primary" title="Print / View PDF">
        <i class="ri-printer-line"></i>
    </a>`;
}

document.getElementById('mobileInput').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').substring(0, 10);
});

document.getElementById('mobileInput').addEventListener('keypress', function (e) {
    if (e.which === 13) {
        e.preventDefault();
        searchMobile();
    }
});

document.getElementById('btnSearchMobile').addEventListener('click', searchMobile);

async function searchMobile() {

    let mobile = document.querySelector('#mobileInput').value.trim();

    document.querySelector('#noPatientMsg').style.display = 'none';
    document.querySelector('#patientSelectWrap').style.display = 'none';
    document.querySelector('#historyWrap').style.display = 'none';
    currentPatient = null;

    if (!mobile) return;

    const response = await fetch('/patient-history/search-by-mobile', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({ mobile_no: mobile })
    });

    const result = await response.json();

    if (!result.status) {
        document.querySelector('#noPatientMsg').style.display = 'block';
        return;
    }

    let select = document.querySelector('#patientSelect');
    select.innerHTML = '';

    let placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = '-- Select Patient --';
    select.appendChild(placeholder);

    result.patients.forEach(p => {
        let option = document.createElement('option');
        option.value = p.id;
        option.textContent = `${p.patient_name} (${p.patient_id ?? 'N/A'}) - Age ${p.age ?? '-'}, ${p.gender ?? '-'}`;
        option.dataset.patientId = p.patient_id ?? '';
        option.dataset.patientName = p.patient_name;
        option.dataset.age = p.age ?? '';
        option.dataset.gender = p.gender ?? '';
        select.appendChild(option);
    });

    select.value = '';

    document.querySelector('#patientSelectWrap').style.display = 'block';
}

document.getElementById('patientSelect').addEventListener('change', function () {

    if (!this.value) {
        currentPatient = null;
        document.querySelector('#historyWrap').style.display = 'none';
        return;
    }

    let mobile = document.querySelector('#mobileInput').value.trim();
    selectPatient(mobile);
});

function selectPatient(mobile) {

    let select = document.querySelector('#patientSelect');
    let option = select.options[select.selectedIndex];

    if (!option) return;

    currentPatient = {
        id: option.value,
        patient_id: option.dataset.patientId,
        patient_name: option.dataset.patientName,
        age: option.dataset.age,
        gender: option.dataset.gender,
        mobile_no: mobile
    };

    document.querySelector('#info-name').textContent = currentPatient.patient_name;
    document.querySelector('#info-patient-id').textContent = currentPatient.patient_id || 'N/A';
    document.querySelector('#info-age').textContent = currentPatient.age || '-';
    document.querySelector('#info-gender').textContent = currentPatient.gender || '-';
    document.querySelector('#info-mobile').textContent = currentPatient.mobile_no;

    document.querySelector('#btnEditPatient').style.display = currentPatient.id ? 'inline-block' : 'none';

    document.querySelector('#historyWrap').style.display = 'block';

    loadHistory();
}

document.getElementById('btnEditPatient').addEventListener('click', function () {

    if (!currentPatient || !currentPatient.id) return;

    Swal.fire({
        title: 'Edit Patient Details',
        html: `
            <div class="text-start">
                <label class="form-label">Name</label>
                <input id="swal-patient-name" class="form-control mb-2" value="${escapeHtml(currentPatient.patient_name)}">
                <label class="form-label">Age</label>
                <input id="swal-patient-age" type="number" min="0" max="150" class="form-control mb-2" value="${escapeHtml(currentPatient.age ?? '')}">
                <label class="form-label">Sex</label>
                <select id="swal-patient-gender" class="form-select">
                    <option value="">Select</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        `,
        didOpen: function () {
            document.getElementById('swal-patient-gender').value = currentPatient.gender || '';
        },
        showCancelButton: true,
        confirmButtonText: 'Save',
        preConfirm: function () {

            let name = document.getElementById('swal-patient-name').value.trim();

            if (!name) {
                Swal.showValidationMessage('Name is required');
                return false;
            }

            return {
                id: currentPatient.id,
                patient_name: name,
                age: document.getElementById('swal-patient-age').value || null,
                gender: document.getElementById('swal-patient-gender').value || null
            };
        }
    }).then(function (result) {

        if (!result.isConfirmed) return;

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

        fetch('/patient-history/update-patient', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify(result.value)
        })
            .then(response => response.json())
            .then(function (response) {

                if (!response.status) {
                    Swal.fire({ icon: 'error', title: 'Update Failed', text: response.message });
                    return;
                }

                currentPatient.patient_name = response.patient.patient_name;
                currentPatient.age = response.patient.age;
                currentPatient.gender = response.patient.gender;

                let select = document.querySelector('#patientSelect');
                let option = select.options[select.selectedIndex];
                if (option) {
                    option.dataset.patientName = currentPatient.patient_name;
                    option.dataset.age = currentPatient.age ?? '';
                    option.dataset.gender = currentPatient.gender ?? '';
                    option.textContent = `${currentPatient.patient_name} (${currentPatient.patient_id ?? 'N/A'}) - Age ${currentPatient.age ?? '-'}, ${currentPatient.gender ?? '-'}`;
                }

                document.querySelector('#info-name').textContent = currentPatient.patient_name;
                document.querySelector('#info-age').textContent = currentPatient.age || '-';
                document.querySelector('#info-gender').textContent = currentPatient.gender || '-';

                Swal.fire({ icon: 'success', title: 'Updated', text: response.message, timer: 1800, showConfirmButton: false });

                loadHistory();
            })
            .catch(function () {
                Swal.fire({ icon: 'error', title: 'Something went wrong. Please try again.' });
            });
    });
});

document.getElementById('periodFilter').addEventListener('click', function (e) {

    let btn = e.target.closest('button[data-period]');
    if (!btn) return;

    currentPeriod = btn.dataset.period;

    this.querySelectorAll('button').forEach(b => {
        b.classList.remove('btn-primary');
        b.classList.add('btn-outline-primary');
    });

    btn.classList.remove('btn-outline-primary');
    btn.classList.add('btn-primary');

    loadHistory();
});

async function loadHistory() {

    if (!currentPatient) return;

    const response = await fetch('/patient-history/get-history', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            patient_id: currentPatient.patient_id,
            mobile_no: currentPatient.mobile_no,
            patient_name: currentPatient.patient_name,
            period: currentPeriod
        })
    });

    const result = await response.json();

    if (!result.status) return;

    renderAppointments(result.appointments);
    renderDoctorVisitInvoices(result.doctor_visit_invoices);
    renderDiagnosticInvoices(result.diagnostic_invoices);
    renderEquipmentRentals(result.equipment_rentals);
    renderAmbulanceBookings(result.ambulance_bookings);
    renderMembership(result.membership);
}

function statusBadge(dueAmount) {
    return Number(dueAmount) > 0
        ? '<span class="badge bg-warning-subtle text-warning">Due</span>'
        : '<span class="badge bg-success-subtle text-success">Paid</span>';
}

function renderAppointments(rows) {

    let tbody = document.querySelector('#appointmentsBody');

    if (!rows || !rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="history-empty">No appointments found in this period.</td></tr>';
        return;
    }

    let statusColors = {
        'Booked': 'primary',
        'Completed': 'success',
        'Cancelled': 'danger',
        'No Show': 'warning'
    };

    tbody.innerHTML = rows.map(r => {
        let color = statusColors[r.appointment_status] || 'secondary';
        return `
        <tr>
            <td>${fmtDate(r.appointment_date)}</td>
            <td>${escapeHtml(r.appointment_time ?? '')}</td>
            <td class="fw-semibold text-primary">${escapeHtml(r.doctor_name ?? '')}</td>
            <td>${escapeHtml(r.token_no ?? '')}</td>
            <td>₹ ${fmtMoney(r.consultation_fee)}</td>
            <td><span class="badge bg-${color}-subtle text-${color}">${escapeHtml(r.appointment_status ?? '')}</span></td>
        </tr>
        `;
    }).join('');
}

function renderDoctorVisitInvoices(rows) {

    let tbody = document.querySelector('#doctorVisitBody');

    if (!rows || !rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="history-empty">No doctor visit invoices found in this period.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(r => `
        <tr>
            <td>${fmtDate(r.invoice_date)}</td>
            <td>${escapeHtml(r.invoice_no)}</td>
            <td>${escapeHtml(r.doctor_name ?? '')}</td>
            <td>₹ ${fmtMoney(r.paid_amount)}</td>
            <td>₹ ${fmtMoney(r.due_amount)}</td>
            <td>${statusBadge(r.due_amount)}</td>
            <td>${printLink('/doctor-visit-invoice/print/' + r.id)}</td>
        </tr>
    `).join('');
}

function renderDiagnosticInvoices(rows) {

    let tbody = document.querySelector('#diagnosticBody');

    if (!rows || !rows.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="history-empty">No diagnostic test invoices found in this period.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(r => {
        let catColor = r.invoice_category === 'PATHOLOGY' ? 'info' : 'secondary';
        let items = (r.items || []).join(', ');
        return `
        <tr>
            <td>${fmtDate(r.invoice_date)}</td>
            <td>${escapeHtml(r.invoice_no)}</td>
            <td><span class="badge bg-${catColor}-subtle text-${catColor}">${escapeHtml(r.invoice_category ?? '')}</span></td>
            <td>${escapeHtml(items)}</td>
            <td>₹ ${fmtMoney(r.paid_amount)}</td>
            <td>₹ ${fmtMoney(r.due_amount)}</td>
            <td>${statusBadge(r.due_amount)}</td>
            <td>${printLink('/diagnostic-invoice/print/' + r.id)}</td>
        </tr>
        `;
    }).join('');
}

function renderEquipmentRentals(rows) {

    let tbody = document.querySelector('#equipmentBody');

    if (!rows || !rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="history-empty">No equipment rentals found in this period.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(r => {
        let typeLabel = r.is_final_settlement
            ? '<span class="badge bg-success-subtle text-success">Final Settlement</span>'
            : '<span class="badge bg-primary-subtle text-primary">Initial Issue</span>';

        return `
        <tr>
            <td>${fmtDate(r.invoice_date)}</td>
            <td>${escapeHtml(r.invoice_no)}</td>
            <td>${escapeHtml(r.category_name ?? '')} ${r.quantity ? '(x' + escapeHtml(r.quantity) + ')' : ''}</td>
            <td>${typeLabel}</td>
            <td>₹ ${fmtMoney(r.paid_amount)}</td>
            <td>${escapeHtml(r.status ?? '')}</td>
            <td>${printLink('/equipment-rental/print/' + r.id)}</td>
        </tr>
        `;
    }).join('');
}

function renderAmbulanceBookings(rows) {

    let tbody = document.querySelector('#ambulanceBody');

    if (!rows || !rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="history-empty">No ambulance bookings found in this period.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(r => `
        <tr>
            <td>${fmtDate(r.invoice_date)}</td>
            <td>${escapeHtml(r.invoice_no)}</td>
            <td>${escapeHtml(r.from_destination ?? '')}</td>
            <td>${escapeHtml(r.destination ?? '')}</td>
            <td>${escapeHtml(r.booking_type ?? '')}</td>
            <td>₹ ${fmtMoney(r.paid_amount)}</td>
            <td>${printLink('/ambulance-rental/print/' + r.id)}</td>
        </tr>
    `).join('');
}

function renderMembership(membership) {

    let card = document.querySelector('#membershipCard');
    let body = document.querySelector('#membershipBody');

    if (!membership || !membership.is_member) {
        card.style.display = 'none';
        body.innerHTML = '';
        return;
    }

    card.style.display = 'block';

    let dueBadge = membership.is_due
        ? `<span class="badge bg-danger-subtle text-danger">Due (${membership.months_overdue} month${membership.months_overdue > 1 ? 's' : ''})</span>`
        : '<span class="badge bg-success-subtle text-success">Up to Date</span>';

    let paymentsRows = (membership.payments || []).map(p => {
        let typeBadge = p.payment_type === 'PAID'
            ? '<span class="badge bg-success-subtle text-success">PAID</span>'
            : '<span class="badge bg-warning-subtle text-warning">ADJUSTED</span>';

        let link = p.invoice_id ? printLink('/membership-fee/print/' + p.invoice_id) : '';

        return `
        <tr>
            <td>${escapeHtml(p.month_label)}</td>
            <td>₹ ${fmtMoney(p.rate_applied)}</td>
            <td>${typeBadge}</td>
            <td>${escapeHtml(p.created_dt ?? '')}</td>
            <td>${link}</td>
        </tr>
        `;
    }).join('');

    body.innerHTML = `
    <div class="row mb-3">
        <div class="col-md-3">
            <small class="text-muted d-block">Member Since</small>
            <span class="fw-semibold">${fmtDate(membership.date_of_joining)}</span>
        </div>
        <div class="col-md-3">
            <small class="text-muted d-block">Last Paid Month</small>
            <span class="fw-semibold">${escapeHtml(membership.last_paid_month ?? 'Never Paid')}</span>
        </div>
        <div class="col-md-3">
            <small class="text-muted d-block">Status</small>
            <span class="fw-semibold">${escapeHtml(membership.status ?? '')}</span>
        </div>
        <div class="col-md-3">
            <small class="text-muted d-block">Fee Status</small>
            ${dueBadge}
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle mb-0">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Amount</th>
                    <th>Type</th>
                    <th>Recorded On</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                ${paymentsRows || '<tr><td colspan="5" class="history-empty">No membership fee records in this period.</td></tr>'}
            </tbody>
        </table>
    </div>
    `;
}
