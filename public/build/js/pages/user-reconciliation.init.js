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

function signedClass(value) {
    return Number(value) < 0 ? 'text-danger' : '';
}

let requestSeq = 0;
let currentUserId = 'EMPLOYEES';
let currentUserLabel = 'All Employees';
let currentMode = 'employees';

// A doctor balance is payable - paid: positive = still to be paid (pending), negative = excess already paid.
function balanceHtml(value) {

    let v = Math.round(Number(value) * 100) / 100;

    if (v > 0.004) {
        return `<span class="text-danger">${fmtMoney(v)}</span><div class="small text-muted">pending</div>`;
    }

    if (v < -0.004) {
        return `<span class="text-primary">${fmtMoney(Math.abs(v))}</span><div class="small text-muted">excess paid</div>`;
    }

    return `<span>${fmtMoney(0)}</span>`;
}

function rowHtml(r, mode, totalRow) {

    let cumCollection = Number(r.cum_collection_cash) + Number(r.cum_collection_non_cash);

    // closing balance = cumulative payable - paid; what this month moved it by; what it was on the 1st
    let closing = Number(r.cum_doctor_payable) - Number(r.cum_doctor_paid);
    let movement = Number(r.period_doctor_payable) - Number(r.period_doctor_paid);
    let carriedOver = closing - movement;

    // first cell: the month (single user / combined) or the employee (employee-wise view)
    let firstCell = totalRow
        ? '<div class="fw-bold">Total</div>'
        : (mode === 'employees'
            ? `<div class="fw-semibold">${escapeHtml(r.user_name)}</div><div class="small text-muted">${escapeHtml(r.role)}</div>`
            : `<div class="fw-semibold">${escapeHtml(r.month_label)}${r.is_live ? ' <span class="badge bg-info">Month to date</span>' : ''}</div>
               <div class="small text-muted">${escapeHtml(r.range_fmt)}</div>`);

    let detailBtn = totalRow
        ? ''
        : `<button type="button" class="btn btn-sm btn-outline-primary showDetailBtn"
                    data-month="${escapeHtml(mode === 'employees' ? currentMonth : r.month)}"
                    data-user-id="${escapeHtml(mode === 'employees' ? r.user_id : currentUserId)}"
                    data-user-name="${escapeHtml(mode === 'employees' ? r.user_name : currentUserLabel)}"
                    data-label="${escapeHtml(mode === 'employees' ? currentMonthLabel : r.month_label)}">
                <i class="ri-list-check-2"></i> Show detail
            </button>`;

    return `
    <tr${totalRow ? ' class="table-light fw-bold"' : (r.is_live ? ' class="table-light"' : '')}>
        <td>${firstCell}</td>
        <td class="text-end">${fmtMoney(r.period_collection_cash)}</td>
        <td class="text-end">${fmtMoney(r.period_collection_non_cash)}</td>
        <td class="text-end">${fmtMoney(r.period_refund)}</td>
        <td class="text-end">${fmtMoney(r.period_doctor_payable)}</td>
        <td class="text-end">${fmtMoney(r.period_doctor_paid)}</td>
        <td class="text-end ${signedClass(r.period_net_cash)}">${fmtMoney(r.period_net_cash)}</td>
        <td class="text-end">${balanceHtml(carriedOver)}</td>
        <td class="text-end">${balanceHtml(movement)}</td>
        <td class="text-end fw-semibold">${balanceHtml(closing)}</td>
        <td class="text-end">${fmtMoney(cumCollection)}</td>
        <td class="text-end">${fmtMoney(r.cum_refund)}</td>
        <td class="text-end">${fmtMoney(r.cum_doctor_payable)}</td>
        <td class="text-end">${fmtMoney(r.cum_doctor_paid)}</td>
        <td class="text-end ${signedClass(r.cum_net_cash)}">${fmtMoney(r.cum_net_cash)}</td>
        <td class="text-end fw-bold ${Number(r.doctor_pending) > 0.004 ? 'text-danger' : ''}">${fmtMoney(r.doctor_pending)}${Number(r.doctor_pending_waiting) > 0.004
            ? `<div class="small fw-normal text-warning" title="The patient has not paid these invoices in full, so Doctor Settlement (By User) cannot offer them yet.">incl. ${fmtMoney(r.doctor_pending_waiting)} awaiting final payment of the invoice</div>`
            : ''}</td>
        <td class="text-nowrap">${detailBtn}</td>
    </tr>`;
}

