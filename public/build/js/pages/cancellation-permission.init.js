let currentInvoice = null;
let currentPermission = null;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

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

function fmtDateTime(value) {
    if (!value) return '-';
    const d = new Date(value.replace(' ', 'T'));
    if (isNaN(d.getTime())) return value;
    return d.toLocaleString('en-IN');
}

/* ==========================================================
   PENDING REQUESTS LIST
========================================================== */

async function loadPendingRequests() {

    const response = await fetch('/cancellation-permission/pending');

    if (response.status === 403) {
        Swal.fire({ icon: 'error', title: 'Access Denied', text: 'Only a Supervisor or Admin can use this dashboard.' });
        return;
    }

    const result = await response.json();

    let tbody = document.getElementById('pendingListBody');

    if (!result.status || !result.data.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No pending cancellation requests.</td></tr>';
        return;
    }

    tbody.innerHTML = result.data.map(row => `
        <tr>
            <td>${escapeHtml(row.invoice_no)}</td>
            <td>${escapeHtml(row.invoice_type_label ?? '-')}</td>
            <td>${escapeHtml(row.invoice_date_fmt ?? '-')}</td>
            <td>${escapeHtml(row.patient_name ?? '-')}</td>
            <td class="text-end">${fmtMoney(row.paid_amount)}</td>
            <td>${escapeHtml(row.requested_by_name ?? '-')}</td>
            <td>${escapeHtml(row.reason ?? '-')}</td>
            <td>${fmtDateTime(row.requested_at)}</td>
            <td>
                <button type="button" class="btn btn-sm btn-success grant-pending-btn"
                        data-permission-id="${row.permission_id}"
                        data-invoice-no="${escapeHtml(row.invoice_no)}"
                        data-requested-by="${escapeHtml(row.requested_by_name ?? '-')}"
                        title="Grant">
                    <i class="ri-shield-check-line"></i>
                    Grant
                </button>
            </td>
        </tr>
    `).join('');
}

document.getElementById('btnRefreshPending').addEventListener('click', loadPendingRequests);

document.getElementById('pendingListBody').addEventListener('click', async function (e) {

    let btn = e.target.closest('.grant-pending-btn');
    if (!btn) return;

    let confirmResult = await Swal.fire({
        title: 'Grant Cancellation Permission?',
        html: `Invoice <strong>${btn.dataset.invoiceNo}</strong> will be cancellable only by
               <strong>${btn.dataset.requestedBy}</strong>.`,
        icon: 'question',
        input: 'textarea',
        inputPlaceholder: 'Optional remarks',
        showCancelButton: true,
        confirmButtonColor: '#0ab39c',
        cancelButtonColor: '#f06548',
        confirmButtonText: 'Yes, Grant'
    });

    if (!confirmResult.isConfirmed) return;

    const response = await fetch('/cancellation-permission/grant', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            permission_id: btn.dataset.permissionId,
            remarks: confirmResult.value || null
        })
    });

    const result = await response.json();

    Swal.fire({
        icon: result.status ? 'success' : 'error',
        title: result.status ? 'Granted' : 'Error',
        text: result.message
    });

    loadPendingRequests();
});

loadPendingRequests();

/* ==========================================================
   LOOK UP A SPECIFIC INVOICE
========================================================== */

document.getElementById('searchForm').addEventListener('submit', async function (e) {

    e.preventDefault();

    let invoiceNo = document.getElementById('invoice_no-field').value.trim();

    if (!invoiceNo) return;

    document.getElementById('notFoundWrap').style.display = 'none';
    document.getElementById('invoiceResultCard').style.display = 'none';

    const response = await fetch(`/cancellation-permission/search?invoice_no=${encodeURIComponent(invoiceNo)}`);

    if (response.status === 403) {
        Swal.fire({ icon: 'error', title: 'Access Denied', text: 'Only a Supervisor or Admin can use this dashboard.' });
        return;
    }

    const result = await response.json();

    if (!result.status) {
        document.getElementById('notFoundWrap').style.display = 'block';
        currentInvoice = null;
        return;
    }

    currentInvoice = result.invoice;
    currentPermission = result.permission;

    renderInvoice(result.invoice, result.permission);
});

