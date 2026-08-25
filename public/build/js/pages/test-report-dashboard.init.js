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

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

function currentFilters() {
    return {
        per_page: document.querySelector('#perPage').value,
        search: document.querySelector('#searchInput').value.trim(),
        invoice_category: document.querySelector('#categoryFilter').value,
        payment_status: document.querySelector('#paymentStatusFilter').value,
        delivery_status: document.querySelector('#deliveryStatusFilter').value,
        from_date: document.querySelector('#fromDateFilter').value,
        to_date: document.querySelector('#toDateFilter').value,
    };
}

function categoryLabel(category) {
    return category === 'PATHOLOGY'
        ? '<span class="badge bg-info text-dark">Pathology</span>'
        : category === 'NON_PATHOLOGY'
            ? '<span class="badge bg-secondary">Non-Pathology</span>'
            : '-';
}

function resultStatusBadge(row) {

    let cls = {
        'Pending': 'bg-warning text-dark',
        'Partial': 'bg-info text-dark',
        'Complete': 'bg-success',
        'N/A': 'bg-secondary'
    }[row.result_status] ?? 'bg-secondary';

    let confirmedBadge = row.confirmed
        ? ' <span class="badge bg-success-subtle text-success">Confirmed</span>'
        : '';

    return `<span class="badge ${cls}">${row.result_status} (${row.results_entered}/${row.total_tests})</span>${confirmedBadge}`;
}

function paymentStatusBadge(status) {
    let cls = {
        'Paid': 'bg-success',
        'Partial': 'bg-warning text-dark',
        'Due': 'bg-danger'
    }[status] ?? 'bg-secondary';

    return `<span class="badge ${cls}">${status}</span>`;
}

function deliveredCell(row) {
    return row.report_delivered_at
        ? `<span class="badge bg-success-subtle text-success" title="${row.report_delivered_at}">Delivered</span>`
        : `<span class="badge bg-secondary-subtle text-secondary">Not Delivered</span>`;
}