let currentMonth = '';
let currentMonthLabel = '';

// the month list only matters for the employee-wise view
function fillMonths(months, selected) {

    let sel = document.getElementById('month-field');

    sel.innerHTML = months.map(m => `<option value="${escapeHtml(m.value)}"${m.value === selected ? ' selected' : ''}>${escapeHtml(m.label)}</option>`).join('');
}

async function loadDashboard(e, keepMonth) {

    let select = document.getElementById('user_id-field');

    currentUserId = select.value;
    currentUserLabel = select.selectedOptions[0].text;
    currentMode = currentUserId === 'EMPLOYEES' ? 'employees' : (currentUserId === 'ALL' ? 'all' : 'user');

    document.getElementById('monthWrap').style.display = currentMode === 'employees' ? '' : 'none';
    document.getElementById('firstHeader').innerText = currentMode === 'employees' ? 'Employee' : 'Month';

    const mySeq = ++requestSeq;

    document.getElementById('tableCard').style.display = 'none';
    document.getElementById('noDataWrap').style.display = 'none';
    document.getElementById('printBtn').style.display = 'none';

    let params = { user_id: currentUserId };

    let monthSel = document.getElementById('month-field');

    if (currentMode === 'employees' && monthSel.value) {
        params.month = monthSel.value;
    }

    const response = await fetch('/user-reconciliation/list?' + new URLSearchParams(params).toString());
    const result = await response.json();

    if (mySeq !== requestSeq) return;

    document.getElementById('inceptionNote').innerText = result.inception
        ? 'System data begins ' + result.inception.split('-').reverse().join('-')
        : '';

    if (result.months) {
        fillMonths(result.months, result.month || (monthSel.value || ''));
    }

    if (!result.status || !result.rows.length) {
        document.getElementById('noDataWrap').style.display = 'block';
        return;
    }

    currentMonth = result.month || '';
    currentMonthLabel = result.month_label || '';

    let html = result.rows.map(r => rowHtml(r, currentMode, false)).join('');

    if (currentMode === 'employees') {
        html += rowHtml(result.totals, currentMode, true);
    }

    document.getElementById('reconBody').innerHTML = html;
    document.getElementById('tableCard').style.display = 'block';

    let printParams = { user_id: currentUserId };

    if (currentMode === 'employees') {
        printParams.month = currentMonth;
    }

    let printBtn = document.getElementById('printBtn');
    printBtn.href = '/user-reconciliation/print?' + new URLSearchParams(printParams).toString();
    printBtn.style.display = '';
}

/* ==========================================================
   SHOW DETAIL
========================================================== */

function modeTable(title, byScope) {

    let modes = Array.from(new Set([...Object.keys(byScope.period || {}), ...Object.keys(byScope.cum || {})])).sort();

    if (!modes.length) {
        return `<h6 class="mt-3">${escapeHtml(title)}</h6><div class="text-muted small mb-2">None.</div>`;
    }

    let totalPeriod = 0;
    let totalCum = 0;

    let rows = modes.map(m => {
        let p = Number((byScope.period || {})[m] || 0);
        let c = Number((byScope.cum || {})[m] || 0);
        totalPeriod += p;
        totalCum += c;
        return `<tr><td>${escapeHtml(m)}</td><td class="text-end">${fmtMoney(p)}</td><td class="text-end">${fmtMoney(c)}</td></tr>`;
    }).join('');

    return `
    <h6 class="mt-3">${escapeHtml(title)}</h6>
    <table class="table table-bordered table-sm align-middle mb-2">
        <thead class="table-light">
            <tr><th>Mode</th><th class="text-end" width="170">This month</th><th class="text-end" width="170">Cumulative</th></tr>
        </thead>
        <tbody>${rows}</tbody>
        <tfoot>
            <tr class="table-light fw-bold"><td>Total</td><td class="text-end">${fmtMoney(totalPeriod)}</td><td class="text-end">${fmtMoney(totalCum)}</td></tr>
        </tfoot>
    </table>`;
}

