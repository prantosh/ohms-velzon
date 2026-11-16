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
    const filters = {
        from_date: document.getElementById('from_date-field').value,
        to_date: document.getElementById('to_date-field').value,
    };

    const doctorId = document.getElementById('doctor_id-field').value;
    if (doctorId) filters.doctor_id = doctorId;

    return filters;
}

function render(result, params) {

    document.getElementById('totalsRow').style.display = 'none';
    document.getElementById('reportCard').style.display = 'none';
    document.getElementById('noDataWrap').style.display = 'none';
    document.getElementById('printBtn').style.display = 'none';

    if (!result.status || !result.rows.length) {
        document.getElementById('noDataWrap').style.display = 'block';
        return;
    }

    document.getElementById('total-count').innerText = result.grand_total.count;
    document.getElementById('total-amount').innerText = '₹' + fmtMoney(result.grand_total.amount);
    document.getElementById('totalsRow').style.display = 'flex';

    let tbody = document.getElementById('reportTableBody');
    tbody.innerHTML = '';

    result.rows.forEach((row, index) => {
        tbody.innerHTML += `
        <tr>
            <td class="text-center">${index + 1}</td>
            <td class="fw-semibold">${escapeHtml(row.invoice_no)}</td>
            <td>${escapeHtml(row.visit_date_fmt)}</td>
            <td>${escapeHtml(row.patient_name)}</td>
            <td>${escapeHtml(row.doctor_name)}</td>
            <td class="text-end">${fmtMoney(row.total_amount)}</td>
            <td class="text-end">${fmtMoney(row.waived_amount)}</td>
            <td>${escapeHtml(row.waived_by_name)}</td>
            <td>${escapeHtml(row.waived_at_fmt)}</td>
            <td>${escapeHtml(row.waiver_reason)}</td>
        </tr>
        `;
    });

    tbody.innerHTML += `
    <tr class="table-light fw-bold">
        <td colspan="6" class="text-end">Total</td>
        <td class="text-end">${fmtMoney(result.grand_total.amount)}</td>
        <td colspan="3"></td>
    </tr>
    `;

    document.getElementById('reportCard').style.display = 'block';

    let printBtn = document.getElementById('printBtn');
    printBtn.href = `/doctor-fee-waiver-report/print?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

async function loadReport() {

    let filters = currentFilters();

    if (!filters.from_date || !filters.to_date) {
        Swal.fire({ icon: 'warning', title: 'Missing Filters', text: 'Please select both dates.' });
        return;
    }

    const mySeq = ++requestSeq;

    const params = new URLSearchParams(filters);

    const response = await fetch(`/doctor-fee-waiver-report/list?${params.toString()}`);
    const result = await response.json();

    if (mySeq !== requestSeq) return;

    render(result, params);
}

document.getElementById('loadReportBtn').addEventListener('click', loadReport);

/* ==========================================================
   INIT (default: last 30 days)
========================================================== */

setFlatpickrValue('to_date-field', new Date().toISOString().substring(0, 10));
setFlatpickrValue('from_date-field', new Date(Date.now() - 29 * 24 * 60 * 60 * 1000).toISOString().substring(0, 10));
