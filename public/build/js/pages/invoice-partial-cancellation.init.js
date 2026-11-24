let currentInvoice = null;
let currentPermission = null;
let currentPartial = null;
let currentSuperseded = null;
let currentParentNo = null;

function captureExtras(result) {
    currentPartial = result.partial ?? null;
    currentSuperseded = result.superseded_by ?? null;
    currentParentNo = result.parent_invoice_no ?? null;
}

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
    captureExtras(result);

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

    document.getElementById('alreadyCancelledWrap').style.display = 'none';
    document.getElementById('permissionGrantedInfoWrap').style.display = 'none';
    document.getElementById('grantedToOtherWrap').style.display = 'none';
    document.getElementById('pendingOwnWrap').style.display = 'none';
    document.getElementById('pendingOtherWrap').style.display = 'none';
    document.getElementById('requestPermissionWrap').style.display = 'none';
    document.getElementById('partialWrap').style.display = 'none';
    document.getElementById('supersededWrap').style.display = 'none';
    document.getElementById('modifiedFromWrap').style.display = 'none';

    if (currentSuperseded) {
        document.getElementById('supersededWrap').style.display = 'block';
        document.getElementById('superseded-by').innerText = currentSuperseded;
    }

    if (currentParentNo) {
        document.getElementById('modifiedFromWrap').style.display = 'block';
        document.getElementById('modified-from').innerText = currentParentNo;
    }

    // Partial cancellation is offered whenever whole-invoice cancellation is.
    const canCancelNow = !invoice.already_cancelled && (
        invoice.is_today || (permission && permission.status === 'GRANTED' && permission.is_granted_to_me)
    );

    const notEligible = document.getElementById('notEligibleWrap');
    notEligible.style.display = 'none';

    if (canCancelNow && currentPartial && currentPartial.eligible) {
        renderPartial(invoice, currentPartial.lines);
    } else if (canCancelNow && currentPartial && currentPartial.reason) {
        notEligible.innerText = currentPartial.reason;
        notEligible.style.display = 'block';
    }

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

    if (invoice.is_today) {

        return;
    }

    if (!permission) {

        document.getElementById('requestPermissionWrap').style.display = 'block';
        return;
    }

    if (permission.status === 'GRANTED') {

        if (permission.is_granted_to_me) {

            document.getElementById('permissionGrantedInfoWrap').style.display = 'block';
            document.getElementById('permission-granted-by').innerText = permission.granted_by_name ?? '-';
            document.getElementById('permission-granted-at').innerText = fmtDateTime(permission.granted_at);


        } else {

            document.getElementById('grantedToOtherWrap').style.display = 'block';
            document.getElementById('granted-to-other-name').innerText = permission.requested_by_name ?? '-';
        }

        return;
    }

    // PENDING
    if (permission.requested_by_me) {

        document.getElementById('pendingOwnWrap').style.display = 'block';
        document.getElementById('pending-own-at').innerText = fmtDateTime(permission.requested_at);

    } else {

        document.getElementById('pendingOtherWrap').style.display = 'block';
        document.getElementById('pending-other-name').innerText = permission.requested_by_name ?? '-';
    }
}

/* ==========================================================
   PARTIAL CANCELLATION
========================================================== */

function renderPartial(invoice, lines) {

    document.getElementById('partialWrap').style.display = 'block';
    document.getElementById('partial_remarks-field').value = '';

    const body = document.getElementById('partialLinesBody');
    body.innerHTML = '';

    lines.forEach(l => {
        body.innerHTML += `
        <tr>
            <td class="text-center">
                <input type="checkbox" class="form-check-input partial-chk" value="${l.id}" data-amount="${l.amount}"
                       ${l.can_cancel ? '' : 'disabled'}>
            </td>
            <td>${escapeHtml(l.item_description)}</td>
            <td class="text-end">${fmtMoney(l.rate)}</td>
            <td class="text-end">${fmtMoney(l.discount)}</td>
            <td class="text-end">${fmtMoney(l.amount)}</td>
            <td>${l.can_cancel
                ? '<span class="badge bg-success">Allowed to Cancel</span>'
                : `<span class="badge bg-danger">Cannot Cancel (${escapeHtml(l.block_reason)})</span>`}
            </td>
        </tr>`;
    });

    body.querySelectorAll('.partial-chk').forEach(c => c.addEventListener('change', updatePartialPreview));

    updatePartialPreview();
}

let currentPartialDue = 0;

function updateCollectBalance(due) {
    if (due !== undefined) currentPartialDue = due;
    const c = Math.min(Math.max(0, Number(document.getElementById('collect_amount-field').value) || 0), currentPartialDue);
    document.getElementById('collect-balance').value = fmtMoney(currentPartialDue - c);
}

document.getElementById('collect_amount-field').addEventListener('input', () => updateCollectBalance());