// payable vs paid, cumulative to the end of the month: pending is only one part of the difference
function reconciliationBlock(r) {

    if (!r) return '';

    return `
    <h6 class="mt-1">How doctor payable and paid relate <small class="text-muted fw-normal">(cumulative since inception, up to the end of this month)</small></h6>
    <table class="table table-bordered table-sm align-middle mb-2">
        <tbody>
            <tr><td>Doctor payable created on this user's invoices</td><td class="text-end" width="170">${fmtMoney(r.payable)}</td></tr>
            <tr><td>Doctor payment made by this user</td><td class="text-end">${fmtMoney(r.paid)}</td></tr>
            <tr class="table-light fw-bold"><td>Difference (payable &minus; paid)</td><td class="text-end">${fmtMoney(r.difference)}</td></tr>
            <tr><td class="ps-4">of which: still pending on this user's invoices</td><td class="text-end">${fmtMoney(r.pending)}</td></tr>
            <tr><td class="ps-4">plus: this user's invoices paid out by <b>other</b> users</td><td class="text-end">${fmtMoney(r.own_paid_by_others)}</td></tr>
            <tr><td class="ps-4">less: <b>other</b> users' invoices paid out by this user</td><td class="text-end">&minus; ${fmtMoney(r.paid_for_others)}</td></tr>
        </tbody>
    </table>
    <div class="small text-muted">
        Doctor Payment Pending only counts what is unpaid on the invoices this user billed; "paid" is whatever this user paid out,
        whoever's invoice it belonged to.
    </div>`;
}

