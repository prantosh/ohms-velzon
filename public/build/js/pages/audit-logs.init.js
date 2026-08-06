let currentPage = 1;
let lastPage = 1;
let perPage = 10;

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
        module_code: document.getElementById('module_code-field').value,
        action: document.getElementById('action-field').value,
        ip_address: document.getElementById('ip_address-field').value.trim(),
        search: document.getElementById('search-field').value.trim(),
        per_page: perPage,
    };
}

const actionBadgeClass = {
    CREATE: 'bg-success-subtle text-success',
    UPDATE: 'bg-warning-subtle text-warning',
    DELETE: 'bg-danger-subtle text-danger',
    LOGIN: 'bg-info-subtle text-info',
    LOGOUT: 'bg-secondary-subtle text-secondary',
    PRINT: 'bg-primary-subtle text-primary',
    CONFIRM: 'bg-success-subtle text-success',
    EXPORT_EXCEL: 'bg-primary-subtle text-primary',
    EXPORT_PDF: 'bg-primary-subtle text-primary',
    WHATSAPP: 'bg-success-subtle text-success',
    CUSTOM: 'bg-dark-subtle text-dark',
};

async function loadAuditLogs(page = 1) {

    currentPage = page;

    let params = new URLSearchParams(currentFilters());
    params.set('page', page);

    const response = await fetch(`/audit-logs/list?${params.toString()}`);
    const result = await response.json();

    let tbody = document.getElementById('auditTableBody');
    tbody.innerHTML = '';

    lastPage = result.pagination.last_page;

    document.getElementById('pageNumber').innerText = `Page ${result.pagination.current_page}`;
    document.getElementById('pagination-info').innerText = `Total Records : ${result.pagination.total}`;

    let startSl = (result.pagination.current_page - 1) * perPage;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted">No audit records found for the selected filters.</td></tr>';
        return;
    }

    result.data.forEach((row, index) => {

        let badgeClass = actionBadgeClass[row.action] || 'bg-secondary-subtle text-secondary';

        tbody.innerHTML += `
        <tr>
            <td>${startSl + index + 1}</td>
            <td>${escapeHtml(row.created_at_fmt)}</td>
            <td>${escapeHtml(row.user_name) || '-'}</td>
            <td>${escapeHtml(row.user_role_snapshot ?? '')}</td>
            <td>${escapeHtml(row.module_name_display)}</td>
            <td><span class="badge ${badgeClass}">${escapeHtml(row.action)}</span></td>
            <td>${escapeHtml(row.table_name)}${row.record_id ? ' #' + escapeHtml(row.record_id) : ''}</td>
            <td>${escapeHtml(row.remarks) || '-'}</td>
            <td>${escapeHtml(row.ip_address) || '-'}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-soft-info view-detail-btn" data-id="${row.id}" title="View Detail">
                    <i class="ri-eye-line"></i>
                </button>
            </td>
        </tr>
        `;
    });
}

document.getElementById('applyFilterBtn').addEventListener('click', () => loadAuditLogs(1));

document.getElementById('prevPage').addEventListener('click', () => {
    if (currentPage > 1) loadAuditLogs(currentPage - 1);
});

document.getElementById('nextPage').addEventListener('click', () => {
    if (currentPage < lastPage) loadAuditLogs(currentPage + 1);
});

document.getElementById('perPage').addEventListener('change', function () {
    perPage = parseInt(this.value);
    loadAuditLogs(1);
});

/* ==========================================================
   DETAIL MODAL
========================================================== */

function renderDataTable(title, dataObj) {

    if (!dataObj || !Object.keys(dataObj).length) return '';

    let rows = Object.entries(dataObj).map(([field, value]) => `
        <tr>
            <td class="fw-semibold">${escapeHtml(field)}</td>
            <td>${escapeHtml(typeof value === 'object' ? JSON.stringify(value) : value)}</td>
        </tr>
    `).join('');

    return `
    <h6 class="mt-3">${title}</h6>
    <div class="table-responsive">
        <table class="table table-sm table-bordered">
            <tbody>${rows}</tbody>
        </table>
    </div>
    `;
}

