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

const COLUMNS = ['collection_cash', 'collection_noncash', 'refund', 'doctor_payable', 'paid_to_doctor', 'cash_to_deposit'];

let requestSeq = 0;

let reportDate = '';

function userRowHtml(row, groupName) {

    // payable and paid disagree: offer the day-wise explanation
    let mismatchBtn = row.mismatch
        ? `<button type="button" class="btn btn-sm btn-warning ms-2 py-0 mismatchBtn"
                title="Payable and Paid to Doctor do not match -- see why"
                data-user-id="${escapeHtml(row.user_id)}"
                data-user-name="${escapeHtml(row.user_name)}"
                data-group="${escapeHtml(groupName)}">
                <i class="ri-error-warning-line"></i> Why?
           </button>`
        : '';

    return `
    <tr>
        <td>${escapeHtml(row.user_name)}${mismatchBtn}</td>
        ${COLUMNS.map(col => `<td class="text-end">${fmtMoney(row[col])}</td>`).join('')}
    </tr>
    `;
}

function subtotalRowHtml(groupName, subtotal) {
    return `
    <tr class="table-light fw-semibold">
        <td>${escapeHtml(groupName)} -- Subtotal</td>
        ${COLUMNS.map(col => `<td class="text-end">${fmtMoney(subtotal[col])}</td>`).join('')}
    </tr>
    `;
}

