let currentPage = 1;
let lastPage = 1;
let undeliveredCurrentPage = 1;
let undeliveredLastPage = 1;

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
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

function typeLabelBadge(typeLabel) {
    let cls = {
        'In-House': 'bg-secondary-subtle text-secondary',
        'Outsourced (Received)': 'bg-info-subtle text-info',
        'Outsourced (Delivered)': 'bg-success-subtle text-success',
    }[typeLabel] ?? 'bg-secondary-subtle text-secondary';

    return `<span class="badge ${cls}">${escapeHtml(typeLabel)}</span>`;
}

function updatePrintLink() {
    let params = new URLSearchParams(currentFilters());
    document.getElementById('printReportBtn').href = `/test-report-delivery-report/print?${params.toString()}`;
}

/*
|--------------------------------------------------------------------------
| DELIVERED TAB -- the append-only test_report_deliveries log
|--------------------------------------------------------------------------
*/

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
        tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">No delivery records found.</td></tr>';
        return;
    }

    result.data.forEach(row => {
        tbody.innerHTML += `
            <tr>
                <td>${escapeHtml(row.invoice_no)}</td>
                <td>${escapeHtml(row.invoice_date_fmt)}</td>
                <td>${escapeHtml(row.patient_name ?? '-')}</td>
                <td>${escapeHtml(row.patient_mobile_no ?? '-')}</td>
                <td>${typeLabelBadge(row.type_label)}</td>
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

    document.querySelectorAll('#dayRangeButtons button').forEach(b => b.classList.remove('active'));
    document.querySelector('#dayRangeButtons button[data-days="all"]').classList.add('active');

    loadReports(1);
});

// Quick day-range shortcuts -- just fill in the same From/To Date fields
// the manual pickers use, so a custom range still works afterwards and
// the backend needs no separate "days" filter of its own.
document.getElementById('dayRangeButtons').addEventListener('click', function (e) {

    let btn = e.target.closest('button');
    if (!btn) return;

    document.querySelectorAll('#dayRangeButtons button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    let days = btn.dataset.days;

    if (days === 'all') {
        setFlatpickrValue('fromDateFilter', '');
        setFlatpickrValue('toDateFilter', '');
    } else {
        let today = new Date();
        let from = new Date();
        from.setDate(today.getDate() - (parseInt(days, 10) - 1));

        setFlatpickrValue('fromDateFilter', from.toISOString().substring(0, 10));
        setFlatpickrValue('toDateFilter', today.toISOString().substring(0, 10));
    }

    loadReports(1);
});

/*
|--------------------------------------------------------------------------
| UNDELIVERED TAB -- invoices still owing an in-house and/or outsourced
| delivery. In-house and outsourced are tracked and delivered
| independently, since a mixed invoice (common) can have one side done
| while the other is still pending. Outsourced goes through an extra
| "Received from Lab" step before it can be marked delivered.
|--------------------------------------------------------------------------
*/

function inHouseStatusBadge(row) {

    if (row.in_house_total_tests === 0) {
        return '<span class="badge bg-secondary">N/A</span>';
    }

    let cls = {
        'Pending': 'bg-warning text-dark',
        'Partial': 'bg-info text-dark',
        'Complete': 'bg-success',
    }[row.in_house_result_status] ?? 'bg-secondary';

    let deliveredBadge = row.in_house_delivered
        ? ' <span class="badge bg-success-subtle text-success">Delivered</span>'
        : '';

    return `<span class="badge ${cls}">${row.in_house_result_status} (${row.in_house_results_entered}/${row.in_house_total_tests})</span>${deliveredBadge}`;
}

function outsourcedStatusBadge(row) {

    let cls = {
        'N/A': 'bg-secondary',
        'Pending': 'bg-warning text-dark',
        'Received': 'bg-info text-dark',
        'Delivered': 'bg-success',
    }[row.outsourced_status] ?? 'bg-secondary';

    let countSuffix = row.outsourced_total_tests > 0 ? ` (${row.outsourced_total_tests})` : '';

    return `<span class="badge ${cls}">${row.outsourced_status}${countSuffix}</span>`;
}

async function loadUndeliveredReports(page = 1) {

    undeliveredCurrentPage = page;

    let params = new URLSearchParams({
        page,
        per_page: document.querySelector('#undeliveredPerPage').value,
        search: document.querySelector('#undeliveredSearchInput').value.trim(),
    });

    const response = await fetch(`/test-report-delivery-report/undelivered?${params.toString()}`, {
        headers: { 'Accept': 'application/json' }
    });

    const result = await response.json();

    let tbody = document.querySelector('#undeliveredTableBody');
    tbody.innerHTML = '';

    if (!result.status) return;

    undeliveredLastPage = result.pagination.last_page;

    document.querySelector('#undeliveredPageNumber').innerText = `Page ${result.pagination.current_page}`;
    document.querySelector('#undelivered-pagination-info').innerText = `Total Records : ${result.pagination.total}`;
    document.querySelector('#undeliveredCount').innerText = result.pagination.total;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No undelivered reports found.</td></tr>';
        return;
    }

    result.data.forEach(row => {

        let actions = '';

        if (row.in_house_total_tests > 0 && !row.in_house_delivered) {

            let blockDeliver = row.in_house_result_status === 'Pending';

            actions += `
                <button class="btn btn-sm btn-soft-primary deliver-in-house-btn mb-1"
                        data-id="${row.id}"
                        data-payment-status="${row.payment_status}"
                        data-due-amount="${row.due_amount}"
                        title="${blockDeliver ? 'No test results entered yet' : 'Mark in-house report as delivered'}"
                        ${blockDeliver ? 'disabled' : ''}>
                    <i class="ri-checkbox-circle-line"></i>
                    Deliver In-House
                </button>
            `;
        }

        if (row.outsourced_status === 'Pending') {
            actions += `
                <button class="btn btn-sm btn-soft-info receive-outsourced-btn mb-1" data-id="${row.id}"
                        title="Mark outsourced report as received from the lab">
                    <i class="ri-download-2-line"></i>
                    Receive Outsourced
                </button>
            `;
        } else if (row.outsourced_status === 'Received') {
            actions += `
                <button class="btn btn-sm btn-soft-primary deliver-outsourced-btn mb-1"
                        data-id="${row.id}"
                        data-payment-status="${row.payment_status}"
                        data-due-amount="${row.due_amount}"
                        title="Mark outsourced report as delivered to patient">
                    <i class="ri-checkbox-circle-line"></i>
                    Deliver Outsourced
                </button>
            `;
        }

        tbody.innerHTML += `
            <tr>
                <td>${escapeHtml(row.invoice_no)}</td>
                <td>${escapeHtml(row.invoice_date ?? '-')}</td>
                <td>${escapeHtml(row.patient_name ?? '-')}</td>
                <td>${escapeHtml(row.patient_mobile_no ?? '-')}</td>
                <td>${paymentStatusBadge(row.payment_status)}</td>
                <td class="text-end">${row.due_amount.toFixed(2)}</td>
                <td>${inHouseStatusBadge(row)}</td>
                <td>${outsourcedStatusBadge(row)}</td>
                <td class="text-nowrap">${actions || '-'}</td>
            </tr>
        `;
    });
}

document.getElementById('undeliveredPrevPage').addEventListener('click', function () {
    if (undeliveredCurrentPage > 1) loadUndeliveredReports(undeliveredCurrentPage - 1);
});

document.getElementById('undeliveredNextPage').addEventListener('click', function () {
    if (undeliveredCurrentPage < undeliveredLastPage) loadUndeliveredReports(undeliveredCurrentPage + 1);
});

document.getElementById('undeliveredPerPage').addEventListener('change', () => loadUndeliveredReports(1));

let undeliveredSearchDebounce = null;
document.getElementById('undeliveredSearchInput').addEventListener('input', function () {
    clearTimeout(undeliveredSearchDebounce);
    undeliveredSearchDebounce = setTimeout(() => loadUndeliveredReports(1), 400);
});

document.getElementById('undeliveredResetFiltersBtn').addEventListener('click', function () {

    document.querySelector('#undeliveredSearchInput').value = '';
    document.querySelector('#undeliveredPerPage').value = '15';

    loadUndeliveredReports(1);
});

async function postAction(url) {

    const response = await fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken() }
    });

    return response.json();
}

function confirmDeliver(title, paymentStatus, dueAmount) {
    return (paymentStatus === 'Partial')
        ? Swal.fire({
            icon: 'warning',
            title: 'Payment is Partial',
            html: `This invoice still has a due amount of <b>&#8377;${dueAmount.toFixed(2)}</b>.<br>Are you sure you want to mark the report as delivered?`,
            showCancelButton: true,
            confirmButtonText: 'Yes, Mark as Delivered',
            confirmButtonColor: '#f7b84b',
        })
        : Swal.fire({
            icon: 'question',
            title: title,
            showCancelButton: true,
            confirmButtonText: 'Yes',
        });
}

