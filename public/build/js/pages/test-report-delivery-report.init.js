let currentPage = 1;
let lastPage = 1;

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
        per_page: document.querySelector('#perPage').value,
        search: document.querySelector('#searchInput').value.trim(),
        user_id: document.querySelector('#userFilter').value,
        from_date: document.querySelector('#fromDateFilter').value,
        to_date: document.querySelector('#toDateFilter').value,
    };
}

function paymentStatusBadge(status) {
    let cls = {
        'Paid': 'bg-success',
        'Partial': 'bg-warning text-dark',
        'Due': 'bg-danger'
    }[status] ?? 'bg-secondary';

    return `<span class="badge ${cls}">${status}</span>`;
}

function updatePrintLink() {
    let params = new URLSearchParams(currentFilters());
    document.getElementById('printReportBtn').href = `/test-report-delivery-report/print?${params.toString()}`;
}

async function loadReports(page = 1) {

    currentPage = page;

    let filters = currentFilters();

    let params = new URLSearchParams({ page, ...filters });

    const response = await fetch(`/test-report-delivery-report/list?${params.toString()}`, {
        headers: { 'Accept': 'application/json' }
    });

    const result = await response.json();

    let tbody = document.querySelector('#reportTableBody');
    tbody.innerHTML = '';

    updatePrintLink();

    if (!result.status) return;

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText = `Page ${result.pagination.current_page}`;
    document.querySelector('#pagination-info').innerText = `Total Records : ${result.pagination.total}`;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No delivery records found.</td></tr>';
        return;
    }

    result.data.forEach(row => {
        tbody.innerHTML += `
            <tr>
                <td>${escapeHtml(row.invoice_no)}</td>
                <td>${escapeHtml(row.patient_name ?? '-')}<br><small class="text-muted">${escapeHtml(row.patient_mobile_no ?? '')}</small></td>
                <td>${escapeHtml(row.delivered_by_name)}</td>
                <td>${escapeHtml(row.delivered_at_fmt)}</td>
                <td>${paymentStatusBadge(row.payment_status)}</td>
                <td class="text-end">${row.total_amount.toFixed(2)}</td>
                <td class="text-end">${row.paid_amount.toFixed(2)}</td>
                <td class="text-end">${row.due_amount.toFixed(2)}</td>
            </tr>
        `;
    });
}

document.getElementById('prevPage').addEventListener('click', function () {
    if (currentPage > 1) loadReports(currentPage - 1);
});

document.getElementById('nextPage').addEventListener('click', function () {
    if (currentPage < lastPage) loadReports(currentPage + 1);
});

document.getElementById('perPage').addEventListener('change', () => loadReports(1));
document.getElementById('userFilter').addEventListener('change', () => loadReports(1));
document.getElementById('fromDateFilter').addEventListener('change', () => loadReports(1));
document.getElementById('toDateFilter').addEventListener('change', () => loadReports(1));

let searchDebounce = null;
document.getElementById('searchInput').addEventListener('input', function () {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => loadReports(1), 400);
});

document.getElementById('resetFiltersBtn').addEventListener('click', function () {

    document.querySelector('#searchInput').value = '';
    document.querySelector('#userFilter').value = 'ALL';
    setFlatpickrValue('fromDateFilter', '');
    setFlatpickrValue('toDateFilter', '');
    document.querySelector('#perPage').value = '15';

    loadReports(1);
});

document.addEventListener('DOMContentLoaded', function () {
    loadReports(1);
});