async function loadReports(page = 1) {

    currentPage = page;

    let filters = currentFilters();

    let params = new URLSearchParams({ page, ...filters });

    const response = await fetch(`/test-report-dashboard/list?${params.toString()}`, {
        headers: { 'Accept': 'application/json' }
    });

    const result = await response.json();

    let tbody = document.querySelector('#reportTableBody');
    tbody.innerHTML = '';

    if (!result.status) return;

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText = `Page ${result.pagination.current_page}`;
    document.querySelector('#pagination-info').innerText = `Total Records : ${result.pagination.total}`;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">No diagnostic test report invoices found.</td></tr>';
        return;
    }

    result.data.forEach(row => {

        let canPrintOrSend = row.confirmed;

        let actions = `
            <button class="btn btn-sm btn-soft-info print-report-btn me-1"
                    data-id="${row.id}"
                    data-invoice-no="${escapeHtml(row.invoice_no)}"
                    data-category="${row.invoice_category ?? ''}"
                    title="${canPrintOrSend ? 'Print Test Report' : 'Confirm the report before printing'}"
                    ${canPrintOrSend ? '' : 'disabled'}>
                <i class="ri-printer-line"></i>
            </button>

            <button class="btn btn-sm btn-soft-success whatsapp-report-btn me-1"
                    data-id="${row.id}"
                    data-invoice-no="${escapeHtml(row.invoice_no)}"
                    data-category="${row.invoice_category ?? ''}"
                    title="${canPrintOrSend ? 'Send Report via WhatsApp' : 'Confirm the report before sending'}"
                    ${canPrintOrSend ? '' : 'disabled'}>
                <i class="ri-whatsapp-line"></i>
            </button>
        `;

        if (row.due_amount > 0) {
            actions += `
            <a href="/diagnostic-invoice/edit/${row.id}"
               class="btn btn-sm btn-soft-warning me-1"
               title="Make Final Payment">
                <i class="ri-money-rupee-circle-line"></i>
            </a>
            `;
        }

        if (row.can_edit_patient_name) {
            actions += `
            <button class="btn btn-sm btn-soft-dark edit-patient-name-btn me-1"
                    data-id="${row.id}"
                    data-current-name="${escapeHtml(row.patient_name ?? '')}"
                    title="Fix Patient Name (typo correction)">
                <i class="ri-edit-line"></i>
            </button>
            `;
        }

        let blockDeliver = row.result_status === 'Pending' && !row.report_delivered_at;

        actions += `
            <button class="btn btn-sm ${row.report_delivered_at ? 'btn-soft-secondary' : 'btn-soft-primary'} toggle-delivered-btn"
                    data-id="${row.id}"
                    data-delivered="${row.report_delivered_at ? '1' : '0'}"
                    data-payment-status="${row.payment_status}"
                    data-due-amount="${row.due_amount}"
                    title="${blockDeliver ? 'No test results entered yet' : (row.report_delivered_at ? 'Mark as Not Delivered' : 'Mark as Delivered')}"
                    ${blockDeliver ? 'disabled' : ''}>
                <i class="ri-${row.report_delivered_at ? 'close-circle-line' : 'checkbox-circle-line'}"></i>
            </button>
        `;

        tbody.innerHTML += `
            <tr>
                <td>${row.invoice_no}</td>
                <td>${row.patient_name ?? '-'}<br><small class="text-muted">${row.patient_mobile_no ?? ''}</small></td>
                <td>${categoryLabel(row.invoice_category)}</td>
                <td>${row.invoice_date ?? '-'}</td>
                <td>${row.total_tests}</td>
                <td>${resultStatusBadge(row)}</td>
                <td>${paymentStatusBadge(row.payment_status)}</td>
                <td class="text-end">${row.paid_amount.toFixed(2)}</td>
                <td class="text-end">${row.due_amount.toFixed(2)}</td>
                <td>${deliveredCell(row)}</td>
                <td class="text-nowrap">${actions}</td>
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
document.getElementById('categoryFilter').addEventListener('change', () => loadReports(1));
document.getElementById('paymentStatusFilter').addEventListener('change', () => loadReports(1));
document.getElementById('deliveryStatusFilter').addEventListener('change', () => loadReports(1));
document.getElementById('fromDateFilter').addEventListener('change', () => loadReports(1));
document.getElementById('toDateFilter').addEventListener('change', () => loadReports(1));

let searchDebounce = null;
document.getElementById('searchInput').addEventListener('input', function () {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => loadReports(1), 400);
});

document.getElementById('resetFiltersBtn').addEventListener('click', function () {

    document.querySelector('#searchInput').value = '';
    document.querySelector('#categoryFilter').value = '';
    document.querySelector('#paymentStatusFilter').value = '';
    document.querySelector('#deliveryStatusFilter').value = '';
    setFlatpickrValue('fromDateFilter', '');
    setFlatpickrValue('toDateFilter', '');
    document.querySelector('#perPage').value = '15';

    loadReports(1);
});

document.getElementById('reportTableBody').addEventListener('click', async function (e) {

    let printBtn = e.target.closest('.print-report-btn');
    if (printBtn && !printBtn.disabled) {

        // A Pathology invoice can now have several independent narrative
        // reports (see PathologyReportController) -- rather than guess
        // which one to print, send staff to Test Result Entry's Pathology
        // tab, where each report has its own Print/WhatsApp button.
        if (printBtn.dataset.category === 'PATHOLOGY') {
            window.open(`/test-result-entry?open=${encodeURIComponent(printBtn.dataset.invoiceNo)}`, '_blank');
            return;
        }

        window.open(`/test-result-entry/print/${printBtn.dataset.id}`, '_blank');
        return;
    }

    let whatsappBtn = e.target.closest('.whatsapp-report-btn');
    if (whatsappBtn && !whatsappBtn.disabled) {

        if (whatsappBtn.dataset.category === 'PATHOLOGY') {
            window.open(`/test-result-entry?open=${encodeURIComponent(whatsappBtn.dataset.invoiceNo)}`, '_blank');
            return;
        }

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

        return;
    }

    let editNameBtn = e.target.closest('.edit-patient-name-btn');
    if (editNameBtn) {

        let id = editNameBtn.dataset.id;
        let currentName = editNameBtn.dataset.currentName;

        const { value: newName } = await Swal.fire({
            icon: 'warning',
            title: 'Fix Patient Name',
            html: 'Correcting a typo made during invoice creation. This permanently updates the patient\'s master record.',
            input: 'text',
            inputValue: currentName,
            inputLabel: 'Corrected Patient Name',
            showCancelButton: true,
            confirmButtonText: 'Update',
            inputValidator: (value) => {
                if (!value || !value.trim()) return 'Patient name cannot be empty.';
            }
        });

        if (!newName) return;

        const response = await fetch(`/test-report-dashboard/update-patient-name/${id}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ patient_name: newName.trim() }),
        });

        const result = await response.json();

        if (!result.status) {
            Swal.fire({ icon: 'error', title: 'Error', text: result.message ?? 'Unable to update patient name.' });
            return;
        }

        Swal.fire({ icon: 'success', title: 'Updated', text: 'Patient name has been updated.', timer: 1500, showConfirmButton: false });

        loadReports(currentPage);

        return;
    }

    let toggleBtn = e.target.closest('.toggle-delivered-btn');
    if (toggleBtn && !toggleBtn.disabled) {

        let id = toggleBtn.dataset.id;
        let currentlyDelivered = toggleBtn.dataset.delivered === '1';
        let paymentStatus = toggleBtn.dataset.paymentStatus;
        let dueAmount = parseFloat(toggleBtn.dataset.dueAmount || '0');

        let confirmPromise = (!currentlyDelivered && paymentStatus === 'Partial')
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
                title: currentlyDelivered ? 'Mark as not delivered?' : 'Mark this report as delivered?',
                showCancelButton: true,
                confirmButtonText: 'Yes',
            });

        confirmPromise.then(async function (confirmResult) {

            if (!confirmResult.isConfirmed) return;

            const response = await fetch(`/test-report-dashboard/toggle-delivered/${id}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken() }
            });

            const result = await response.json();

            if (!result.status) {
                Swal.fire({ icon: 'error', title: 'Error', text: result.message ?? 'Unable to update delivery status.' });
                return;
            }

            loadReports(currentPage);
        });

        return;
    }
});

document.addEventListener('DOMContentLoaded', function () {
    loadReports(1);
});
