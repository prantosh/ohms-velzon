let doctorGroups = [];
let activeGroup = null;

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

/* ==========================================================
   PAYMENT MODE FIELD TOGGLE
========================================================== */

function toggleModeFields() {

    let mode = document.getElementById('payment_mode-field').value;

    document.querySelectorAll('.pm-field').forEach(el => {
        let modes = el.dataset.modes.split(',');
        el.style.display = modes.includes(mode) ? 'block' : 'none';
    });
}

document.getElementById('payment_mode-field').addEventListener('change', toggleModeFields);

/* ==========================================================
   LOAD OUTSTANDING (GROUPED BY DOCTOR)
========================================================== */

document.getElementById('loadBtn').addEventListener('click', async function () {

    let userId = document.getElementById('user_id-field').value;
    let invoiceDate = document.getElementById('invoice_date-field').value;

    if (!userId || !invoiceDate) {
        Swal.fire({ icon: 'warning', title: 'Select User and Invoice Date', text: 'Both fields are required.' });
        return;
    }

    const response = await fetch('/doctor-settlement-by-user/outstanding', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({ user_id: userId, invoice_date: invoiceDate })
    });

    const result = await response.json();

    document.getElementById('settlementFieldsCard').style.display = 'none';
    document.getElementById('summaryRow').style.display = 'none';
    document.getElementById('doctorGroupsWrap').innerHTML = '';
    document.getElementById('noDataWrap').style.display = 'none';

    if (!result.status) {
        Swal.fire({ icon: 'error', title: 'Error', text: result.message ?? 'Unable to load outstanding payables.' });
        return;
    }

    if (!result.groups.length) {
        document.getElementById('noDataWrap').style.display = 'block';
        return;
    }

    doctorGroups = result.groups.map(group => {
        group.items.forEach(item => {
            item.selected = false;
            item.settlement_amount = Number(item.balance_amount);
        });
        return group;
    });

    document.getElementById('summary-total_doctors').innerText = result.summary.total_doctors;
    document.getElementById('summary-total_invoices').innerText = result.summary.total_invoices;
    document.getElementById('summary-total_outstanding').innerText = '₹' + fmtMoney(result.summary.total_outstanding);

    document.getElementById('summaryRow').style.display = 'flex';
    document.getElementById('settlementFieldsCard').style.display = 'block';

    renderDoctorGroups();
});

/* ==========================================================
   RENDER DOCTOR GROUPS
========================================================== */

function renderDoctorGroups() {

    let wrap = document.getElementById('doctorGroupsWrap');

    wrap.innerHTML = '';

    doctorGroups.forEach((group, groupIndex) => {

        let rowsHtml = group.items.map(item => `
            <tr>
                <td><input type="checkbox" class="form-check-input row-check" data-group="${groupIndex}" data-id="${item.payable_id}" ${item.selected ? 'checked' : ''}></td>
                <td>${escapeHtml(item.invoice_no)}</td>
                <td>${escapeHtml(item.invoice_date)}</td>
                <td><span class="badge ${item.invoice_type === 'DOCTOR_VISIT' ? 'bg-primary' : 'bg-success'}">${item.invoice_type === 'DOCTOR_VISIT' ? 'CONSULTATION' : 'DIAGNOSTIC'}</span></td>
                <td>${escapeHtml(item.patient_name)}</td>
                <td>${escapeHtml(item.item_description ?? '-')}</td>
                <td class="text-end">${fmtMoney(item.payable_amount)}</td>
                <td class="text-end">${fmtMoney(item.paid_amount)}</td>
                <td class="text-end">${fmtMoney(item.balance_amount)}</td>
                <td>
                    <input type="number" class="form-control form-control-sm settlement-input" data-group="${groupIndex}" data-id="${item.payable_id}"
                        value="${item.settlement_amount}" min="0" max="${item.balance_amount}" step="0.01">
                </td>
            </tr>
        `).join('');

        wrap.innerHTML += `
        <div class="card doctor-group-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0"><i class="ri-user-heart-line"></i> ${escapeHtml(group.doctor_name)}</h5>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-danger">Outstanding: ₹${fmtMoney(group.total_outstanding)}</span>
                    <button type="button" class="btn btn-sm btn-light select-all-btn" data-group="${groupIndex}">Select All</button>
                    <button type="button" class="btn btn-sm btn-light clear-btn" data-group="${groupIndex}">Clear</button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive settlement-table">
                    <table class="table table-bordered table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="30"></th>
                                <th>Invoice No</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Patient</th>
                                <th>Description</th>
                                <th class="text-end">Payable</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Balance</th>
                                <th width="120">Settlement Amt</th>
                            </tr>
                        </thead>
                        <tbody>${rowsHtml}</tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <div>
                        Selected: <span class="fw-semibold group-selected-count" data-group="${groupIndex}">0</span>
                        &nbsp;|&nbsp;
                        Amount: <span class="fw-semibold group-selected-amount" data-group="${groupIndex}">₹0.00</span>
                    </div>
                    <button type="button" class="btn btn-success preview-settle-btn" data-group="${groupIndex}" disabled>
                        <i class="ri-eye-line"></i>
                        Preview &amp; Settle This Doctor
                    </button>
                </div>
            </div>
        </div>
        `;
    });

    doctorGroups.forEach((group, groupIndex) => {
        updateGroupTotals(groupIndex);
    });
}