document.getElementById('undeliveredTableBody').addEventListener('click', function (e) {

    let inHouseBtn = e.target.closest('.deliver-in-house-btn');
    if (inHouseBtn && !inHouseBtn.disabled) {

        let id = inHouseBtn.dataset.id;
        let paymentStatus = inHouseBtn.dataset.paymentStatus;
        let dueAmount = parseFloat(inHouseBtn.dataset.dueAmount || '0');

        confirmDeliver('Mark the in-house report as delivered?', paymentStatus, dueAmount).then(async function (confirmResult) {

            if (!confirmResult.isConfirmed) return;

            const result = await postAction(`/test-report-dashboard/toggle-delivered/${id}`);

            if (!result.status) {
                Swal.fire({ icon: 'error', title: 'Error', text: result.message ?? 'Unable to update delivery status.' });
                return;
            }

            Swal.fire({ icon: 'success', title: 'Delivered', timer: 1200, showConfirmButton: false });

            loadUndeliveredReports(undeliveredCurrentPage);
            loadReports(currentPage);
        });

        return;
    }

    let receiveBtn = e.target.closest('.receive-outsourced-btn');
    if (receiveBtn && !receiveBtn.disabled) {

        let id = receiveBtn.dataset.id;

        Swal.fire({
            icon: 'question',
            title: 'Mark outsourced report as received from the lab?',
            showCancelButton: true,
            confirmButtonText: 'Yes',
        }).then(async function (confirmResult) {

            if (!confirmResult.isConfirmed) return;

            const result = await postAction(`/test-report-delivery-report/mark-outsourced-received/${id}`);

            if (!result.status) {
                Swal.fire({ icon: 'error', title: 'Error', text: result.message ?? 'Unable to update status.' });
                return;
            }

            Swal.fire({ icon: 'success', title: 'Received', timer: 1200, showConfirmButton: false });

            loadUndeliveredReports(undeliveredCurrentPage);
            loadReports(currentPage);
        });

        return;
    }

    let deliverOutsourcedBtn = e.target.closest('.deliver-outsourced-btn');
    if (deliverOutsourcedBtn && !deliverOutsourcedBtn.disabled) {

        let id = deliverOutsourcedBtn.dataset.id;
        let paymentStatus = deliverOutsourcedBtn.dataset.paymentStatus;
        let dueAmount = parseFloat(deliverOutsourcedBtn.dataset.dueAmount || '0');

        confirmDeliver('Mark the outsourced report as delivered to the patient?', paymentStatus, dueAmount).then(async function (confirmResult) {

            if (!confirmResult.isConfirmed) return;

            const result = await postAction(`/test-report-delivery-report/mark-outsourced-delivered/${id}`);

            if (!result.status) {
                Swal.fire({ icon: 'error', title: 'Error', text: result.message ?? 'Unable to update status.' });
                return;
            }

            Swal.fire({ icon: 'success', title: 'Delivered', timer: 1200, showConfirmButton: false });

            loadUndeliveredReports(undeliveredCurrentPage);
            loadReports(currentPage);
        });

        return;
    }
});

document.addEventListener('DOMContentLoaded', function () {
    loadReports(1);
    loadUndeliveredReports(1);
});
