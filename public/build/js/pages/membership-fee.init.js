let currentMember = null;
let lastInvoiceId = null;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

document.getElementById('mobileInput').addEventListener('input', function () {

    this.value = this.value.replace(/\D/g, '').substring(0, 10);
});

async function searchMember() {

    let mobile = document.querySelector('#mobileInput').value.trim();

    document.querySelector('#memberNotFoundMsg').style.display = 'none';
    document.querySelector('#memberInfoWrap').style.display = 'none';
    document.querySelector('#previewWrap').style.display = 'none';
    document.querySelector('#lastInvoiceWrap').style.display = 'none';
    lastInvoiceId = null;

    if (!mobile) {
        return;
    }

    const response = await fetch('/membership-fee/search-member', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({ mobile_no: mobile })
    });

    const result = await response.json();

    if (!result.status) {

        document.querySelector('#memberNotFoundMsg').style.display = 'block';
        return;
    }

    currentMember = result.member;

    document.querySelector('#info-name').innerText = result.member.name ?? '';
    document.querySelector('#info-mobile').innerText = result.member.mobile_no ?? '';
    document.querySelector('#info-status').innerText = result.member.status ?? '';
    document.querySelector('#info-last-paid').innerText = result.last_paid_month ?? 'Never Paid';

    document.querySelector('#from_month-field').value = result.next_due_month;
    document.querySelector('#to_month-field').value = result.current_month;

    document.querySelector('#adjustTabItem').style.display =
        window.currentUserRole === 'Admin' ? 'block' : 'none';

    document.querySelector('#memberInfoWrap').style.display = 'block';

    loadHistory();
}

document.getElementById('btnSearchMember').addEventListener('click', searchMember);

document.getElementById('mobileInput').addEventListener('keypress', function (e) {

    if (e.which === 13) {
        e.preventDefault();
        searchMember();
    }
});

document.getElementById('btnPreviewMonths').addEventListener('click', async function () {

    if (!currentMember) return;

    let fromMonth = document.querySelector('#from_month-field').value;
    let toMonth = document.querySelector('#to_month-field').value;

    if (!fromMonth || !toMonth) {

        Swal.fire({
            icon: 'warning',
            title: 'Select both From Month and To Month'
        });

        return;
    }

    const response = await fetch('/membership-fee/preview-months', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            user_id: currentMember.id,
            from_month: fromMonth,
            to_month: toMonth
        })
    });

    const result = await response.json();

    if (!result.status) {

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: result.message ?? 'Unable to preview months.'
        });

        return;
    }

    let tbody = document.querySelector('#previewTableBody');

    tbody.innerHTML = '';

    result.months.forEach(row => {

        tbody.innerHTML += `
        <tr>
            <td>${row.label}</td>
            <td>${Number(row.rate).toFixed(2)}</td>
        </tr>
        `;
    });

    document.querySelector('#total_amount-display').value = Number(result.total_amount).toFixed(2);

    if (result.skipped_already_paid > 0) {

        document.querySelector('#skippedMsg').style.display = 'block';
        document.querySelector('#skippedMsg').innerText =
            `${result.skipped_already_paid} month(s) in this range are already paid/adjusted and have been excluded.`;

    } else {

        document.querySelector('#skippedMsg').style.display = 'none';
    }

    document.querySelector('#previewWrap').style.display =
        result.months.length > 0 ? 'block' : 'none';

    if (result.months.length === 0) {

        Swal.fire({
            icon: 'info',
            title: 'Nothing to collect',
            text: 'All months in this range are already paid or adjusted.'
        });
    }
});

document.getElementById('btnCollectFee').addEventListener('click', async function () {

    if (!currentMember) return;

    let fromMonth = document.querySelector('#from_month-field').value;
    let toMonth = document.querySelector('#to_month-field').value;
    let paymentMode = document.querySelector('#payment_mode-field').value;
    let remarks = document.querySelector('#remarks-field').value;

    Swal.fire({
        title: 'Saving....',
        text: 'Please wait',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: function () {
            Swal.showLoading();
        }
    });

    const response = await fetch('/membership-fee/store', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            user_id: currentMember.id,
            from_month: fromMonth,
            to_month: toMonth,
            payment_mode: paymentMode,
            remarks: remarks
        })
    });

    const result = await response.json();

    if (!result.status) {

        let errorText = result.errors
            ? Object.values(result.errors).flat().join(', ')
            : (result.message ?? 'Unable to record payment.');

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: errorText
        });

        return;
    }

    Swal.fire({
        icon: 'success',
        title: 'Payment Recorded',
        text: result.message
    });

    document.querySelector('#previewWrap').style.display = 'none';
    document.querySelector('#remarks-field').value = '';

    window.open(`/membership-fee/print/${result.invoice_id}`, '_blank');

    await searchMember();

    lastInvoiceId = result.invoice_id;

    document.querySelector('#lastInvoiceNo').innerText = result.invoice_no;
    document.querySelector('#lastInvoiceWrap').style.display = 'block';
});

