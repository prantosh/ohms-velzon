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

// Where a given invoice's report(s) actually live -- Pathology, USG,
// Cardiology and Non-Pathology (Rest Test Result Entry) are each their OWN
// standalone page now, so routing by print_route (computed server-side from
// the invoice's actual item codes) instead of a blanket category check
// avoids sending staff to a page that 403s with "must be confirmed" even
// though the report genuinely is confirmed, just through a different
// module's own confirmed_at.
function printRouteUrl(printRoute, invoiceNo) {

    let base = {
        pathology: '/test-result-entry',
        usg: '/usg-report',
        cardiology: '/cardiology-report',
        non_pathology: '/non-pathology-report',
    }[printRoute];

    if (!base) return null;

    let params = new URLSearchParams({ open: invoiceNo });

    return `${base}?${params.toString()}`;
}

function currentFilters() {
    return {
        per_page: document.querySelector('#perPage').value,
        search: document.querySelector('#searchInput').value.trim(),
        invoice_category: document.querySelector('#categoryFilter').value,
        payment_status: document.querySelector('#paymentStatusFilter').value,
        result_status: document.querySelector('#resultStatusFilter').value,
        range: document.querySelector('#rangeFilter').value,
        delivery_status: document.querySelector('#deliveryFilter').value,
    };
}

function categoryLabel(category) {
    return category === 'PATHOLOGY'
        ? '<span class="badge bg-info text-dark">Pathology</span>'
        : category === 'NON_PATHOLOGY'
            ? '<span class="badge bg-secondary">Non-Pathology</span>'
            : '-';
}

// Display wording for the internal status codes the server (and the filter
// values) still use.
const RESULT_STATUS_LABELS = {
    'Pending': 'No Report Prepared',
    'Partial': 'Prepared Partially',
    'Confirmation Pending': 'Confirmation Pending',
    'Complete': 'Completed',
    'N/A': 'N/A'
};

function resultStatusBadge(row) {

    let cls = {
        'Pending': 'bg-warning text-dark',
        'Partial': 'bg-info text-dark',
        'Confirmation Pending': 'bg-primary',
        'Complete': 'bg-success',
        'N/A': 'bg-secondary'
    }[row.result_status] ?? 'bg-secondary';

    let confirmedBadge = row.confirmed
        ? ' <span class="badge bg-success-subtle text-success">Confirmed</span>'
        : '';

    return `<span class="badge ${cls}">${RESULT_STATUS_LABELS[row.result_status] ?? row.result_status} (${row.results_entered}/${row.total_tests})</span>${confirmedBadge}`;
}

function paymentStatusBadge(status) {
    let cls = {
        'Paid': 'bg-success',
        'Partial': 'bg-warning text-dark',
        'Due': 'bg-danger'
    }[status] ?? 'bg-secondary';

    return `<span class="badge ${cls}">${status}</span>`;
}

// Bumped on every load so a slow, superseded response (e.g. the user kept
// typing in Search, or changed a filter) can't overwrite a newer one.
let loadSequence = 0;

function setLoadingState(isLoading) {

    // The Search box is left alone on purpose -- locking it would interrupt
    // someone still typing; loadSequence already discards stale responses.
    document.querySelectorAll('#categoryFilter, #paymentStatusFilter, #resultStatusFilter, #rangeFilter, #deliveryFilter, #perPage, #prevPage, #nextPage')
        .forEach(el => { el.disabled = isLoading; });
}

