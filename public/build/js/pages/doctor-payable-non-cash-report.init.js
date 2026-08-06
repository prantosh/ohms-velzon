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
    let filters = {
        from_date: document.getElementById('from_date-field').value,
        to_date: document.getElementById('to_date-field').value,
    };
    let doctorId = document.getElementById('doctor_id-field').value;
    if (doctorId) filters.doctor_id = doctorId;
    return filters;
}

function pendingClass(pending) {
    return Number(pending) > 0 ? 'text-warning fw-semibold' : 'text-success';
}

function renderDetail(result, params) {

    document.getElementById('detailTotalsRow').style.display = 'none';
    document.getElementById('detailCard').style.display = 'none';
    document.getElementById('detailNoDataWrap').style.display = 'none';
    document.getElementById('printDetailBtn').style.display = 'none';

    if (!result.status || !result.rows.length) {
        document.getElementById('detailNoDataWrap').style.display = 'block';
        return;
    }

    let g = result.grand_total;

    document.getElementById('detail-count').innerText = g.invoice_count;
    document.getElementById('detail-payable').innerText = '₹' + fmtMoney(g.payable);
    document.getElementById('detail-settled').innerText = '₹' + fmtMoney(g.settled);
    document.getElementById('detail-pending').innerText = '₹' + fmtMoney(g.pending);

    document.getElementById('detailTotalsRow').style.display = 'flex';

    let tbody = document.getElementById('detailTableBody');
    tbody.innerHTML = '';

    result.rows.forEach(row => {
        tbody.innerHTML += `
        <tr>
            <td>${escapeHtml(row.invoice_date_fmt)}</td>
            <td class="fw-semibold">${escapeHtml(row.invoice_no)}</td>
            <td>${escapeHtml(row.doctor_names) || '-'}</td>
            <td>${escapeHtml(row.patient_name) || '-'}</td>
            <td class="text-center"><span class="badge bg-info-subtle text-info">${escapeHtml(row.patient_payment_mode)}</span></td>
            <td>${escapeHtml(row.user_involved)}</td>
            <td class="text-end">${fmtMoney(row.total_payable)}</td>
            <td class="text-end">${fmtMoney(row.total_settled)}</td>
            <td class="text-end ${pendingClass(row.total_pending)}">${fmtMoney(row.total_pending)}</td>
            <td>${escapeHtml(row.settlement_nos)}</td>
            <td>${escapeHtml(row.settlement_dates)}</td>
            <td class="text-center">
                <a href="${row.print_url}" target="_blank" class="btn btn-sm btn-outline-primary" title="Print Invoice">
                    <i class="ri-printer-line"></i>
                </a>
            </td>
        </tr>
        `;
    });

    document.getElementById('detailCard').style.display = 'block';

    let printBtn = document.getElementById('printDetailBtn');
    printBtn.href = `/doctor-payable-non-cash-report/print-detail?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

function renderSummary(result, params) {

    document.getElementById('summaryTotalsRow').style.display = 'none';
    document.getElementById('summaryCard').style.display = 'none';
    document.getElementById('summaryNoDataWrap').style.display = 'none';
    document.getElementById('printSummaryBtn').style.display = 'none';

    if (!result.status || !result.rows.length) {
        document.getElementById('summaryNoDataWrap').style.display = 'block';
        return;
    }

    let g = result.grand_total;

    document.getElementById('summary-count').innerText = g.invoice_count;
    document.getElementById('summary-payable').innerText = '₹' + fmtMoney(g.payable);
    document.getElementById('summary-settled').innerText = '₹' + fmtMoney(g.settled);
    document.getElementById('summary-pending').innerText = '₹' + fmtMoney(g.pending);

    document.getElementById('summaryTotalsRow').style.display = 'flex';

    let tbody = document.getElementById('summaryTableBody');
    tbody.innerHTML = '';

    result.rows.forEach(row => {
        tbody.innerHTML += `
        <tr>
            <td>${escapeHtml(row.doctor_name)}</td>
            <td class="text-center">${row.invoice_count}</td>
            <td class="text-end">${fmtMoney(row.payable)}</td>
            <td class="text-end">${fmtMoney(row.settled)}</td>
            <td class="text-end ${pendingClass(row.pending)}">${fmtMoney(row.pending)}</td>
        </tr>
        `;
    });

    tbody.innerHTML += `
    <tr class="table-light fw-bold">
        <td class="text-end">Total</td>
        <td class="text-center">${g.invoice_count}</td>
        <td class="text-end">${fmtMoney(g.payable)}</td>
        <td class="text-end">${fmtMoney(g.settled)}</td>
        <td class="text-end">${fmtMoney(g.pending)}</td>
    </tr>
    `;

    document.getElementById('summaryCard').style.display = 'block';

    let printBtn = document.getElementById('printSummaryBtn');
    printBtn.href = `/doctor-payable-non-cash-report/print-summary?${params.toString()}`;
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

    const [detailResponse, summaryResponse] = await Promise.all([
        fetch(`/doctor-payable-non-cash-report/detail?${params.toString()}`),
        fetch(`/doctor-payable-non-cash-report/summary?${params.toString()}`),
    ]);

    const detailResult = await detailResponse.json();
    const summaryResult = await summaryResponse.json();

    if (mySeq !== requestSeq) return;

    renderDetail(detailResult, params);
    renderSummary(summaryResult, params);
}

document.getElementById('loadReportBtn').addEventListener('click', loadReport);

/* ==========================================================
   INIT (default: last 30 days)
========================================================== */

setFlatpickrValue('to_date-field', new Date().toISOString().substring(0, 10));
setFlatpickrValue('from_date-field', new Date(Date.now() - 29 * 24 * 60 * 60 * 1000).toISOString().substring(0, 10));
