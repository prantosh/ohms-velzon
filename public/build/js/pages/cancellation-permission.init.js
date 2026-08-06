let currentInvoice = null;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
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
        statusBadge.innerHTML = `<span class="badge bg-success">${invoice.status}</span>`;
    }

    document.getElementById('todayWrap').style.display = 'none';
    document.getElementById('alreadyCancelledWrap').style.display = 'none';
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

    if (permission) {

        document.getElementById('permissionGrantedWrap').style.display = 'block';
        document.getElementById('granted-by').innerText = permission.granted_by_name ?? '-';
        document.getElementById('granted-at').innerText = fmtDateTime(permission.created_at);
        document.getElementById('granted-remarks').innerText = permission.remarks ?? '-';
        return;
    }

    document.getElementById('grant_remarks-field').value = '';
    document.getElementById('grantActionWrap').style.display = 'block';
}

document.getElementById('btnGrantPermission').addEventListener('click', async function () {

    if (!currentInvoice) return;

    let remarks = document.getElementById('grant_remarks-field').value.trim();

    if (!remarks) {
        Swal.fire({ icon: 'warning', title: 'Remarks required', text: 'Please enter a reason for granting permission.' });
        return;
    }

    const response = await fetch('/cancellation-permission/grant', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            invoice_id: currentInvoice.id,
            remarks: remarks
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

    // refresh to show the granted state
    const refreshed = await fetch(`/cancellation-permission/search?invoice_no=${encodeURIComponent(currentInvoice.invoice_no)}`);
    const refreshedResult = await refreshed.json();

    if (refreshedResult.status) {
        currentInvoice = refreshedResult.invoice;
        renderInvoice(refreshedResult.invoice, refreshedResult.permission);
    }
});
