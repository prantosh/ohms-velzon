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
   SEARCH
========================================================== */

document.getElementById('searchForm').addEventListener('submit', async function (e) {

    e.preventDefault();

    let invoiceNo = document.getElementById('invoice_no-field').value.trim();

    if (!invoiceNo) return;

    document.getElementById('notFoundWrap').style.display = 'none';
    document.getElementById('invoiceResultCard').style.display = 'none';

    const response = await fetch(`/invoice-cancellation/search?invoice_no=${encodeURIComponent(invoiceNo)}`);

    const result = await response.json();

    if (!result.status) {
        document.getElementById('notFoundWrap').style.display = 'block';
        currentInvoice = null;
        currentPermission = null;
        return;
    }

    currentInvoice = result.invoice;
    currentPermission = result.permission;

    renderInvoice(result.invoice, result.details, result.permission);
});

function renderInvoice(invoice, details, permission) {

    document.getElementById('invoiceResultCard').style.display = 'block';

    document.getElementById('detail-invoice_no').innerText = invoice.invoice_no;
    document.getElementById('detail-invoice_type').innerText = invoice.invoice_type_label;
    document.getElementById('detail-invoice_date').innerText = invoice.invoice_date_fmt ?? '-';
    document.getElementById('detail-patient_name').innerText = invoice.patient_name ?? '-';
    document.getElementById('detail-patient_mobile_no').innerText = invoice.patient_mobile_no ?? '-';
    document.getElementById('detail-payment_mode').innerText = invoice.payment_mode ?? '-';
    document.getElementById('detail-total_amount').innerText = fmtMoney(invoice.total_amount);
    document.getElementById('detail-paid_amount').innerText = fmtMoney(invoice.paid_amount);
    document.getElementById('detail-due_amount').innerText = fmtMoney(invoice.due_amount);
    document.getElementById('detail-received_by_name').innerText = invoice.received_by_name ?? '-';
    document.getElementById('detail-remarks').innerText = invoice.remarks ?? '-';

    let statusBadge = document.getElementById('statusBadge');

    if (invoice.already_cancelled) {
        statusBadge.innerHTML = '<span class="badge bg-danger">Cancelled</span>';
    } else {
        statusBadge.innerHTML = `<span class="badge bg-success">${escapeHtml(invoice.status)}</span>`;
    }

    let tbody = document.getElementById('detailsTableBody');

    tbody.innerHTML = '';

    if (!details.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No line items found.</td></tr>';
    } else {
        details.forEach((row, index) => {
            tbody.innerHTML += `
            <tr>
                <td>${index + 1}</td>
                <td>${escapeHtml(row.item_description ?? row.item_code ?? '-')}</td>
                <td>${row.quantity ?? '-'}</td>
                <td class="text-end">${fmtMoney(row.rate)}</td>
                <td class="text-end">${fmtMoney(row.amount)}</td>
            </tr>
            `;
        });
    }

    document.getElementById('alreadyCancelledWrap').style.display = 'none';
    document.getElementById('permissionGrantedInfoWrap').style.display = 'none';
    document.getElementById('permissionRequiredWrap').style.display = 'none';
    document.getElementById('cancelActionWrap').style.display = 'none';

    if (invoice.already_cancelled) {

        document.getElementById('alreadyCancelledWrap').style.display = 'block';

        document.getElementById('cancelled-by').innerText = invoice.cancelled_by_name ?? '-';
        document.getElementById('cancelled-at').innerText = fmtDateTime(invoice.cancelled_at);
        document.getElementById('cancelled-remarks').innerText = invoice.cancellation_remarks ?? '-';

        if (invoice.cancellation_approved_by_name) {
            document.getElementById('cancelled-approval-wrap').style.display = 'block';
            document.getElementById('cancelled-approved-by').innerText = invoice.cancellation_approved_by_name;
        } else {
            document.getElementById('cancelled-approval-wrap').style.display = 'none';
        }

        return;
    }

    if (!invoice.is_today && permission) {

        document.getElementById('permissionGrantedInfoWrap').style.display = 'block';
        document.getElementById('permission-granted-by').innerText = permission.granted_by_name ?? '-';
        document.getElementById('permission-granted-at').innerText = fmtDateTime(permission.created_at);
        document.getElementById('permission-remarks').innerText = permission.remarks ?? '-';
    }

    if (invoice.can_cancel) {
        document.getElementById('cancelActionWrap').style.display = 'block';
    } else {
        document.getElementById('permissionRequiredWrap').style.display = 'block';
    }
}

/* ==========================================================
   OFFCANVAS
========================================================== */

document.getElementById('btnOpenCancel').addEventListener('click', function () {

    if (!currentInvoice) return;

    document.getElementById('oc-invoice_no').innerText = currentInvoice.invoice_no;
    document.getElementById('oc-invoice_date').innerText = currentInvoice.invoice_date_fmt ?? '-';
    document.getElementById('oc-patient_name').innerText = currentInvoice.patient_name ?? '-';
    document.getElementById('oc-paid_amount').innerText = '₹ ' + fmtMoney(currentInvoice.paid_amount);

    document.getElementById('cancellation_remarks-field').value = '';

    if (!currentInvoice.is_today && currentPermission) {

        document.getElementById('oc-approval-note').style.display = 'block';
        document.getElementById('oc-permission-granted-by').innerText = currentPermission.granted_by_name ?? '-';

    } else {

        document.getElementById('oc-approval-note').style.display = 'none';
    }

    new bootstrap.Offcanvas(document.getElementById('cancelOffcanvas')).show();
});

document.getElementById('btnConfirmCancel').addEventListener('click', async function () {

    if (!currentInvoice) return;

    let remarks = document.getElementById('cancellation_remarks-field').value.trim();

    if (!remarks) {
        Swal.fire({ icon: 'warning', title: 'Remarks required', text: 'Please enter a reason for cancellation.' });
        return;
    }

    let payload = {
        invoice_id: currentInvoice.id,
        cancellation_remarks: remarks
    };

    const response = await fetch('/invoice-cancellation/cancel', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!response.ok || !result.status) {

        let errorText = result.errors
            ? Object.values(result.errors).flat().join(', ')
            : (result.message ?? 'Unable to cancel invoice.');

        Swal.fire({ icon: 'error', title: 'Error', text: errorText });
        return;
    }

    bootstrap.Offcanvas.getInstance(document.getElementById('cancelOffcanvas'))?.hide();

    Swal.fire({ icon: 'success', title: 'Cancelled', text: result.message });

    // reload the invoice details to reflect cancelled state
    const refreshed = await fetch(`/invoice-cancellation/search?invoice_no=${encodeURIComponent(currentInvoice.invoice_no)}`);
    const refreshedResult = await refreshed.json();

    if (refreshedResult.status) {
        currentInvoice = refreshedResult.invoice;
        currentPermission = refreshedResult.permission;
        renderInvoice(refreshedResult.invoice, refreshedResult.details, refreshedResult.permission);
    }
});