/* ==========================================================
   CHECKBOX / AMOUNT INTERACTIONS
========================================================== */

document.addEventListener('change', function (e) {

    if (e.target.classList.contains('row-check')) {

        let groupIndex = e.target.dataset.group;
        let id = e.target.dataset.id;
        let item = doctorGroups[groupIndex].items.find(x => x.payable_id == id);

        if (!item) return;

        item.selected = e.target.checked;

        if (item.selected) {
            item.settlement_amount = Number(item.balance_amount);
        }

        let input = document.querySelector(`.settlement-input[data-group="${groupIndex}"][data-id="${id}"]`);
        if (input) input.value = item.settlement_amount;

        updateGroupTotals(groupIndex);
    }
});

document.addEventListener('input', function (e) {

    if (e.target.classList.contains('settlement-input')) {

        let groupIndex = e.target.dataset.group;
        let id = e.target.dataset.id;
        let item = doctorGroups[groupIndex].items.find(x => x.payable_id == id);

        if (!item) return;

        let balance = Number(item.balance_amount);
        let amount = parseFloat(e.target.value) || 0;

        if (amount > balance) {
            amount = balance;
            e.target.value = balance.toFixed(2);
        }

        if (amount < 0) {
            amount = 0;
            e.target.value = '0.00';
        }

        item.settlement_amount = amount;

        updateGroupTotals(groupIndex);
    }
});

document.addEventListener('click', function (e) {

    let selectAllBtn = e.target.closest('.select-all-btn');
    let clearBtn = e.target.closest('.clear-btn');
    let previewBtn = e.target.closest('.preview-settle-btn');

    if (selectAllBtn) {

        let groupIndex = selectAllBtn.dataset.group;

        doctorGroups[groupIndex].items.forEach(item => {
            item.selected = true;
            item.settlement_amount = Number(item.balance_amount);
        });

        renderDoctorGroups();

        return;
    }

    if (clearBtn) {

        let groupIndex = clearBtn.dataset.group;

        doctorGroups[groupIndex].items.forEach(item => {
            item.selected = false;
        });

        renderDoctorGroups();

        return;
    }

    if (previewBtn) {

        let groupIndex = previewBtn.dataset.group;

        openPreview(groupIndex);
    }
});

function updateGroupTotals(groupIndex) {

    let group = doctorGroups[groupIndex];

    let selectedItems = group.items.filter(x => x.selected);

    let count = selectedItems.length;

    let amount = selectedItems.reduce((sum, x) => sum + Number(x.settlement_amount), 0);

    document.querySelector(`.group-selected-count[data-group="${groupIndex}"]`).innerText = count;
    document.querySelector(`.group-selected-amount[data-group="${groupIndex}"]`).innerText = '₹' + fmtMoney(amount);
    document.querySelector(`.preview-settle-btn[data-group="${groupIndex}"]`).disabled = count === 0 || amount <= 0;
}

