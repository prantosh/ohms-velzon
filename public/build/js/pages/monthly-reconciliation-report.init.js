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

let requestSeq = 0;

function currentFilters() {
    return {
        month: document.getElementById('month-field').value,
        year: document.getElementById('year-field').value,
    };
}

function renderReport(result, params) {

    document.getElementById('totalsRow').style.display = 'none';
    document.getElementById('reportCard').style.display = 'none';
    document.getElementById('noDataWrap').style.display = 'none';
    document.getElementById('printReportBtn').style.display = 'none';

    if (!result.status || !result.rows.length) {
        document.getElementById('noDataWrap').style.display = 'block';
        return;
    }

    let g = result.grand_total;

    document.getElementById('total-deposit_cash').innerText = '₹' + fmtMoney(g.deposit_cash);
    document.getElementById('total-total_income').innerText = '₹' + fmtMoney(g.total_income);

    document.getElementById('totalsRow').style.display = 'flex';

    let tbody = document.getElementById('reportTableBody');
    tbody.innerHTML = '';

    result.rows.forEach(row => {
        tbody.innerHTML += `
        <tr>
            <td class="fw-semibold">${escapeHtml(row.category)}</td>
            <td class="text-end">${fmtMoney(row.received_cash)}</td>
            <td class="text-end">${fmtMoney(row.received_noncash)}</td>
            <td class="text-end">${fmtMoney(row.refund_cash)}</td>
            <td class="text-end">${fmtMoney(row.doctor_payment_cash)}</td>
            <td class="text-end fw-semibold">${fmtMoney(row.deposit_cash)}</td>
            <td class="text-end">${fmtMoney(row.doctor_payment_noncash_source)}</td>
            <td class="text-end fw-semibold">${fmtMoney(row.total_income)}</td>
        </tr>
        `;
    });

    tbody.innerHTML += `
    <tr class="table-light fw-bold">
        <td>Total</td>
        <td class="text-end">${fmtMoney(g.received_cash)}</td>
        <td class="text-end">${fmtMoney(g.received_noncash)}</td>
        <td class="text-end">${fmtMoney(g.refund_cash)}</td>
        <td class="text-end">${fmtMoney(g.doctor_payment_cash)}</td>
        <td class="text-end">${fmtMoney(g.deposit_cash)}</td>
        <td class="text-end">${fmtMoney(g.doctor_payment_noncash_source)}</td>
        <td class="text-end">${fmtMoney(g.total_income)}</td>
    </tr>
    `;

    document.getElementById('reportCard').style.display = 'block';

    let printBtn = document.getElementById('printReportBtn');
    printBtn.href = `/monthly-reconciliation-report/print?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

async function loadReport() {

    let filters = currentFilters();

    if (!filters.month || !filters.year) {
        Swal.fire({ icon: 'warning', title: 'Missing Filters', text: 'Please select both month and year.' });
        return;
    }

    const mySeq = ++requestSeq;

    const params = new URLSearchParams(filters);

    const response = await fetch(`/monthly-reconciliation-report/report?${params.toString()}`);
    const result = await response.json();

    if (mySeq !== requestSeq) return;

    renderReport(result, params);
}

document.getElementById('loadReportBtn').addEventListener('click', loadReport);

/* ==========================================================
   INIT (default: current month/year; year options = last 5 years)
========================================================== */

(function initFilters() {
    const now = new Date();
    const currentYear = now.getFullYear();

    const yearField = document.getElementById('year-field');
    for (let y = currentYear; y >= currentYear - 4; y--) {
        const opt = document.createElement('option');
        opt.value = y;
        opt.text = y;
        yearField.appendChild(opt);
    }

    document.getElementById('month-field').value = now.getMonth() + 1;
    yearField.value = currentYear;
})();