function renderInvoice(invoice, permission) {

    document.getElementById('invoiceResultCard').style.display = 'block';

    document.getElementById('detail-invoice_no').innerText = invoice.invoice_no;
    document.getElementById('detail-invoice_type').innerText = invoice.invoice_type_label;
    document.getElementById('detail-invoice_date').innerText = invoice.invoice_date_fmt ?? '-';
    document.getElementById('detail-patient_name').innerText = invoice.patient_name ?? '-';
    document.getElementById('detail-total_amount').innerText = fmtMoney(invoice.total_amount);
    document.getElementById('detail-paid_amount').innerText = fmtMoney(invoice.paid_amount);

    let statusBadge = document.getElementById('statusBadge');

    if (invoice.already_cancelled) {
        statusBadge.innerHTML = '<span class="badge bg-danger">Cancelled</span>';
    } else {
        statusBadge.innerHTML = `<span class="badge bg-success">${escapeHtml(invoice.status)}</span>`;
    }

    document.getElementById('todayWrap').style.display = 'none';
    document.getElementById('alreadyCancelledWrap').style.display = 'none';
    document.getElementById('noRequestWrap').style.display = 'none';
    document.getElementById('pendingRequestWrap').style.display = 'none';
    document.getElementById('permissionGrantedWrap').style.display = 'none';
    document.getElementById('grantActionWrap').style.display = 'none';

    if (invoice.already_cancelled) {

        document.getElementById('alreadyCancelledWrap').style.display = 'block';
        return;
    }

    if (invoice.is_today) {

        document.getElementById('todayWrap').style.display = 'block';
        return;
    }

    if (!permission) {

        document.getElementById('noRequestWrap').style.display = 'block';
        return;
    }

    if (permission.status === 'GRANTED') {

        document.getElementById('permissionGrantedWrap').style.display = 'block';
        document.getElementById('granted-requested-by').innerText = permission.requested_by_name ?? '-';
        document.getElementById('granted-reason').innerText = permission.reason ?? '-';
        document.getElementById('granted-by').innerText = permission.granted_by_name ?? '-';
        document.getElementById('granted-at').innerText = fmtDateTime(permission.granted_at);
        return;
    }

    // PENDING
    document.getElementById('pendingRequestWrap').style.display = 'block';
    document.getElementById('pending-requested-by').innerText = permission.requested_by_name ?? '-';
    document.getElementById('pending-requested-at').innerText = fmtDateTime(permission.requested_at);
    document.getElementById('pending-reason').innerText = permission.reason ?? '-';

    document.getElementById('grant_remarks-field').value = '';
    document.getElementById('grantActionWrap').style.display = 'block';
}

document.getElementById('btnGrantPermission').addEventListener('click', async function () {

    if (!currentInvoice || !currentPermission) return;

    const response = await fetch('/cancellation-permission/grant', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            permission_id: currentPermission.id,
            remarks: document.getElementById('grant_remarks-field').value.trim() || null
        })
    });

    const result = await response.json();

    if (!response.ok || !result.status) {

        let errorText = result.errors
            ? Object.values(result.errors).flat().join(', ')
            : (result.message ?? 'Unable to grant cancellation permission.');

        Swal.fire({ icon: 'error', title: 'Error', text: errorText });
        return;
    }

    Swal.fire({ icon: 'success', title: 'Permission Granted', text: result.message });

    loadPendingRequests();

    // refresh to show the granted state
    const refreshed = await fetch(`/cancellation-permission/search?invoice_no=${encodeURIComponent(currentInvoice.invoice_no)}`);
    const refreshedResult = await refreshed.json();

    if (refreshedResult.status) {
        currentInvoice = refreshedResult.invoice;
        currentPermission = refreshedResult.permission;
        renderInvoice(refreshedResult.invoice, refreshedResult.permission);
    }
});
