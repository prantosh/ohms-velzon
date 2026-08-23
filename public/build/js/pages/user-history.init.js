let currentUser = null;

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

function fmtMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Without an explicit Accept header, fetch() doesn't tell Laravel this is an
// AJAX call -- a validation failure then 302-redirects to a normal HTML page
// instead of returning JSON, and response.json() throws. Same fix applied
// to the diagnostic invoice / report editors elsewhere in this codebase.
async function fetchJson(url, options = {}) {
    const headers = Object.assign({ 'Accept': 'application/json' }, options.headers || {});
    const response = await fetch(url, Object.assign({}, options, { headers }));

    let result;
    try {
        result = await response.json();
    } catch (e) {
        throw new Error(`Unexpected server response (HTTP ${response.status}). Please try again.`);
    }

    return { response, result };
}

// The dropdown itself is rendered server-side (every staff user, with role
// and mobile shown per option) -- no search step, just select and go.
document.getElementById('userSelect').addEventListener('change', function () {

    if (!this.value) {
        currentUser = null;
        document.querySelector('#historyWrap').style.display = 'none';
        return;
    }

    selectUser();
});

function todayStr() {
    return new Date().toISOString().slice(0, 10);
}

function daysAgoStr(days) {
    let d = new Date();
    d.setDate(d.getDate() - days);
    return d.toISOString().slice(0, 10);
}

function selectUser() {

    let select = document.querySelector('#userSelect');
    let option = select.options[select.selectedIndex];

    if (!option) return;

    currentUser = {
        id: option.value,
        name: option.dataset.name,
        role: option.dataset.role
    };

    document.querySelector('#info-name').textContent = currentUser.name;
    document.querySelector('#info-role').textContent = currentUser.role;

    // Default to the last 7 days -- one row per day, so an unbounded
    // range would be impractical (the backend also caps this at 62 days).
    document.querySelector('#fromDateInput').value = daysAgoStr(6);
    document.querySelector('#toDateInput').value = todayStr();

    document.querySelector('#historyWrap').style.display = 'block';

    loadDailySummary();
    loadLoginLogs();
}

document.getElementById('btnLoad').addEventListener('click', function () {
    loadDailySummary();
    loadLoginLogs();
});

async function loadDailySummary() {

    if (!currentUser) return;

    let fromDate = document.querySelector('#fromDateInput').value;
    let toDate = document.querySelector('#toDateInput').value;

    if (!fromDate || !toDate) return;

    let tbody = document.querySelector('#dailySummaryBody');
    tbody.innerHTML = '<tr><td colspan="6" class="history-empty">Loading...</td></tr>';

    const { result } = await fetchJson('/user-history/get-daily-summary', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken()
        },
        body: JSON.stringify({ user_id: currentUser.id, from_date: fromDate, to_date: toDate })
    });

    if (!result.status) {
        tbody.innerHTML = `<tr><td colspan="6" class="history-empty text-danger">${escapeHtml(result.message ?? 'Unable to load.')}</td></tr>`;
        return;
    }

    renderDailySummary(result.data);
}

function renderDailySummary(rows) {

    let tbody = document.querySelector('#dailySummaryBody');

    if (!rows || !rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="history-empty">No activity found in this range.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(r => `
        <tr>
            <td class="fw-semibold">${escapeHtml(r.date_fmt)}</td>
            <td>${r.invoice_count}</td>
            <td class="${Number(r.due_amount) > 0 ? 'text-warning fw-semibold' : ''}">₹ ${fmtMoney(r.due_amount)}</td>
            <td>₹ ${fmtMoney(r.cash_paid_to_doctors)}</td>
            <td class="fw-semibold">₹ ${fmtMoney(r.net_cash_to_deposit)}</td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-primary show-detail-btn" data-date="${r.date}">
                    <i class="ri-eye-line"></i>
                    Show Detail
                </button>
            </td>
        </tr>
    `).join('');
}

document.getElementById('dailySummaryBody').addEventListener('click', async function (e) {

    let btn = e.target.closest('.show-detail-btn');
    if (!btn) return;

    let date = btn.dataset.date;

    btn.disabled = true;

    try {

        const { result } = await fetchJson('/user-history/get-day-detail', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken()
            },
            body: JSON.stringify({ user_id: currentUser.id, date: date })
        });

        if (!result.status) return;

        renderDayDetail(date, result);

        bootstrap.Modal.getOrCreateInstance(document.getElementById('dayDetailModal')).show();

    } finally {

        btn.disabled = false;
    }
});

