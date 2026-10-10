let currentPage = 1;
let lastPage = 1;
let pendingInvoiceId = null;

const MAX_BYTES = 20 * 1024 * 1024;

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
        upload_status: document.querySelector('#uploadStatusFilter').value,
        range: document.querySelector('#rangeFilter').value,
    };
}

function paymentBadge(row) {
    let cls = { 'Paid': 'bg-success', 'Partial': 'bg-warning text-dark', 'Due': 'bg-danger' }[row.payment_status] ?? 'bg-secondary';
    let due = row.due_amount > 0 ? `<br><small class="text-muted">Due ${row.due_amount.toFixed(2)}</small>` : '';
    return `<span class="badge ${cls}">${row.payment_status}</span>${due}`;
}

function reportCell(row) {

    if (row.file_missing) {
        return '<span class="badge bg-danger">File missing</span><br><small class="text-muted">Upload again</small>';
    }

    if (!row.uploaded) {
        return '<span class="badge bg-secondary">Not uploaded</span>';
    }

    return `<span class="badge bg-success">Uploaded</span>
            <br><small class="text-muted">${escapeHtml(row.uploaded_at)}${row.uploaded_by ? ' by ' + escapeHtml(row.uploaded_by) : ''}</small>`;
}

async function loadRows(page = 1) {

    currentPage = page;

    let params = new URLSearchParams({ page, ...currentFilters() });

    const response = await fetch(`/xray-report-upload/list?${params.toString()}`, {
        headers: { 'Accept': 'application/json' }
    });

    const result = await response.json();

    let tbody = document.querySelector('#xrayTableBody');
    tbody.innerHTML = '';

    if (!result.status) return;

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText = `Page ${result.pagination.current_page}`;
    document.querySelector('#pagination-info').innerText = `Total Records : ${result.pagination.total}`;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No invoices with an X-Ray item found.</td></tr>';
        return;
    }

    result.data.forEach(row => {

        let hasFile = row.uploaded && !row.file_missing;

        tbody.innerHTML += `
            <tr>
                <td class="text-nowrap">
                    <button class="btn btn-sm ${hasFile ? 'btn-soft-warning' : 'btn-soft-primary'} upload-xray-btn"
                            data-id="${row.id}"
                            data-invoice-no="${escapeHtml(row.invoice_no)}"
                            title="${hasFile ? 'Replace the uploaded X-Ray report' : 'Upload X-Ray report PDF'}">
                        <i class="${hasFile ? 'ri-refresh-line' : 'ri-upload-2-line'}"></i>
                        ${hasFile ? 'Replace' : 'Upload'}
                    </button>
                </td>
                <td>${escapeHtml(row.invoice_no)}</td>
                <td>${escapeHtml(row.patient_name ?? '-')}<br><small class="text-muted">${escapeHtml(row.patient_mobile_no ?? '')}</small></td>
                <td>${escapeHtml(row.invoice_date ?? '-')}</td>
                <td>${escapeHtml(row.xray_tests)}</td>
                <td>${paymentBadge(row)}</td>
                <td>${reportCell(row)}</td>
                <td class="text-nowrap">${hasFile
                    ? `<a href="${row.view_url}" target="_blank" class="btn btn-sm btn-soft-info me-1" title="View uploaded X-Ray report"><i class="ri-file-pdf-line"></i></a><button class="btn btn-sm btn-soft-success whatsapp-xray-btn" data-id="${row.id}" data-invoice-no="${escapeHtml(row.invoice_no)}" title="${row.due_amount > 0 ? 'Payment is due - collect it before sending on WhatsApp' : 'Send report to the patient via WhatsApp'}" ${row.due_amount > 0 ? 'disabled' : ''}><i class="ri-whatsapp-line"></i></button>`
                    : '-'}</td>
            </tr>
        `;
    });
}

document.getElementById('xrayTableBody').addEventListener('click', function (e) {

    let btn = e.target.closest('.upload-xray-btn');
    if (!btn) return;

    pendingInvoiceId = btn.dataset.id;

    let input = document.getElementById('xrayFileInput');
    input.value = '';
    input.dataset.invoiceNo = btn.dataset.invoiceNo;
    input.click();
});

