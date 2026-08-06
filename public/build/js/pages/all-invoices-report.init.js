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

const tabs = ['all', 'pending', 'cancelled'];

let tabState = {
    all: { currentPage: 1, lastPage: 1, perPage: 10, loaded: false },
    pending: { currentPage: 1, lastPage: 1, perPage: 10, loaded: false },
    cancelled: { currentPage: 1, lastPage: 1, perPage: 10, loaded: false },
};

function currentDate() {
    return document.getElementById('invoice_date-field').value;
}

function currentSearch() {
    return document.getElementById('search-field').value.trim();
}

function statusBadge(row) {
    if (row.is_cancelled) {
        return '<span class="badge bg-danger-subtle text-danger">Cancelled</span>';
    }
    if (row.is_pending) {
        return '<span class="badge bg-warning-subtle text-warning">Pending</span>';
    }
    return '<span class="badge bg-success-subtle text-success">' + escapeHtml(row.status || 'Paid') + '</span>';
}

function printButtonHtml(row) {
    if (!row.print_url) {
        return '-';
    }
    return `<a href="${row.print_url}" target="_blank" class="btn btn-sm btn-outline-primary" title="Print Invoice"><i class="ri-printer-line"></i></a>`;
}

async function loadTab(tabKey, page = 1) {

    let date = currentDate();

    if (!date) {
        return;
    }

    let state = tabState[tabKey];
    state.currentPage = page;

    const params = new URLSearchParams({
        date: date,
        tab: tabKey,
        search: currentSearch(),
        per_page: state.perPage,
        page: page,
    });

    const response = await fetch(`/all-invoices-report/list?${params.toString()}`);
    const result = await response.json();

    let tbody = document.getElementById(`tableBody-${tabKey}`);
    tbody.innerHTML = '';

    state.lastPage = result.pagination.last_page;

    document.getElementById(`pageNumber-${tabKey}`).innerText = `Page ${result.pagination.current_page}`;
    document.getElementById(`pagination-info-${tabKey}`).innerText = `Total Records : ${result.pagination.total}`;

    let startSl = (result.pagination.current_page - 1) * state.perPage;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="14" class="text-center text-muted">No invoices found.</td></tr>';
        state.loaded = true;
        return;
    }

    result.data.forEach((row, index) => {
        tbody.innerHTML += `
        <tr>
            <td>${startSl + index + 1}</td>
            <td>${escapeHtml(row.invoice_date_fmt)}</td>
            <td class="fw-semibold">${escapeHtml(row.invoice_no)}</td>
            <td>${escapeHtml(row.invoice_type_label)}</td>
            <td>${escapeHtml(row.patient_name) || '-'}</td>
            <td>${escapeHtml(row.patient_mobile_no) || '-'}</td>
            <td>${escapeHtml(row.doctor_display)}</td>
            <td class="text-end">${fmtMoney(row.total_amount)}</td>
            <td class="text-end">${fmtMoney(row.paid_amount)}</td>
            <td class="text-end">${fmtMoney(row.due_amount)}</td>
            <td class="text-center">${escapeHtml(row.payment_mode) || '-'}</td>
            <td class="text-center">${statusBadge(row)}</td>
            <td>${escapeHtml(row.created_by_name)}</td>
            <td class="text-center">${printButtonHtml(row)}</td>
        </tr>
        `;
    });

    state.loaded = true;
}

async function loadCounts() {

    let date = currentDate();

    if (!date) {
        return;
    }

    const params = new URLSearchParams({
        date: date,
        search: currentSearch(),
    });

    const response = await fetch(`/all-invoices-report/counts?${params.toString()}`);
    const result = await response.json();

    if (!result.status) return;

    document.getElementById('badge-all').innerText = result.counts.all;
    document.getElementById('badge-pending').innerText = result.counts.pending;
    document.getElementById('badge-cancelled').innerText = result.counts.cancelled;
}

function activeTabKey() {
    let activeLink = document.querySelector('.nav-tabs-custom .nav-link.active');
    return activeLink ? activeLink.dataset.tab : 'all';
}

async function loadReport() {

    if (!currentDate()) {
        Swal.fire({ icon: 'warning', title: 'Missing Date', text: 'Please select an invoice date.' });
        return;
    }

    tabs.forEach(t => { tabState[t].loaded = false; tabState[t].currentPage = 1; });

    await loadCounts();
    await loadTab(activeTabKey(), 1);
}

document.getElementById('loadReportBtn').addEventListener('click', loadReport);

document.querySelectorAll('.nav-tabs-custom [data-bs-toggle="tab"]').forEach(tabLink => {
    tabLink.addEventListener('shown.bs.tab', function () {
        let tabKey = this.dataset.tab;
        if (!tabState[tabKey].loaded) {
            loadTab(tabKey, 1);
        }
    });
});

document.querySelectorAll('.perPage-field').forEach(select => {
    select.addEventListener('change', function () {
        let tabKey = this.dataset.tab;
        tabState[tabKey].perPage = parseInt(this.value);
        loadTab(tabKey, 1);
    });
});

document.querySelectorAll('.prevPage-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        let tabKey = this.dataset.tab;
        if (tabState[tabKey].currentPage > 1) {
            loadTab(tabKey, tabState[tabKey].currentPage - 1);
        }
    });
});

document.querySelectorAll('.nextPage-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        let tabKey = this.dataset.tab;
        if (tabState[tabKey].currentPage < tabState[tabKey].lastPage) {
            loadTab(tabKey, tabState[tabKey].currentPage + 1);
        }
    });
});

/* ==========================================================
   INIT (default: today)
========================================================== */

setFlatpickrValue('invoice_date-field', new Date().toISOString().substring(0, 10));

loadReport();