async function loadReports(page = 1) {

    currentPage = page;

    let thisLoad = ++loadSequence;

    let filters = currentFilters();

    let params = new URLSearchParams({ page, ...filters });

    let tbody = document.querySelector('#reportTableBody');

    tbody.innerHTML = `
        <tr>
            <td colspan="10" class="text-center py-5">
                <div class="spinner-border text-primary" role="status" style="width: 2rem; height: 2rem;"></div>
                <div class="mt-2 fw-semibold">Loading reports, please wait...</div>
                <small class="text-muted">This can take a few seconds.</small>
            </td>
        </tr>
    `;

    document.querySelector('#pagination-info').innerText = 'Loading...';

    setLoadingState(true);

    let result;

    try {

        const response = await fetch(`/test-report-dashboard/list?${params.toString()}`, {
            headers: { 'Accept': 'application/json' }
        });

        result = await response.json();

    } catch (err) {

        if (thisLoad !== loadSequence) return;

        setLoadingState(false);

        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="text-center text-danger py-4">
                    Could not load the reports. Please check your connection and
                    <a href="#" id="retryLoadReports">try again</a>.
                </td>
            </tr>
        `;

        document.querySelector('#pagination-info').innerText = '';

        document.getElementById('retryLoadReports').addEventListener('click', function (e) {
            e.preventDefault();
            loadReports(page);
        });

        return;
    }

    if (thisLoad !== loadSequence) return;

    setLoadingState(false);

    tbody.innerHTML = '';

    if (!result.status) return;

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText = `Page ${result.pagination.current_page}`;
    document.querySelector('#pagination-info').innerText = `Total Records : ${result.pagination.total}`;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No diagnostic test report invoices found.</td></tr>';
        return;
    }

    result.data.forEach(row => {

        let canPrintOrSend = row.confirmed;

        // The print window always opens -- it shows every item's status and why
        // an item can't be printed yet, and only lets confirmed ones be ticked.

        // WhatsApp needs the whole report confirmed AND the invoice fully paid
        // (the server enforces the same rule; once the due is collected the
        // held report is also sent automatically).
        let canSendWhatsapp = canPrintOrSend && row.due_amount <= 0;
        let whatsappTitle = !canPrintOrSend
            ? 'Confirm the report before sending'
            : (row.due_amount > 0 ? 'Payment is due - collect it before sending on WhatsApp' : 'Send Report via WhatsApp');

        let actions = `
            <button class="btn btn-sm btn-soft-info print-report-btn me-1"
                    data-id="${row.id}"
                    data-invoice-no="${escapeHtml(row.invoice_no)}"
                    data-print-route="${row.print_route ?? ''}"
                    title="Report status / Print reports"
                    data-confirmed="${row.confirmed ? 1 : 0}">
                <i class="ri-printer-line"></i>
            </button>

            <button class="btn btn-sm btn-soft-success whatsapp-report-btn me-1"
                    data-id="${row.id}"
                    data-invoice-no="${escapeHtml(row.invoice_no)}"
                    data-print-route="${row.print_route ?? ''}"
                    title="${whatsappTitle}"
                    ${canSendWhatsapp ? '' : 'disabled'}>
                <i class="ri-whatsapp-line"></i>
            </button>
        `;

        if (row.xray_report_url) {
            actions += `
            <a href="${row.xray_report_url}" target="_blank"
               class="btn btn-sm btn-soft-secondary me-1"
               title="View uploaded X-Ray report">
                <i class="ri-file-pdf-line"></i>
            </a>
            `;
        }

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
                    title="Patient name correction is disabled here"
                    disabled>
                <i class="ri-edit-line"></i>
            </button>
            `;
        }

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
document.getElementById('resultStatusFilter').addEventListener('change', () => loadReports(1));
document.getElementById('rangeFilter').addEventListener('change', () => loadReports(1));
document.getElementById('deliveryFilter').addEventListener('change', () => loadReports(1));

let searchDebounce = null;
document.getElementById('searchInput').addEventListener('input', function () {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => loadReports(1), 400);
});

document.getElementById('resetFiltersBtn').addEventListener('click', function () {

    document.querySelector('#searchInput').value = '';
    document.querySelector('#categoryFilter').value = '';
    document.querySelector('#paymentStatusFilter').value = '';
    document.querySelector('#resultStatusFilter').value = '';
    document.querySelector('#rangeFilter').value = '3';
    document.querySelector('#deliveryFilter').value = 'not_delivered';
    document.querySelector('#perPage').value = '15';

    loadReports(1);
});