/* ==========================================================
   PREVIEW & SETTLE (reuses /doctor-settlement/preview + /store)
========================================================== */

async function openPreview(groupIndex) {

    let group = doctorGroups[groupIndex];

    let selectedItems = group.items.filter(x => x.selected);

    if (!selectedItems.length) {
        Swal.fire({ icon: 'warning', title: 'No invoices selected', text: 'Select at least one invoice for this doctor.' });
        return;
    }

    let settlementItemsPayload = selectedItems.map(item => ({
        payable_id: item.payable_id,
        invoice_id: item.invoice_id,
        invoice_no: item.invoice_no,
        settlement_amount: item.settlement_amount,
        balance_amount: item.balance_amount
    }));

    const response = await fetch('/doctor-settlement/preview', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            doctor_id: group.doctor_id,
            settlement_items: settlementItemsPayload
        })
    });

    const result = await response.json();

    if (!result.status) {

        let errorText = result.errors
            ? Object.values(result.errors).flat().join(', ')
            : (result.message ?? 'Unable to preview settlement.');

        Swal.fire({ icon: 'error', title: 'Error', text: errorText });
        return;
    }

    activeGroup = { groupIndex, group, settlementItemsPayload, summary: result.summary };

    document.getElementById('pv-doctor_name').innerText = group.doctor_name;
    document.getElementById('pv-settlement_date').innerText = document.getElementById('settlement_date-field').value;
    document.getElementById('pv-payment_mode').innerText = document.getElementById('payment_mode-field').selectedOptions[0].text;
    document.getElementById('pv-count').innerText = selectedItems.length;

    let itemsBody = document.getElementById('pv-items');
    itemsBody.innerHTML = selectedItems.map(item => `
        <tr>
            <td>${escapeHtml(item.invoice_no)}</td>
            <td>${escapeHtml(item.patient_name)}</td>
            <td class="text-end">${fmtMoney(item.settlement_amount)}</td>
        </tr>
    `).join('');

    let netAmount = result.summary.net_amount ?? result.summary.gross_amount ?? selectedItems.reduce((s, x) => s + Number(x.settlement_amount), 0);

    document.getElementById('pv-net').innerText = '₹' + fmtMoney(netAmount);

    new bootstrap.Offcanvas(document.getElementById('settlementPreviewOffcanvas')).show();
}

document.getElementById('btnConfirmSettle').addEventListener('click', async function () {

    if (!activeGroup) return;

    let settlementDate = document.getElementById('settlement_date-field').value;

    if (!settlementDate) {
        Swal.fire({ icon: 'warning', title: 'Settlement date required' });
        return;
    }

    let payload = {
        doctor_id: activeGroup.group.doctor_id,
        settlement_date: settlementDate,
        payment_mode: document.getElementById('payment_mode-field').value,
        bank_name: document.getElementById('bank_name-field').value,
        cheque_no: document.getElementById('cheque_no-field').value,
        utr_no: document.getElementById('utr_no-field').value,
        remarks: document.getElementById('remarks-field').value,
        payable_ids: activeGroup.settlementItemsPayload.map(x => x.payable_id),
        settlement_items: activeGroup.settlementItemsPayload
    };

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

    const response = await fetch('/doctor-settlement/store', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!result.status) {

        let errorText = result.errors
            ? Object.values(result.errors).flat().join(', ')
            : (result.message ?? 'Unable to save settlement.');

        Swal.fire({ icon: 'error', title: 'Error', text: errorText });
        return;
    }

    bootstrap.Offcanvas.getInstance(document.getElementById('settlementPreviewOffcanvas'))?.hide();

    Swal.fire({
        icon: 'success',
        title: 'Settlement Completed',
        html: `<b>Settlement No:</b> ${result.settlement_no}<br><br>${result.message}`
    }).then(() => {
        window.open(`/doctor-settlement/pdf/${result.settlement_id}`, '_blank');
        document.getElementById('loadBtn').click();
    });

    activeGroup = null;
});

document.getElementById('settlement_date-field').value = new Date().toISOString().substring(0, 10);
document.getElementById('invoice_date-field').value = new Date().toISOString().substring(0, 10);
toggleModeFields();