// invoice-wise lines behind "plus / less / pending" of the block above (single user only)
function itemsTable(title, hint, rows, kind) {

    if (!rows || !rows.length) return '';

    let total = rows.reduce((s, r) => s + Number(r.amount), 0);

    let head = kind === 'pending'
        ? '<th>Invoice</th><th>Item</th><th>Doctor</th><th>Payable date</th><th class="text-end">Payable</th><th class="text-end">Already settled</th><th class="text-end">Pending</th><th class="text-end">Patient\'s due</th><th>Can it be settled?</th>'
        : '<th>Invoice</th><th>Item</th><th>Doctor</th><th>Billed by</th><th>Payable date</th><th>Paid by</th><th>Settled on</th><th>Settlement no</th><th class="text-end">Amount</th>';

    let body = rows.map(r => kind === 'pending'
        ? `<tr>
            <td>${escapeHtml(r.invoice_no)}</td><td>${escapeHtml(r.item)}</td><td>${escapeHtml(r.doctor)}</td>
            <td>${escapeHtml(r.payable_date)}</td><td class="text-end">${fmtMoney(r.payable_amount)}</td>
            <td class="text-end">${fmtMoney(r.settled)}</td><td class="text-end">${fmtMoney(r.amount)}</td>
            <td class="text-end ${Number(r.patient_due) > 0 ? 'text-danger' : ''}">${fmtMoney(r.patient_due)}</td>
            <td>${r.ready
                ? `<span class="badge bg-success">Ready to settle</span><div class="small text-muted">Doctor Settlement (By User): ${escapeHtml(r.settle_under)}, invoice date ${escapeHtml(r.settle_on)}</div>`
                : '<span class="badge bg-warning text-dark">Waiting</span><div class="small text-muted">patient still owes the invoice balance</div>'}</td></tr>`
        : `<tr>
            <td>${escapeHtml(r.invoice_no)}</td><td>${escapeHtml(r.item)}</td><td>${escapeHtml(r.doctor)}</td>
            <td>${escapeHtml(r.billed_by)}</td><td>${escapeHtml(r.payable_date)}</td>
            <td>${escapeHtml(r.paid_by)}</td><td>${escapeHtml(r.settled_on)}</td><td>${escapeHtml(r.settlement_no)}</td>
            <td class="text-end">${fmtMoney(r.amount)}</td></tr>`
    ).join('');

    return `
    <h6 class="mt-3">${escapeHtml(title)} <small class="text-muted fw-normal">${escapeHtml(hint)}</small></h6>
    <div class="table-responsive">
    <table class="table table-bordered table-sm align-middle mb-2">
        <thead class="table-light"><tr>${head}</tr></thead>
        <tbody>${body}</tbody>
        <tfoot>
            <tr class="table-light fw-bold"><td colspan="${kind === 'pending' ? 6 : 8}" class="text-end">Total (${rows.length})</td><td class="text-end">${fmtMoney(total)}</td>${kind === 'pending' ? '<td></td><td></td>' : ''}</tr>
            ${kind === 'pending' ? `
            <tr class="small"><td colspan="6" class="text-end">of which ready to settle now</td><td class="text-end">${fmtMoney(rows.filter(r => r.ready).reduce((s, r) => s + Number(r.amount), 0))}</td><td colspan="2"></td></tr>
            <tr class="small"><td colspan="6" class="text-end">of which waiting for the patient's balance payment</td><td class="text-end">${fmtMoney(rows.filter(r => !r.ready).reduce((s, r) => s + Number(r.amount), 0))}</td><td colspan="2"></td></tr>` : ''}
        </tfoot>
    </table>
    </div>`;
}

function itemsBlock(items) {

    if (!items) return '';

    return itemsTable('Other users\' invoices paid out by this user', '(the "less" line above)', items.paid_for_others, 'settled')
        + itemsTable('This user\'s invoices paid out by other users', '(the "plus" line above)', items.own_paid_by_others, 'settled')
        + itemsTable('Still pending on this user\'s invoices', '(the "pending" line above)', items.pending, 'pending');
}

function doctorTable(doctors) {

    if (!doctors.length) {
        return '<h6 class="mt-3">Doctor-wise</h6><div class="text-muted small">No doctor payable or payment.</div>';
    }

    let t = { payable: 0, paid: 0, pending: 0, unpaid: 0 };

    let rows = doctors.map(d => {
        t.payable += Number(d.payable);
        t.paid += Number(d.paid);
        t.pending += Number(d.pending);
        t.unpaid += Number(d.unpaid_count);
        return `
        <tr>
            <td>${escapeHtml(d.doctor_name)}</td>
            <td class="text-end">${fmtMoney(d.payable)}</td>
            <td class="text-end">${fmtMoney(d.paid)}</td>
            <td class="text-end ${Number(d.pending) > 0.004 ? 'text-danger fw-semibold' : ''}">${fmtMoney(d.pending)}</td>
            <td class="text-end">${d.unpaid_count}</td>
        </tr>`;
    }).join('');

    return `
    <h6 class="mt-3">Doctor-wise <small class="text-muted fw-normal">(payable and paid are for this month; pending is cumulative at month end)</small></h6>
    <table class="table table-bordered table-sm align-middle">
        <thead class="table-light">
            <tr>
                <th>Doctor</th>
                <th class="text-end" width="150">Payable<br><small class="fw-normal">this month</small></th>
                <th class="text-end" width="150">Paid<br><small class="fw-normal">this month</small></th>
                <th class="text-end" width="170">Pending<br><small class="fw-normal">since inception</small></th>
                <th class="text-end" width="110">Unpaid items</th>
            </tr>
        </thead>
        <tbody>${rows}</tbody>
        <tfoot>
            <tr class="table-light fw-bold">
                <td>Total</td>
                <td class="text-end">${fmtMoney(t.payable)}</td>
                <td class="text-end">${fmtMoney(t.paid)}</td>
                <td class="text-end">${fmtMoney(t.pending)}</td>
                <td class="text-end">${t.unpaid}</td>
            </tr>
        </tfoot>
    </table>`;
}

document.addEventListener('click', async function (e) {

    let btn = e.target.closest('.showDetailBtn');

    if (!btn) return;

    let body = document.getElementById('detailBody');

    document.getElementById('detailTitle').innerText = btn.dataset.label + ' -- ' + btn.dataset.userName;
    body.innerHTML = '<div class="text-center text-muted py-4">Loading...</div>';

    let detailParams = new URLSearchParams({ month: btn.dataset.month, user_id: btn.dataset.userId }).toString();

    document.getElementById('detailPrintBtn').href = '/user-reconciliation/print-detail?' + detailParams;

    bootstrap.Modal.getOrCreateInstance(document.getElementById('detailModal')).show();

    try {

        const response = await fetch('/user-reconciliation/detail?' + detailParams);
        const result = await response.json();

        if (!result.status) {
            body.innerHTML = '<div class="alert alert-danger">Unable to load the detail.</div>';
            return;
        }

        body.innerHTML =
            reconciliationBlock(result.reconciliation)
            + itemsBlock(result.items)
            + modeTable('Collection by mode', result.modes.collection)
            + modeTable('Refund by mode', result.modes.refund)
            + modeTable('Doctor payment made, by mode', result.modes.doctor_paid)
            + doctorTable(result.doctors);

    } catch (err) {
        body.innerHTML = '<div class="alert alert-danger">Unable to load the detail.</div>';
    }
});

document.getElementById('loadBtn').addEventListener('click', loadDashboard);
document.getElementById('user_id-field').addEventListener('change', loadDashboard);
document.getElementById('month-field').addEventListener('change', loadDashboard);

loadDashboard();