function updatePartialPreview() {

    const checks = [...document.querySelectorAll('.partial-chk')];
    const picked = checks.filter(c => c.checked);

    const cancelled = picked.reduce((s, c) => s + Number(c.dataset.amount), 0);
    const total = Number(currentInvoice.total_amount);
    const paid = Number(currentInvoice.paid_amount);
    const newTotal = Math.max(0, total - cancelled);
    const refund = Math.max(0, paid - newTotal);
    const due = Math.max(0, newTotal - paid);

    document.getElementById('pp-cancelled').innerText = fmtMoney(cancelled);
    document.getElementById('pp-newtotal').innerText = fmtMoney(newTotal);
    document.getElementById('pp-paid').innerText = fmtMoney(paid);

    document.getElementById('pp-result-label').innerText = refund > 0 ? 'Refund to patient (₹)' : 'Due after change (₹)';
    document.getElementById('pp-result').innerText = fmtMoney(refund > 0 ? refund : due);

    // collection option only when a balance will still be due
    const collectWrap = document.getElementById('collectWrap');
    const collectInput = document.getElementById('collect_amount-field');
    collectWrap.style.display = due > 0 ? 'flex' : 'none';
    document.getElementById('collect-max').innerText = fmtMoney(due);
    collectInput.max = due;
    if (due <= 0 || Number(collectInput.value) > due) collectInput.value = 0;
    updateCollectBalance(due);

    // at least one item must stay on the invoice
    const allPicked = picked.length === checks.filter(c => !c.disabled).length
        && picked.length === checks.length;

    const btn = document.getElementById('btnPartialCancel');
    btn.disabled = picked.length === 0 || picked.length >= checks.length;
    btn.title = allPicked ? 'To cancel every item use Cancel Entire Invoice' : '';
}

document.getElementById('btnPartialCancel').addEventListener('click', async function () {

    if (!currentInvoice) return;

    const ids = [...document.querySelectorAll('.partial-chk:checked')].map(c => Number(c.value));
    const remarks = document.getElementById('partial_remarks-field').value.trim();

    if (!ids.length) return;

    if (!remarks) {
        Swal.fire({ icon: 'warning', title: 'Remarks required', text: 'Please enter a reason.' });
        return;
    }

    const collectAmount = currentPartialDue > 0
        ? Math.max(0, Number(document.getElementById('collect_amount-field').value) || 0)
        : 0;

    if (collectAmount > currentPartialDue + 0.001) {
        Swal.fire({ icon: 'warning', title: 'Collection too high', text: 'Amount collected cannot exceed the due amount (₹ ' + fmtMoney(currentPartialDue) + ').' });
        return;
    }

    const confirm = await Swal.fire({
        icon: 'question',
        title: 'Cancel selected items?',
        html: `A Modified Invoice will replace ${escapeHtml(currentInvoice.invoice_no)}.<br>`
            + `<b>${document.getElementById('pp-result-label').innerText}: ₹ ${document.getElementById('pp-result').innerText}</b>`,
        showCancelButton: true,
        confirmButtonText: 'Yes, proceed'
    });

    if (!confirm.isConfirmed) return;

    this.disabled = true;

    const response = await fetch('/invoice-partial-cancellation/cancel', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            invoice_id: currentInvoice.id,
            detail_ids: ids,
            cancellation_remarks: remarks,
            collect_amount: collectAmount > 0 ? collectAmount : null,
            collect_mode: collectAmount > 0 ? document.getElementById('collect_mode-field').value : null
        })
    });

    const result = await response.json();

    if (!response.ok || !result.status) {

        this.disabled = false;

        const errorText = result.errors
            ? Object.values(result.errors).flat().join(', ')
            : (result.message ?? 'Unable to partially cancel invoice.');

        Swal.fire({ icon: 'error', title: 'Error', text: errorText });
        this.disabled = false;
        return;
    }

    await Swal.fire({ icon: 'success', title: 'Modified Invoice Issued', text: result.message, confirmButtonText: 'OK' });

    // Same as normal invoice creation: open the new invoice in a new window after OK.
    if (result.new_invoice_id) {
        window.open('/diagnostic-invoice/print/' + result.new_invoice_id, '_blank');
    }

    const refreshed = await fetch(`/invoice-cancellation/search?invoice_no=${encodeURIComponent(currentInvoice.invoice_no)}`);
    const refreshedResult = await refreshed.json();

    if (refreshedResult.status) {
        currentInvoice = refreshedResult.invoice;
        currentPermission = refreshedResult.permission;
        captureExtras(refreshedResult);
        renderInvoice(refreshedResult.invoice, refreshedResult.details, refreshedResult.permission);
    }
});

/* ==========================================================
   REQUEST PERMISSION
========================================================== */

document.getElementById('btnRequestPermission').addEventListener('click', async function () {

    if (!currentInvoice) return;

    let reason = document.getElementById('request_reason-field').value.trim();

    if (!reason) {
        Swal.fire({ icon: 'warning', title: 'Reason required', text: 'Please enter a reason for cancellation.' });
        return;
    }

    const response = await fetch('/invoice-cancellation/request-permission', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            invoice_id: currentInvoice.id,
            reason: reason
        })
    });

    const result = await response.json();

    if (!response.ok || !result.status) {

        let errorText = result.errors
            ? Object.values(result.errors).flat().join(', ')
            : (result.message ?? 'Unable to submit cancellation request.');

        Swal.fire({ icon: 'error', title: 'Error', text: errorText });
        return;
    }

    Swal.fire({ icon: 'success', title: 'Request Submitted', text: result.message });

    document.getElementById('request_reason-field').value = '';

    const refreshed = await fetch(`/invoice-cancellation/search?invoice_no=${encodeURIComponent(currentInvoice.invoice_no)}`);
    const refreshedResult = await refreshed.json();

    if (refreshedResult.status) {
        currentInvoice = refreshedResult.invoice;
        currentPermission = refreshedResult.permission;
        captureExtras(refreshedResult);
        renderInvoice(refreshedResult.invoice, refreshedResult.details, refreshedResult.permission);
    }
});

