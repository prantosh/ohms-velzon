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

function fmtQty(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

let requestSeq = 0;

function currentFilters() {
    return {
        from_date: document.getElementById('from_date-field').value,
        to_date: document.getElementById('to_date-field').value,
    };
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

    document.getElementById('detail-qty').innerText = fmtQty(g.qty);
    document.getElementById('detail-amount').innerText = '₹' + fmtMoney(g.amount);

    document.getElementById('detailTotalsRow').style.display = 'flex';

    let tbody = document.getElementById('detailTableBody');
    tbody.innerHTML = '';

    result.rows.forEach(row => {
        tbody.innerHTML += `
        <tr>
            <td class="fw-semibold">${escapeHtml(row.po_no)}</td>
            <td>${escapeHtml(row.po_date_fmt)}</td>
            <td>${escapeHtml(row.vendor_name) || '-'}</td>
            <td>${escapeHtml(row.item_code)}</td>
            <td>${escapeHtml(row.item_name)}</td>
            <td class="text-center">${escapeHtml(row.uom)}</td>
            <td class="text-end">${fmtQty(row.po_qty)}</td>
            <td class="text-end">${fmtMoney(row.unit_rate)}</td>
            <td class="text-end">${fmtQty(row.gst_percent)}</td>
            <td class="text-end">${fmtMoney(row.amount)}</td>
            <td class="text-end">${fmtQty(row.received_qty)}</td>
            <td class="text-center">${escapeHtml(row.status) || '-'}</td>
        </tr>
        `;
    });

    document.getElementById('detailCard').style.display = 'block';

    let printBtn = document.getElementById('printDetailBtn');
    printBtn.href = `/purchase-order-report/print-detail?${params.toString()}`;
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

    document.getElementById('summary-qty').innerText = fmtQty(g.qty);
    document.getElementById('summary-amount').innerText = '₹' + fmtMoney(g.amount);

    document.getElementById('summaryTotalsRow').style.display = 'flex';

    let tbody = document.getElementById('summaryTableBody');
    tbody.innerHTML = '';

    result.rows.forEach(row => {
        tbody.innerHTML += `
        <tr>
            <td>${escapeHtml(row.item_code)}</td>
            <td>${escapeHtml(row.item_name)}</td>
            <td class="text-center">${escapeHtml(row.uom)}</td>
            <td class="text-center">${row.po_count}</td>
            <td class="text-end">${fmtQty(row.qty)}</td>
            <td class="text-end">${fmtMoney(row.amount)}</td>
        </tr>
        `;
    });

    tbody.innerHTML += `
    <tr class="table-light fw-bold">
        <td colspan="4" class="text-end">Total</td>
        <td class="text-end">${fmtQty(g.qty)}</td>
        <td class="text-end">${fmtMoney(g.amount)}</td>
    </tr>
    `;

    document.getElementById('summaryCard').style.display = 'block';

    let printBtn = document.getElementById('printSummaryBtn');
    printBtn.href = `/purchase-order-report/print-summary?${params.toString()}`;
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
        fetch(`/purchase-order-report/detail?${params.toString()}`),
        fetch(`/purchase-order-report/summary?${params.toString()}`),
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
