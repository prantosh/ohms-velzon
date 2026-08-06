let loginCurrentPage = 1;
let loginLastPage = 1;
let loginPerPage = 10;

let auditCurrentPage = 1;
let auditLastPage = 1;
let auditPerPage = 10;
let auditSearchTerm = '';

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function currentFilters() {
    return {
        from_date: document.getElementById('from_date-field').value,
        to_date: document.getElementById('to_date-field').value,
        user_id: document.getElementById('user_id-field').value,
    };
}

/* ==========================================================
   LOGIN ACTIVITY
========================================================== */

async function loadLoginActivity(page = 1) {

    loginCurrentPage = page;

    let filters = currentFilters();

    const params = new URLSearchParams({
        page: page,
        per_page: loginPerPage,
        from_date: filters.from_date,
        to_date: filters.to_date,
        user_id: filters.user_id,
    });

    const response = await fetch(`/activity-log/login-activity?${params.toString()}`);
    const result = await response.json();

    let tbody = document.getElementById('loginTableBody');
    tbody.innerHTML = '';

    loginLastPage = result.pagination.last_page;

    document.getElementById('loginPageNumber').innerText = `Page ${result.pagination.current_page}`;
    document.getElementById('login-pagination-info').innerText = `Total Records : ${result.pagination.total}`;

    let startSl = (result.pagination.current_page - 1) * loginPerPage;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No login activity found for this period.</td></tr>';
        return;
    }

    result.data.forEach((row, index) => {

        let statusBadge = row.still_active
            ? '<span class="badge bg-success">Active Session</span>'
            : '<span class="badge bg-secondary">Logged Out</span>';

        tbody.innerHTML += `
        <tr>
            <td>${startSl + index + 1}</td>
            <td>${escapeHtml(row.name)}</td>
            <td>${escapeHtml(row.user_role)}</td>
            <td>${row.login_time_fmt ?? '-'}</td>
            <td>${row.logout_time_fmt ?? '-'}</td>
            <td>${statusBadge}</td>
            <td>${escapeHtml(row.ip_address ?? '-')}</td>
        </tr>
        `;
    });
}

document.getElementById('loginPrevPage').addEventListener('click', function () {
    if (loginCurrentPage > 1) loadLoginActivity(loginCurrentPage - 1);
});

document.getElementById('loginNextPage').addEventListener('click', function () {
    if (loginCurrentPage < loginLastPage) loadLoginActivity(loginCurrentPage + 1);
});

document.getElementById('loginPerPage').addEventListener('change', function (e) {
    loginPerPage = parseInt(e.target.value);
    loadLoginActivity(1);
});

/* ==========================================================
   AUDIT / ACTION ACTIVITY
========================================================== */

async function loadAuditActivity(page = 1) {

    auditCurrentPage = page;

    let filters = currentFilters();

    const params = new URLSearchParams({
        page: page,
        per_page: auditPerPage,
        from_date: filters.from_date,
        to_date: filters.to_date,
        user_id: filters.user_id,
        module_code: document.getElementById('module_code-field').value,
        action: document.getElementById('action-field').value,
        search: auditSearchTerm,
    });

    const response = await fetch(`/activity-log/audit-activity?${params.toString()}`);
    const result = await response.json();

    let tbody = document.getElementById('auditTableBody');
    tbody.innerHTML = '';

    auditLastPage = result.pagination.last_page;

    document.getElementById('auditPageNumber').innerText = `Page ${result.pagination.current_page}`;
    document.getElementById('audit-pagination-info').innerText = `Total Records : ${result.pagination.total}`;

    let startSl = (result.pagination.current_page - 1) * auditPerPage;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No audit activity found for this period.</td></tr>';
        return;
    }

    let actionBadgeClass = {
        'CREATE': 'bg-success',
        'UPDATE': 'bg-warning',
        'DELETE': 'bg-danger',
        'LOGIN': 'bg-info',
        'LOGOUT': 'bg-secondary',
        'PRINT': 'bg-primary',
        'CONFIRM': 'bg-success',
        'WHATSAPP': 'bg-success',
        'CUSTOM': 'bg-secondary',
    };

    result.data.forEach((row, index) => {

        let badge = actionBadgeClass[row.action] ?? 'bg-secondary';

        tbody.innerHTML += `
        <tr>
            <td>${startSl + index + 1}</td>
            <td>${row.created_at_fmt ?? '-'}</td>
            <td>${escapeHtml(row.user_name)}</td>
            <td>${escapeHtml(row.user_role)}</td>
            <td>${escapeHtml(row.module_name_display ?? row.module_code)}</td>
            <td><span class="badge ${badge}">${escapeHtml(row.action)}</span></td>
            <td>${escapeHtml(row.table_name)}${row.record_id ? ' #' + escapeHtml(row.record_id) : ''}</td>
            <td>${escapeHtml(row.related_invoice_no ?? '-')}</td>
            <td>${escapeHtml(row.remarks ?? '-')}</td>
        </tr>
        `;
    });
}

document.getElementById('auditPrevPage').addEventListener('click', function () {
    if (auditCurrentPage > 1) loadAuditActivity(auditCurrentPage - 1);
});

document.getElementById('auditNextPage').addEventListener('click', function () {
    if (auditCurrentPage < auditLastPage) loadAuditActivity(auditCurrentPage + 1);
});

document.getElementById('auditPerPage').addEventListener('change', function (e) {
    auditPerPage = parseInt(e.target.value);
    loadAuditActivity(1);
});

document.getElementById('module_code-field').addEventListener('change', function () {
    loadAuditActivity(1);
});

document.getElementById('action-field').addEventListener('change', function () {
    loadAuditActivity(1);
});

let auditSearchDebounce;

document.getElementById('auditSearchInput').addEventListener('input', function (e) {

    clearTimeout(auditSearchDebounce);

    auditSearchDebounce = setTimeout(() => {
        auditSearchTerm = e.target.value;
        loadAuditActivity(1);
    }, 350);
});

/* ==========================================================
   SHARED FILTER
========================================================== */

document.getElementById('applyFilterBtn').addEventListener('click', function () {
    loadLoginActivity(1);
    loadAuditActivity(1);
});

/* ==========================================================
   INIT (default period: last 7 days)
========================================================== */

document.getElementById('to_date-field').value = new Date().toISOString().substring(0, 10);
document.getElementById('from_date-field').value = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().substring(0, 10);

loadLoginActivity();
loadAuditActivity();
