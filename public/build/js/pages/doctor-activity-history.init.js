let currentDoctor = null;
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

function statusBadge(dueAmount) {
    return Number(dueAmount) > 0
        ? '<span class="badge bg-warning-subtle text-warning">Due</span>'
        : '<span class="badge bg-success-subtle text-success">Paid</span>';
}

// The dropdown itself is rendered server-side (every doctor, with
// specialisation shown per option) -- no search step, just select and go.
document.getElementById('doctorSelect').addEventListener('change', function () {

    if (!this.value) {
        currentDoctor = null;
        document.querySelector('#historyWrap').style.display = 'none';
        return;
    }

    selectDoctor();
});

function selectDoctor() {

    let select = document.querySelector('#doctorSelect');
    let option = select.options[select.selectedIndex];

    if (!option) return;

    currentDoctor = {
        id: option.value,
        doctor_name: option.dataset.doctorName,
        doctor_code: option.dataset.doctorCode,
        qualification: option.dataset.qualification,
        specialisation: option.dataset.specialisation
    };

    document.querySelector('#info-name').textContent = currentDoctor.doctor_name;
    document.querySelector('#info-code').textContent = currentDoctor.doctor_code || 'N/A';
    document.querySelector('#info-qualification').textContent = currentDoctor.qualification || '-';
    document.querySelector('#info-specialisation').textContent = currentDoctor.specialisation || '-';

    document.querySelector('#historyWrap').style.display = 'block';

    loadHistory();
}

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

    if (!currentDoctor) return;

    const response = await fetch('/doctor-activity-history/get-history', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            doctor_id: currentDoctor.id,
            period: currentPeriod
        })
    });

    const result = await response.json();

    if (!result.status) return;

    renderAppointments(result.appointments);
    renderDoctorVisitInvoices(result.doctor_visit_invoices);
    renderDiagnosticInvolvement(result.diagnostic_involvement);
    renderSettlements(result.settlements);
}

function renderAppointments(rows) {

    let tbody = document.querySelector('#appointmentsBody');

    if (!rows || !rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="history-empty">No appointments found in this period.</td></tr>';
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
            <td class="fw-semibold text-primary">${escapeHtml(r.patient_name ?? '')}</td>
            <td>${escapeHtml(r.patient_mobile_no ?? '')}</td>
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
        tbody.innerHTML = '<tr><td colspan="7" class="history-empty">No consultation invoices found in this period.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(r => `
        <tr>
            <td>${fmtDate(r.invoice_date)}</td>
            <td>${escapeHtml(r.invoice_no)}</td>
            <td>${escapeHtml(r.patient_name ?? '')}</td>
            <td>₹ ${fmtMoney(r.paid_amount)}</td>
            <td>₹ ${fmtMoney(r.due_amount)}</td>
            <td>${statusBadge(r.due_amount)}</td>
            <td>${printLink('/doctor-visit-invoice/print/' + r.id)}</td>
        </tr>
    `).join('');
}

function renderDiagnosticInvolvement(rows) {

    let tbody = document.querySelector('#diagnosticBody');

    if (!rows || !rows.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="history-empty">No diagnostic test involvement found in this period.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(r => `
        <tr>
            <td>${fmtDate(r.invoice_date)}</td>
            <td>${escapeHtml(r.invoice_no)}</td>
            <td>${escapeHtml(r.patient_name ?? '')}</td>
            <td>${escapeHtml(r.item_description ?? '')}</td>
            <td>₹ ${fmtMoney(r.amount)}</td>
            <td>₹ ${fmtMoney(r.payment_value)}</td>
            <td>${statusBadge(r.due_amount)}</td>
            <td>${printLink('/diagnostic-invoice/print/' + r.invoice_id)}</td>
        </tr>
    `).join('');
}

function renderSettlements(rows) {

    let tbody = document.querySelector('#settlementsBody');

    if (!rows || !rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="history-empty">No settlements found in this period.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(r => `
        <tr>
            <td>${fmtDate(r.settlement_date)}</td>
            <td>${escapeHtml(r.settlement_no ?? '')}</td>
            <td>₹ ${fmtMoney(r.gross_amount)}</td>
            <td>₹ ${fmtMoney(r.deduction_amount)}</td>
            <td class="fw-semibold">₹ ${fmtMoney(r.net_amount)}</td>
            <td>${escapeHtml(r.payment_mode ?? '')}</td>
        </tr>
    `).join('');
}