document.getElementById('btnPrintInvoice').addEventListener('click', function () {

    if (!lastInvoiceId) return;

    window.open(`/membership-fee/print/${lastInvoiceId}`, '_blank');
});

document.getElementById('btnSendWhatsapp').addEventListener('click', async function () {

    if (!lastInvoiceId) return;

    Swal.fire({
        title: 'Please wait',
        text: 'Whatsapp message is under process.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: function () {
            Swal.showLoading();
        }
    });

    const response = await fetch(`/membership-fee/send-whatsapp/${lastInvoiceId}`);

    const result = await response.json();

    Swal.fire({
        icon: result.status ? 'success' : 'error',
        title: result.status ? 'Sent' : 'Error',
        text: result.message
    });
});

async function loadHistory() {

    if (!currentMember) return;

    const response = await fetch(`/membership-fee/history/${currentMember.id}`);

    const result = await response.json();

    let tbody = document.querySelector('#historyTableBody');

    tbody.innerHTML = '';

    if (!result.status || !result.data.length) {

        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No payment history.</td></tr>';
        return;
    }

    result.data.forEach(row => {

        let typeBadge = row.payment_type === 'PAID'
            ? '<span class="badge bg-success">PAID</span>'
            : '<span class="badge bg-warning text-dark">ADJUSTED</span>';

        let actionButtons = row.invoice_id
            ? `
            <button class="btn btn-sm btn-outline-primary historyPrintBtn" data-invoice-id="${row.invoice_id}" title="Print Invoice">
                <i class="ri-printer-line"></i>
            </button>
            <button class="btn btn-sm btn-outline-success ms-1 historyWhatsappBtn" data-invoice-id="${row.invoice_id}" title="Send WhatsApp">
                <i class="ri-whatsapp-line"></i>
            </button>
            `
            : '';

        tbody.innerHTML += `
        <tr>
            <td>${row.month_label}</td>
            <td>${Number(row.rate_applied).toFixed(2)}</td>
            <td>${typeBadge}</td>
            <td>${row.invoice_no ?? '-'}</td>
            <td>${row.remarks ?? ''}</td>
            <td>${row.created_dt ?? ''}</td>
            <td>${actionButtons}</td>
        </tr>
        `;
    });
}

document.getElementById('historyTableBody').addEventListener('click', async function (e) {

    let printBtn = e.target.closest('.historyPrintBtn');
    let whatsappBtn = e.target.closest('.historyWhatsappBtn');

    if (printBtn) {

        window.open(`/membership-fee/print/${printBtn.dataset.invoiceId}`, '_blank');
        return;
    }

    if (whatsappBtn) {

        Swal.fire({
            title: 'Please wait',
            text: 'Whatsapp message is under process.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        const response = await fetch(`/membership-fee/send-whatsapp/${whatsappBtn.dataset.invoiceId}`);

        const result = await response.json();

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Sent' : 'Error',
            text: result.message
        });
    }
});

document.getElementById('btnAdjust').addEventListener('click', async function () {

    if (!currentMember) return;

    let targetMonth = document.querySelector('#target_month-field').value;
    let remarks = document.querySelector('#adjust_remarks-field').value;

    if (!targetMonth) {

        Swal.fire({
            icon: 'warning',
            title: 'Select the month to mark paid through'
        });

        return;
    }

    let confirm = await Swal.fire({
        title: 'Set Last Paid Month?',
        text: `This will set the last paid month to ${targetMonth} WITHOUT creating any invoice or financial transaction.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f06548',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, Adjust'
    });

    if (!confirm.isConfirmed) return;

    const response = await fetch('/membership-fee/adjust', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            user_id: currentMember.id,
            target_month: targetMonth,
            remarks: remarks
        })
    });

    const result = await response.json();

    if (!result.status) {

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: result.message ?? 'Unable to adjust last paid month.'
        });

        return;
    }

    Swal.fire({
        icon: 'success',
        title: 'Adjusted',
        text: result.message
    });

    document.querySelector('#adjust_remarks-field').value = '';

    searchMember();
});
