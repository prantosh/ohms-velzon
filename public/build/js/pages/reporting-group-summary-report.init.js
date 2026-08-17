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

function userRowHtml(row) {
    return `
    <tr>
        <td>${escapeHtml(row.user_name)}</td>
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
            tbody.innerHTML += userRowHtml(row);
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

    renderReport(result, params);
}

document.getElementById('loadReportBtn').addEventListener('click', loadReport);

/* ==========================================================
   INIT (default: today)
========================================================== */

document.getElementById('date-field').value = new Date().toISOString().substring(0, 10);
