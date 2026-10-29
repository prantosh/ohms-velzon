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

function renderReport(result, params) {

    document.getElementById('totalsRow').style.display = 'none';
    document.getElementById('reportCard').style.display = 'none';
    document.getElementById('noDataWrap').style.display = 'none';
    document.getElementById('printReportBtn').style.display = 'none';

    if (!result.status || !result.days.length) {
        document.getElementById('noDataWrap').style.display = 'block';
        return;
    }

    const g = result.grand_total;

    COLUMNS.forEach(col => {
        document.getElementById('total-' + col).innerText = '₹' + fmtMoney(g[col]);
    });

    document.getElementById('totalsRow').style.display = 'flex';

    let html = '';

    result.days.forEach(day => {

        html += `<tr class="table-secondary"><th colspan="${COLUMNS.length + 2}">${escapeHtml(day.date_fmt)}</th></tr>`;

        day.rows.forEach(row => {
            html += `
            <tr>
                <td>${escapeHtml(row.user_name)}</td>
                <td>${escapeHtml(row.group_name)}</td>
                ${COLUMNS.map(col => `<td class="text-end">${fmtMoney(row[col])}</td>`).join('')}
            </tr>`;
        });

        html += `
        <tr class="table-light fw-semibold">
            <td colspan="2">${escapeHtml(day.date_fmt)} -- Day Total</td>
            ${COLUMNS.map(col => `<td class="text-end">${fmtMoney(day.subtotal[col])}</td>`).join('')}
        </tr>`;
    });

    html += `
    <tr class="table-dark fw-bold">
        <td colspan="2">Grand Total</td>
        ${COLUMNS.map(col => `<td class="text-end">${fmtMoney(g[col])}</td>`).join('')}
    </tr>`;

    document.getElementById('reportTableBody').innerHTML = html;
    document.getElementById('reportCard').style.display = 'block';

    const printBtn = document.getElementById('printReportBtn');
    printBtn.href = `/day-wise-group-summary-report/print?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

async function loadReport() {

    const from = document.getElementById('from-date').value;
    const to = document.getElementById('to-date').value;

    if (!from || !to) {
        Swal.fire({ icon: 'warning', title: 'Missing Date', text: 'Please select both From and To dates.' });
        return;
    }

    if (to < from) {
        Swal.fire({ icon: 'warning', title: 'Invalid Range', text: 'To Date cannot be before From Date.' });
        return;
    }

    const params = new URLSearchParams({ from_date: from, to_date: to });

    const userId = document.getElementById('user-id').value;
    const group = document.getElementById('group').value;

    if (userId) params.set('user_id', userId);
    if (group) params.set('group', group);

    const mySeq = ++requestSeq;

    const response = await fetch(`/day-wise-group-summary-report/report?${params.toString()}`);
    const result = await response.json();

    if (mySeq !== requestSeq) return;

    renderReport(result, params);
}

document.getElementById('loadReportBtn').addEventListener('click', loadReport);

const today = new Date().toISOString().substring(0, 10);
document.getElementById('from-date').value = today;
document.getElementById('to-date').value = today;