function renderDayDetail(date, result) {

    document.querySelector('#detailModalDate').textContent = new Date(date).toLocaleDateString('en-GB');

    let s = result.summary;

    document.querySelector('#detailSummaryRow').innerHTML = `
        <div class="col-md-2">
            <small class="text-muted d-block">Invoices</small>
            <span class="fw-semibold">${s.invoice_count}</span>
        </div>
        <div class="col-md-2">
            <small class="text-muted d-block">Cash Collected</small>
            <span class="fw-semibold">₹ ${fmtMoney(s.cash_collected)}</span>
        </div>
        <div class="col-md-2">
            <small class="text-muted d-block">Non-Cash Collected</small>
            <span class="fw-semibold">₹ ${fmtMoney(s.non_cash_collected)}</span>
        </div>
        <div class="col-md-2">
            <small class="text-muted d-block">Cash Refunded</small>
            <span class="fw-semibold">₹ ${fmtMoney(s.cash_refunded)}</span>
        </div>
        <div class="col-md-2">
            <small class="text-muted d-block">Paid To Doctors</small>
            <span class="fw-semibold">₹ ${fmtMoney(s.cash_paid_to_doctors)}</span>
        </div>
        <div class="col-md-2">
            <small class="text-muted d-block">Net Cash To Deposit</small>
            <span class="fw-bold text-success">₹ ${fmtMoney(s.net_cash_to_deposit)}</span>
        </div>
    `;

    let ledgerTbody = document.querySelector('#detailLedgerBody');

    let typeBadgeClass = {
        'Collection': 'bg-success',
        'Refund': 'bg-danger',
        'Doctor Payment': 'bg-warning text-dark',
    };

    ledgerTbody.innerHTML = (result.ledger || []).length
        ? result.ledger.map(row => {
            let amountClass = row.amount >= 0 ? 'text-success' : 'text-danger';
            return `
            <tr>
                <td class="fw-semibold">${escapeHtml(row.invoice_no)}</td>
                <td>${escapeHtml(row.category)}</td>
                <td>${escapeHtml(row.transaction_no)}</td>
                <td>${escapeHtml(row.transaction_to)}</td>
                <td>${escapeHtml(row.time_fmt)}</td>
                <td class="text-end ${amountClass}">${fmtMoney(row.amount)}</td>
                <td><span class="badge ${typeBadgeClass[row.type] ?? 'bg-secondary'}">${escapeHtml(row.type)}</span></td>
            </tr>
            `;
        }).join('')
        : '<tr><td colspan="7" class="history-empty">No transactions on this day.</td></tr>';

    let doctorTbody = document.querySelector('#detailDoctorPaymentsBody');

    doctorTbody.innerHTML = (result.doctor_payments || []).length
        ? result.doctor_payments.map(p => `
            <tr>
                <td>${escapeHtml(p.invoice_no)}</td>
                <td>${escapeHtml(p.doctor_name)}</td>
                <td>${escapeHtml(p.settlement_no)}</td>
                <td>${escapeHtml(p.settlement_time)}</td>
                <td class="text-end">₹ ${fmtMoney(p.amount)}</td>
            </tr>
        `).join('')
        : '<tr><td colspan="5" class="history-empty">No doctor payments made on this day.</td></tr>';
}

async function loadLoginLogs() {

    if (!currentUser) return;

    let fromDate = document.querySelector('#fromDateInput').value;
    let toDate = document.querySelector('#toDateInput').value;

    if (!fromDate || !toDate) return;

    let tbody = document.querySelector('#loginLogsBody');
    tbody.innerHTML = '<tr><td colspan="5" class="history-empty">Loading...</td></tr>';

    const { result } = await fetchJson('/user-history/get-login-logs', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken()
        },
        body: JSON.stringify({ user_id: currentUser.id, from_date: fromDate, to_date: toDate })
    });

    if (!result.status) {
        tbody.innerHTML = `<tr><td colspan="5" class="history-empty text-danger">${escapeHtml(result.message ?? 'Unable to load.')}</td></tr>`;
        return;
    }

    renderLoginLogs(result.data);
}

function renderLoginLogs(rows) {

    let tbody = document.querySelector('#loginLogsBody');

    if (!rows || !rows.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="history-empty">No login activity found in this range.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(r => `
        <tr>
            <td>${escapeHtml(r.login_time_fmt ?? '')}</td>
            <td>${escapeHtml(r.logout_time_fmt ?? '-')}</td>
            <td>${escapeHtml(r.ip_address ?? '')}</td>
            <td class="text-truncate" style="max-width:280px;" title="${escapeHtml(r.user_agent ?? '')}">${escapeHtml(r.user_agent ?? '')}</td>
            <td>${r.still_active
                ? '<span class="badge bg-success-subtle text-success">Active</span>'
                : '<span class="badge bg-secondary-subtle text-secondary">Ended</span>'}</td>
        </tr>
    `).join('');
}
