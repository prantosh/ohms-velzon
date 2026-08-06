let currentPage = 1;
let lastPage = 1;
let perPage = 10;
let searchTerm = '';
let requestSeq = 0;

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function fmtMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function loadStatus(page = 1) {

    currentPage = page;

    const mySeq = ++requestSeq;

    const params = new URLSearchParams({
        page: page,
        per_page: perPage,
        role: document.getElementById('role-field').value,
        status: document.getElementById('status-field').value,
        search: searchTerm,
    });

    const response = await fetch(`/membership-fee-status/list?${params.toString()}`);
    const result = await response.json();

    // discard this response if a newer request has been issued since (avoids
    // a slower earlier request overwriting the table with stale/unfiltered data)
    if (mySeq !== requestSeq) {
        return;
    }

    let tbody = document.getElementById('statusTableBody');
    tbody.innerHTML = '';

    lastPage = result.pagination.last_page;

    document.getElementById('pageNumber').innerText = `Page ${result.pagination.current_page}`;
    document.getElementById('pagination-info').innerText = `Total Records : ${result.pagination.total}`;

    let startSl = (result.pagination.current_page - 1) * perPage;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No members/admins/supervisors found.</td></tr>';
        return;
    }

    result.data.forEach((row, index) => {

        let statusBadge = row.status === 'overdue'
            ? '<span class="badge bg-danger">Overdue</span>'
            : '<span class="badge bg-success">Up To Date</span>';

        let overdueDisplay = row.months_overdue > 0
            ? `<span class="text-danger fw-semibold">${row.months_overdue}</span>`
            : '0';

        tbody.innerHTML += `
        <tr>
            <td>${startSl + index + 1}</td>
            <td>${escapeHtml(row.name)}</td>
            <td>${escapeHtml(row.mobile_no ?? '-')}</td>
            <td>${escapeHtml(row.role)}</td>
            <td>${escapeHtml(row.last_paid_month ?? 'Never Paid')}</td>
            <td>${escapeHtml(row.next_due_month)}</td>
            <td>${overdueDisplay}</td>
            <td class="text-end">${fmtMoney(row.total_due_amount)}</td>
            <td>${statusBadge}</td>
        </tr>
        `;
    });
}

document.addEventListener('click', function (e) {

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadStatus(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadStatus(currentPage + 1);
    }
});

document.addEventListener('change', function (e) {

    if (e.target.id === 'perPage') {
        perPage = parseInt(e.target.value);
        loadStatus(1);
    }

    if (e.target.id === 'role-field' || e.target.id === 'status-field') {
        loadStatus(1);
    }
});

let searchDebounce;

document.getElementById('searchInput').addEventListener('input', function (e) {

    clearTimeout(searchDebounce);

    searchDebounce = setTimeout(() => {
        searchTerm = e.target.value;
        loadStatus(1);
    }, 350);
});

loadStatus();