document.getElementById('reportTableBody').addEventListener('click', async function (e) {

    let printBtn = e.target.closest('.print-report-btn');
    if (printBtn && !printBtn.disabled) {

        // An invoice can have several independent reports (Pathology, USG,
        // Cardiology, Non-Pathology, an uploaded X-Ray PDF), each confirmed
        // on its own -- so show every item's status and let the user pick
        // which confirmed ones to print. Only an invoice with no qualifying
        // lines at all (nothing to list) falls back to the legacy
        // single-PDF route.
        if (printBtn.dataset.printRoute === 'legacy' && printBtn.dataset.confirmed === '1') {
            window.open(`/test-result-entry/print/${printBtn.dataset.id}`, '_blank');
            return;
        }

        openPrintItemsModal(printBtn.dataset.id);
        return;
    }

    let whatsappBtn = e.target.closest('.whatsapp-report-btn');
    if (whatsappBtn && !whatsappBtn.disabled) {

        let whatsappUrl = printRouteUrl(whatsappBtn.dataset.printRoute, whatsappBtn.dataset.invoiceNo);

        if (whatsappUrl) {
            window.open(whatsappUrl, '_blank');
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
});

document.addEventListener('DOMContentLoaded', function () {
    loadReports(1);
});

/*
|--------------------------------------------------------------------------
| PRINT MODAL -- per-item report status; print only the confirmed items the
| user ticks. The server re-checks that every selected item is confirmed.
|--------------------------------------------------------------------------
*/

let printItemsInvoiceId = null;

function itemStatusBadge(item) {

    if (item.confirmed) {
        return item.file_missing
            ? '<span class="badge bg-danger">File missing</span>'
            : '<span class="badge bg-success">Confirmed</span>';
    }

    return item.prepared
        ? '<span class="badge bg-primary">Confirmation Pending</span>'
        : '<span class="badge bg-warning text-dark">Not Prepared</span>';
}

function refreshPrintSelection() {

    let boxes = [...document.querySelectorAll('#printItemsBody .print-item-check')];
    let enabled = boxes.filter(b => !b.disabled);
    let checked = boxes.filter(b => b.checked);

    document.getElementById('printItemsPrintBtn').disabled = checked.length === 0;

    let all = document.getElementById('printItemsSelectAll');
    all.disabled = enabled.length === 0;
    all.checked = enabled.length > 0 && checked.length === enabled.length;
    all.indeterminate = checked.length > 0 && checked.length < enabled.length;
}

async function openPrintItemsModal(invoiceId) {

    printItemsInvoiceId = invoiceId;

    let body = document.getElementById('printItemsBody');
    body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>';
    document.getElementById('printItemsInvoiceInfo').innerText = '';
    let summaryBox = document.getElementById('printItemsSummary');
    summaryBox.classList.add('d-none');

    let modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('printItemsModal'));
    modal.show();

    try {

        const response = await fetch(`/test-report-dashboard/items/${invoiceId}`, {
            headers: { 'Accept': 'application/json' }
        });

        const result = await response.json();

        if (!result.status) throw new Error('failed');

        document.getElementById('printItemsInvoiceInfo').innerText =
            `${result.invoice.invoice_no} - ${result.invoice.patient_name ?? ''}`;

        // Overall status, worded exactly like the dashboard column.
        let summary = result.summary;
        summaryBox.innerHTML =
            `<strong>${escapeHtml(RESULT_STATUS_LABELS[summary.result_status] ?? summary.result_status)}</strong>` +
            (summary.total > 0
                ? ` - ${summary.prepared} of ${summary.total} prepared, ${summary.confirmed} confirmed. Only confirmed reports can be printed.`
                : ' - no report is required for this invoice.');
        summaryBox.classList.remove('d-none');

        if (!result.items.length) {
            body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No report items on this invoice (nothing here needs a report).</td></tr>';
            refreshPrintSelection();
            return;
        }

        body.innerHTML = result.items.map(item => `
            <tr>
                <td>
                    <input type="checkbox" class="form-check-input print-item-check"
                           value="${item.invoice_detail_id}"
                           ${item.can_print ? '' : 'disabled'}
                           title="${item.can_print ? 'Include in print' : 'Only confirmed reports can be printed'}">
                </td>
                <td>${escapeHtml(item.item_description)}</td>
                <td>${itemStatusBadge(item)}</td>
                <td>${escapeHtml(item.prepared_by ?? '-')}</td>
                <td>${escapeHtml(item.prepared_at ?? '-')}</td>
                <td>${escapeHtml(item.confirmed_by ?? '-')}</td>
                <td>${escapeHtml(item.confirmed_at ?? '-')}</td>
                <td class="${item.can_print ? 'text-muted' : 'text-danger'}"><small>${escapeHtml(item.reason ?? 'Ready to print')}</small></td>
            </tr>
        `).join('');

        refreshPrintSelection();

    } catch (err) {

        body.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Could not load the report details. Please try again.</td></tr>';
    }
}

document.getElementById('printItemsSelectAll').addEventListener('change', function () {

    document.querySelectorAll('#printItemsBody .print-item-check:not(:disabled)')
        .forEach(box => { box.checked = this.checked; });

    refreshPrintSelection();
});

document.getElementById('printItemsBody').addEventListener('change', function (e) {
    if (e.target.classList.contains('print-item-check')) refreshPrintSelection();
});

document.getElementById('printItemsPrintBtn').addEventListener('click', function () {

    let ids = [...document.querySelectorAll('#printItemsBody .print-item-check:checked')].map(b => b.value);

    if (!ids.length || !printItemsInvoiceId) return;

    // A real form POST into a new tab, so the PDF opens like any other
    // print (a fetch+blob would need the browser's popup handling too).
    let form = document.createElement('form');
    form.method = 'POST';
    form.action = '/test-report-dashboard/print-selected';
    form.target = '_blank';

    let fields = { _token: csrfToken(), invoice_id: printItemsInvoiceId };

    Object.entries(fields).forEach(([name, value]) => {
        let input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    });

    ids.forEach(id => {
        let input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'detail_ids[]';
        input.value = id;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
    form.remove();
});