function renderChangedDataTable(oldData, changedData) {

    if (!changedData || !Object.keys(changedData).length) return '';

    let rows = Object.keys(changedData).map(field => {
        let oldVal = oldData && Object.prototype.hasOwnProperty.call(oldData, field) ? oldData[field] : null;
        let newVal = changedData[field];
        return `
        <tr>
            <td class="fw-semibold">${escapeHtml(field)}</td>
            <td class="text-danger">${escapeHtml(oldVal === null ? '(none)' : (typeof oldVal === 'object' ? JSON.stringify(oldVal) : oldVal))}</td>
            <td class="text-success">${escapeHtml(typeof newVal === 'object' ? JSON.stringify(newVal) : newVal)}</td>
        </tr>
        `;
    }).join('');

    return `
    <h6 class="mt-3">Changed Fields</h6>
    <div class="table-responsive">
        <table class="table table-sm table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Field</th>
                    <th>Old Value</th>
                    <th>New Value</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
    </div>
    `;
}

document.getElementById('auditTableBody').addEventListener('click', async function (e) {

    let btn = e.target.closest('.view-detail-btn');
    if (!btn) return;

    let modalEl = document.getElementById('detailModal');
    let modal = new bootstrap.Modal(modalEl);
    let body = document.getElementById('detailModalBody');
    body.innerHTML = '<div class="text-center text-muted py-4">Loading...</div>';
    modal.show();

    const response = await fetch(`/audit-logs/show/${btn.dataset.id}`);
    const result = await response.json();

    if (!result.status) {
        body.innerHTML = '<div class="text-danger">Could not load this record.</div>';
        return;
    }

    let d = result.data;

    let metaHtml = `
    <table class="table table-sm table-borderless mb-0">
        <tr><td class="fw-semibold" width="160">Date/Time</td><td>${escapeHtml(d.created_at_fmt)}</td></tr>
        <tr><td class="fw-semibold">User</td><td>${escapeHtml(d.user_name) || '-'} ${d.user_role ? '(' + escapeHtml(d.user_role) + ')' : ''}</td></tr>
        <tr><td class="fw-semibold">Module</td><td>${escapeHtml(d.module_name)}</td></tr>
        <tr><td class="fw-semibold">Action</td><td>${escapeHtml(d.action)}</td></tr>
        <tr><td class="fw-semibold">Table / Record</td><td>${escapeHtml(d.table_name)}${d.record_id ? ' #' + escapeHtml(d.record_id) : ''}</td></tr>
        <tr><td class="fw-semibold">IP Address</td><td>${escapeHtml(d.ip_address) || '-'}</td></tr>
        <tr><td class="fw-semibold">Remarks</td><td>${escapeHtml(d.remarks) || '-'}</td></tr>
        <tr><td class="fw-semibold">Request URL</td><td class="text-break">${escapeHtml(d.request_url) || '-'}</td></tr>
        <tr><td class="fw-semibold">User Agent</td><td class="text-break small text-muted">${escapeHtml(d.user_agent) || '-'}</td></tr>
    </table>
    `;

    let dataHtml = '';

    if (d.action === 'UPDATE' && d.changed_data) {
        dataHtml = renderChangedDataTable(d.old_data, d.changed_data);
    } else if (d.action === 'CREATE' && d.new_data) {
        dataHtml = renderDataTable('Created With', d.new_data);
    } else if (d.action === 'DELETE' && d.old_data) {
        dataHtml = renderDataTable('Deleted Record', d.old_data);
    }

    if (!dataHtml) {
        dataHtml = '<p class="text-muted mt-3 mb-0">No field-level data changes recorded for this action.</p>';
    }

    body.innerHTML = metaHtml + dataHtml;
});

/* ==========================================================
   INIT (default: last 7 days)
========================================================== */

setFlatpickrValue('to_date-field', new Date().toISOString().substring(0, 10));
setFlatpickrValue('from_date-field', new Date(Date.now() - 6 * 24 * 60 * 60 * 1000).toISOString().substring(0, 10));

loadAuditLogs();