document.getElementById('xrayTableBody').addEventListener('click', async function (e) {

    let btn = e.target.closest('.whatsapp-xray-btn');
    if (!btn) return;

    let confirmResult = await Swal.fire({
        icon: 'question',
        title: 'Send via WhatsApp?',
        text: `Send the X-Ray report for ${btn.dataset.invoiceNo} to the patient.`,
        showCancelButton: true,
        confirmButtonText: 'Send'
    });

    if (!confirmResult.isConfirmed) return;

    btn.disabled = true;

    try {

        const response = await fetch(`/xray-report-upload/send-whatsapp/${btn.dataset.id}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' }
        });

        const result = await response.json();

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Sent' : 'Not sent',
            text: result.message
        });

    } catch (err) {

        Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed. Please try again.' });
    }

    btn.disabled = false;
});

document.getElementById('xrayFileInput').addEventListener('change', async function () {

    let file = this.files[0];
    let invoiceNo = this.dataset.invoiceNo;

    if (!file || !pendingInvoiceId) return;

    if (!/\.pdf$/i.test(file.name)) {
        Swal.fire({ icon: 'warning', title: 'Only PDF', text: 'Please choose a PDF file.' });
        return;
    }

    if (file.size > MAX_BYTES) {
        Swal.fire({ icon: 'warning', title: 'File too large', text: 'The PDF must be 20 MB or smaller.' });
        return;
    }

    let confirmResult = await Swal.fire({
        icon: 'question',
        title: 'Upload X-Ray report?',
        html: `<b>${escapeHtml(file.name)}</b><br>for invoice <b>${escapeHtml(invoiceNo)}</b><br><small>An existing report for this invoice will be replaced.</small>`,
        showCancelButton: true,
        confirmButtonText: 'Upload'
    });

    if (!confirmResult.isConfirmed) return;

    let formData = new FormData();
    formData.append('invoice_id', pendingInvoiceId);
    formData.append('file', file);

    Swal.fire({ title: 'Uploading...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {

        const response = await fetch('/xray-report-upload/upload', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
            body: formData
        });

        const result = await response.json();

        if (response.ok && result.status) {

            // A clean send closes by itself; anything else (held for a due
            // payment, failed, switched off) stays up so staff actually read it.
            let sent = result.data && result.data.whatsapp_status === 'sent';

            await Swal.fire(sent
                ? { icon: 'success', title: 'Done', text: result.message, timer: 2000, showConfirmButton: false }
                : { icon: 'info', title: 'Uploaded', text: result.message });

            loadRows(currentPage);

        } else {

            let message = result.message
                || (result.errors ? Object.values(result.errors).flat().join(' ') : 'Upload failed.');

            Swal.fire({ icon: 'error', title: 'Error', text: message });
        }

    } catch (err) {

        Swal.fire({ icon: 'error', title: 'Error', text: 'Upload failed. Please try again.' });
    }
});

document.getElementById('prevPage').addEventListener('click', () => { if (currentPage > 1) loadRows(currentPage - 1); });
document.getElementById('nextPage').addEventListener('click', () => { if (currentPage < lastPage) loadRows(currentPage + 1); });

document.getElementById('perPage').addEventListener('change', () => loadRows(1));
document.getElementById('uploadStatusFilter').addEventListener('change', () => loadRows(1));
document.getElementById('rangeFilter').addEventListener('change', () => loadRows(1));

let searchDebounce = null;
document.getElementById('searchInput').addEventListener('input', function () {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => loadRows(1), 400);
});

document.getElementById('resetFiltersBtn').addEventListener('click', function () {
    document.querySelector('#searchInput').value = '';
    document.querySelector('#uploadStatusFilter').value = 'all';
    document.querySelector('#rangeFilter').value = '7';
    document.querySelector('#perPage').value = '15';
    loadRows(1);
});

loadRows(1);