function renderReport(result, params) {

    document.getElementById('totalsRow').style.display = 'none';
    document.getElementById('reportCard').style.display = 'none';
    document.getElementById('noDataWrap').style.display = 'none';
    document.getElementById('printReportBtn').style.display = 'none';

    if (!result.status || !result.groups.length) {
        document.getElementById('noDataWrap').style.display = 'block';
        return;
    }

    let g = result.grand_total;

    COLUMNS.forEach(col => {
        document.getElementById('total-' + col).innerText = '₹' + fmtMoney(g[col]);
    });

    document.getElementById('totalsRow').style.display = 'flex';

    let tbody = document.getElementById('reportTableBody');
    tbody.innerHTML = '';

    result.groups.forEach(group => {

        tbody.innerHTML += `
        <tr class="table-secondary">
            <th colspan="${COLUMNS.length + 1}">${escapeHtml(group.group_name)}</th>
        </tr>
        `;

        group.rows.forEach(row => {
            tbody.innerHTML += userRowHtml(row, group.group_name);
        });

        tbody.innerHTML += subtotalRowHtml(group.group_name, group.subtotal);
    });

    tbody.innerHTML += `
    <tr class="table-dark fw-bold">
        <td>Grand Total</td>
        ${COLUMNS.map(col => `<td class="text-end">${fmtMoney(g[col])}</td>`).join('')}
    </tr>
    `;

    document.getElementById('reportCard').style.display = 'block';

    let printBtn = document.getElementById('printReportBtn');
    printBtn.href = `/reporting-group-summary-report/print?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

async function loadReport() {

    let date = document.getElementById('date-field').value;

    if (!date) {
        Swal.fire({ icon: 'warning', title: 'Missing Date', text: 'Please select a date.' });
        return;
    }

    const mySeq = ++requestSeq;

    const params = new URLSearchParams({ date: date });

    const response = await fetch(`/reporting-group-summary-report/report?${params.toString()}`);
    const result = await response.json();

    if (mySeq !== requestSeq) return;

    reportDate = date;

    renderReport(result, params);
}

/* ==========================================================
   MISMATCH DETAIL (modal) -- day-wise, only the days that do not agree
========================================================== */

document.addEventListener('click', async function (e) {

    let btn = e.target.closest('.mismatchBtn');

    if (!btn) return;

    let body = document.getElementById('mismatchModalBody');
    document.getElementById('mismatchModalTitle').innerText = btn.dataset.userName + ' -- ' + btn.dataset.group;
    body.innerHTML = '<div class="text-center text-muted py-4">Loading...</div>';

    bootstrap.Modal.getOrCreateInstance(document.getElementById('mismatchModal')).show();

    const params = new URLSearchParams({ date: reportDate, user_id: btn.dataset.userId, group: btn.dataset.group });

    try {

        const response = await fetch(`/reporting-group-summary-report/mismatch-detail?${params.toString()}`);
        const result = await response.json();

        if (!result.status) {
            body.innerHTML = '<div class="alert alert-danger">Unable to load the detail.</div>';
            return;
        }

        renderMismatch(result, body);

    } catch (err) {
        body.innerHTML = '<div class="alert alert-danger">Unable to load the detail.</div>';
    }
});

function renderMismatch(result, body) {

    if (!result.days.length) {
        body.innerHTML = '<div class="alert alert-info">No difference found for this user and group.</div>';
        return;
    }

    let html = `
    <p class="text-muted small mb-2">
        Report day <b>${escapeHtml(result.date_fmt)}</b>.<br>
        <b>Payable</b> = doctor payables created on the report day by this user's invoices.<br>
        <b>Paid</b> = what this user paid out to doctors on the report day. This can include payables created on earlier days
        or by other users. An earlier day's payable was already counted in <i>that</i> day's report, so it adds nothing to Payable here.<br>
        Only the days where the two do not agree are listed.
    </p>
    <table class="table table-bordered align-middle">
        <thead class="table-light">
            <tr>
                <th>Payable was created on</th>
                <th class="text-end" width="130">Payable<br><small class="fw-normal">(this report day)</small></th>
                <th class="text-end" width="130">Paid<br><small class="fw-normal">(this report day)</small></th>
                <th class="text-end" width="130">Difference</th>
            </tr>
        </thead>
        <tbody>`;

    result.days.forEach(day => {

        html += `
        <tr class="table-secondary fw-semibold">
            <td>${escapeHtml(day.date_fmt)}${day.date_fmt !== result.date_fmt ? ' <span class="fw-normal small text-muted">(earlier day -- its payables were counted in that day\'s report)</span>' : ''}</td>
            <td class="text-end">${fmtMoney(day.payable)}</td>
            <td class="text-end">${fmtMoney(day.paid)}</td>
            <td class="text-end ${day.difference > 0 ? 'text-danger' : 'text-primary'}">${fmtMoney(day.difference)}</td>
        </tr>`;

        if (day.lines.length) {

            html += `
        <tr>
            <td colspan="4" class="p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr class="text-muted small">
                            <th>Invoice</th><th>Doctor</th><th>Item</th><th>Created by</th>
                            <th class="text-end">Payable amount</th><th class="text-end">Paid</th><th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${day.lines.map(l => `
                        <tr>
                            <td>${escapeHtml(l.invoice_no)}</td>
                            <td>${escapeHtml(l.doctor)}</td>
                            <td>${escapeHtml(l.item)}</td>
                            <td>${escapeHtml(l.created_by)}</td>
                            <td class="text-end">${fmtMoney(l.original_payable)}</td>
                            <td class="text-end">${fmtMoney(l.paid_amount)}</td>
                            <td class="small">${escapeHtml(l.note)}</td>
                        </tr>`).join('')}
                    </tbody>
                </table>
            </td>
        </tr>`;
        }
    });

    html += `
        </tbody>
        <tfoot>
            <tr class="table-light fw-bold">
                <td>Total of the report day</td>
                <td class="text-end">${fmtMoney(result.total_payable)}</td>
                <td class="text-end">${fmtMoney(result.total_paid)}</td>
                <td class="text-end">${fmtMoney(result.total_payable - result.total_paid)}</td>
            </tr>
        </tfoot>
    </table>`;

    body.innerHTML = html;
}

document.getElementById('loadReportBtn').addEventListener('click', loadReport);

/* ==========================================================
   INIT (default: today)
========================================================== */

document.getElementById('date-field').value = new Date().toISOString().substring(0, 10);
