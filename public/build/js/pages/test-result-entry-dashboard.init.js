"use strict";

/*
|--------------------------------------------------------------------------
| TEST RESULT ENTRY DASHBOARD
|--------------------------------------------------------------------------
| Lists diagnostic invoices (Pathology / Non-Pathology tabs) with a date
| range filter and search, and drives the entry modal (searchInvoice() /
| #invoiceNoInput etc. are defined in test-result-entry.init.js, loaded
| before this file on the same page).
*/

let dashCategory = 'PATHOLOGY';
let dashRange = '3';
let dashSearch = '';
let dashPage = 1;
let dashLastPage = 1;

function resultStatusBadgeHtml(row) {

    let cls = {
        'Pending': 'result-status-Pending',
        'Partial': 'result-status-Partial',
        'Complete': 'result-status-Complete',
        'N/A': 'result-status-NA'
    }[row.result_status] ?? 'result-status-NA';

    return `<span class="badge ${cls}">${escapeHtml(row.result_status)} (${row.results_entered}/${row.total_tests})</span>`;
}

function confirmedCellHtml(row) {
    return row.confirmed
        ? '<span class="badge bg-success-subtle text-success"><i class="ri-check-double-line"></i> Confirmed</span>'
        : '<span class="badge bg-secondary-subtle text-secondary">Not Confirmed</span>';
}

function actionCellHtml(row) {

    if (row.confirmed) {
        return `
        <button type="button" class="btn btn-sm btn-soft-info print-result-btn me-1" data-id="${row.id}" title="Print Report">
            <i class="ri-printer-line"></i>
        </button>
        <button type="button" class="btn btn-sm btn-soft-success whatsapp-result-btn" data-id="${row.id}" title="Send via WhatsApp">
            <i class="ri-whatsapp-line"></i>
        </button>
        `;
    }

    // Complete but not yet confirmed -- open the same modal read-only
    // (no editing of result values) so the user can review and hit
    // Confirm from there; Print/WhatsApp only unlock once confirmed.
    if (row.result_status === 'Complete') {
        return `
        <button type="button" class="btn btn-sm btn-outline-primary enter-result-btn"
                data-invoice-no="${escapeHtml(row.invoice_no)}"
                data-readonly="1">
            <i class="ri-eye-line"></i>
            Show Result
        </button>
        `;
    }

    return `
    <button type="button" class="btn btn-sm btn-primary enter-result-btn"
            data-invoice-no="${escapeHtml(row.invoice_no)}">
        <i class="ri-edit-2-line"></i>
        Enter Results
    </button>
    `;
}

async function loadDashboard(page = 1) {

    dashPage = page;

    let params = new URLSearchParams({
        invoice_category: dashCategory,
        range: dashRange,
        page: dashPage
    });

    if (dashSearch) {
        params.set('search', dashSearch);
    }

    const response = await fetch(`/test-result-entry/list?${params.toString()}`);
    const result = await response.json();

    let tbody = document.getElementById('dashTableBody');
    tbody.innerHTML = '';

    if (!result.status || !result.data.length) {

        document.getElementById('dashNoDataWrap').style.display = 'block';
        document.getElementById('dashPaginationInfo').innerText = '';
        dashLastPage = 1;
        return;
    }

    document.getElementById('dashNoDataWrap').style.display = 'none';

    result.data.forEach(function (row) {

        tbody.innerHTML += `
        <tr>
            <td class="fw-semibold">${escapeHtml(row.invoice_no)}</td>
            <td>${escapeHtml(row.invoice_date ?? '')}</td>
            <td>${escapeHtml(row.patient_name ?? '')}</td>
            <td>${escapeHtml(row.patient_mobile_no ?? '')}</td>
            <td class="text-center">${row.total_tests}</td>
            <td class="text-center">${resultStatusBadgeHtml(row)}</td>
            <td class="text-center">${confirmedCellHtml(row)}</td>
            <td class="text-center text-nowrap">${actionCellHtml(row)}</td>
        </tr>
        `;
    });

    dashLastPage = result.pagination.last_page;

    document.getElementById('dashPaginationInfo').innerText =
        `Page ${result.pagination.current_page} of ${result.pagination.last_page} (${result.pagination.total} total)`;
}

/*
|--------------------------------------------------------------------------
| FILTER EVENTS
|--------------------------------------------------------------------------
*/

document.querySelectorAll('#categoryTabs .nav-link').forEach(function (tab) {

    tab.addEventListener('click', function () {

        document.querySelectorAll('#categoryTabs .nav-link').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');

        dashCategory = tab.dataset.category;
        loadDashboard(1);
    });
});

document.querySelectorAll('#rangeButtons [data-range]').forEach(function (btn) {

    btn.addEventListener('click', function () {

        document.querySelectorAll('#rangeButtons [data-range]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        dashRange = btn.dataset.range;
        loadDashboard(1);
    });
});

let dashSearchTimer = null;

document.getElementById('dashSearchInput').addEventListener('input', function () {

    let value = this.value;

    clearTimeout(dashSearchTimer);

    dashSearchTimer = setTimeout(function () {
        dashSearch = value.trim();
        loadDashboard(1);
    }, 400);
});

document.getElementById('dashPrevPage').addEventListener('click', function () {
    if (dashPage > 1) loadDashboard(dashPage - 1);
});

document.getElementById('dashNextPage').addEventListener('click', function () {
    if (dashPage < dashLastPage) loadDashboard(dashPage + 1);
});

/*
|--------------------------------------------------------------------------
| ROW ACTIONS
|--------------------------------------------------------------------------
*/

document.addEventListener('click', async function (e) {

    let enterBtn = e.target.closest('.enter-result-btn');
    if (enterBtn) {

        let invoiceNo = enterBtn.dataset.invoiceNo;

        let modalEl = document.getElementById('resultEntryModal');
        let modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        isReadOnlyView = enterBtn.dataset.readonly === '1';

        document.getElementById('resultEntryModalTitle').innerText =
            isReadOnlyView ? 'Show Result' : 'Enter Test Results';

        document.getElementById('invoiceNoInput').value = invoiceNo;
        searchInvoice();

        return;
    }

    let printBtn = e.target.closest('.print-result-btn');
    if (printBtn) {
        window.open(`/test-result-entry/print/${printBtn.dataset.id}`, '_blank');
        return;
    }

    let whatsappBtn = e.target.closest('.whatsapp-result-btn');
    if (whatsappBtn) {

        let id = whatsappBtn.dataset.id;

        whatsappBtn.disabled = true;

        Swal.fire({
            title: 'Please wait...',
            text: 'We are sending WhatsApp message',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        const response = await fetch(`/test-result-entry/send-whatsapp/${id}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() }
        });

        const result = await response.json();

        whatsappBtn.disabled = false;

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Sent' : 'Error',
            text: result.message
        });
    }
});

// Refresh the dashboard list once the modal closes, in case results were
// entered/confirmed -- the row's status/action column would otherwise
// stay stale until the next full reload.
document.getElementById('resultEntryModal').addEventListener('hidden.bs.modal', function () {
    loadDashboard(dashPage);
});

loadDashboard(1);
