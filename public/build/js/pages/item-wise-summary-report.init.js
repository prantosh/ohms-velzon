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

function printButtonHtml(row) {
    if (row.print_url) {
        return `<a href="${row.print_url}" target="_blank" class="btn btn-sm btn-outline-primary" title="Print Invoice"><i class="ri-printer-line"></i></a>`;
    }
    if (row.print_ajax_url) {
        return `<button type="button" class="btn btn-sm btn-outline-primary printInvoiceBtn" data-url="${row.print_ajax_url}" title="Print Invoice"><i class="ri-printer-line"></i></button>`;
    }
    return '-';
}

document.getElementById('detailTableBody')?.addEventListener('click', function (e) {
    const btn = e.target.closest('.printInvoiceBtn');
    if (!btn) return;

    const url = btn.getAttribute('data-url');

    fetch(url)
        .then(response => response.json())
        .then(result => {
            if (result && result.pdf_url) {
                window.open(result.pdf_url, '_blank');
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Could not generate invoice PDF.' });
            }
        })
        .catch(() => {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Could not generate invoice PDF.' });
        });
});

let summarySeq = 0;
let detailSeq = 0;

function periodFilters() {
    return {
        from_date: document.getElementById('from_date-field').value,
        to_date: document.getElementById('to_date-field').value,
    };
}

/* ==========================================================
   SUMMARY TAB (all items)
========================================================== */

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

    document.getElementById('summary-invoice_count').innerText = g.invoice_count;
    document.getElementById('summary-receivable').innerText = '₹' + fmtMoney(g.receivable);
    document.getElementById('summary-received_total').innerText = '₹' + fmtMoney(g.received_total);
    document.getElementById('summary-settled_amount').innerText = '₹' + fmtMoney(g.settled_amount);
    document.getElementById('summary-deposited').innerText = '₹' + fmtMoney(g.deposited);

    document.getElementById('summaryTotalsRow').style.display = 'flex';

    let tbody = document.getElementById('summaryTableBody');
    tbody.innerHTML = '';

    result.rows.forEach(row => {
        tbody.innerHTML += `
        <tr>
            <td>${escapeHtml(row.item_name)}</td>
            <td>${escapeHtml(row.item_code)}</td>
            <td class="text-center">${row.invoice_count}</td>
            <td class="text-end">${fmtMoney(row.receivable)}</td>
            <td class="text-end">${fmtMoney(row.received_cash)}</td>
            <td class="text-end">${fmtMoney(row.received_noncash)}</td>
            <td class="text-end">${fmtMoney(row.received_total)}</td>
            <td class="text-end">${fmtMoney(row.settled_amount)}</td>
            <td class="text-end">${fmtMoney(row.refund_cash)}</td>
            <td class="text-end">${fmtMoney(row.doctor_payment_cash)}</td>
            <td class="text-end fw-semibold">${fmtMoney(row.deposited)}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-primary viewDetailBtn" data-code="${escapeHtml(row.item_code)}" title="View Detail">
                    <i class="ri-eye-line"></i>
                </button>
            </td>
        </tr>
        `;
    });

    tbody.innerHTML += `
    <tr class="table-light fw-bold">
        <td colspan="2" class="text-end">Total</td>
        <td class="text-center">${g.invoice_count}</td>
        <td class="text-end">${fmtMoney(g.receivable)}</td>
        <td class="text-end">${fmtMoney(g.received_cash)}</td>
        <td class="text-end">${fmtMoney(g.received_noncash)}</td>
        <td class="text-end">${fmtMoney(g.received_total)}</td>
        <td class="text-end">${fmtMoney(g.settled_amount)}</td>
        <td class="text-end">${fmtMoney(g.refund_cash)}</td>
        <td class="text-end">${fmtMoney(g.doctor_payment_cash)}</td>
        <td class="text-end">${fmtMoney(g.deposited)}</td>
        <td></td>
    </tr>
    `;

    document.getElementById('summaryCard').style.display = 'block';

    let printBtn = document.getElementById('printSummaryBtn');
    printBtn.href = `/item-wise-summary-report/print-summary?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

async function loadSummary() {

    let filters = periodFilters();

    if (!filters.from_date || !filters.to_date) {
        Swal.fire({ icon: 'warning', title: 'Missing Filters', text: 'Please select both dates.' });
        return;
    }

    const mySeq = ++summarySeq;
    const params = new URLSearchParams(filters);

    const response = await fetch(`/item-wise-summary-report/summary?${params.toString()}`);
    const result = await response.json();

    if (mySeq !== summarySeq) return;

    renderSummary(result, params);
}

document.getElementById('loadReportBtn').addEventListener('click', loadSummary);

document.getElementById('summaryTableBody').addEventListener('click', function (e) {
    const btn = e.target.closest('.viewDetailBtn');
    if (!btn) return;

    document.getElementById('item_code-field').value = btn.getAttribute('data-code');

    let detailTabLink = document.querySelector('a[href="#detailTab"]');
    bootstrap.Tab.getOrCreateInstance(detailTabLink).show();

    loadDetail();
});

/* ==========================================================
   DETAIL TAB (single item)
========================================================== */

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

    document.getElementById('detail-invoice_count').innerText = g.invoice_count;
    document.getElementById('detail-amount').innerText = '₹' + fmtMoney(g.amount);
    document.getElementById('detail-settled_amount').innerText = '₹' + fmtMoney(g.settled_amount);
    document.getElementById('detailCardTitle').innerText = 'Invoice Detail — ' + (result.item_name ?? '-');

    document.getElementById('detailTotalsRow').style.display = 'flex';

    let tbody = document.getElementById('detailTableBody');
    tbody.innerHTML = '';

    result.rows.forEach(row => {
        tbody.innerHTML += `
        <tr>
            <td>${escapeHtml(row.invoice_date_fmt)}</td>
            <td class="fw-semibold">${escapeHtml(row.invoice_no)}</td>
            <td>${escapeHtml(row.patient_name) || '-'}</td>
            <td>${escapeHtml(row.sub_item_label) || '-'}</td>
            <td class="text-end">${fmtMoney(row.item_amount)}</td>
            <td>${escapeHtml(row.payment_mode)}</td>
            <td class="text-center">
                <span class="badge ${row.is_settled ? 'bg-success' : 'bg-warning text-dark'}">${row.is_settled ? 'Settled' : 'Pending'}</span>
            </td>
            <td class="text-center">${printButtonHtml(row)}</td>
        </tr>
        `;
    });

    document.getElementById('detailCard').style.display = 'block';

    let printBtn = document.getElementById('printDetailBtn');
    printBtn.href = `/item-wise-summary-report/print-detail?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

async function loadDetail() {

    let filters = Object.assign(periodFilters(), {
        item_code: document.getElementById('item_code-field').value,
    });

    if (!filters.item_code || !filters.from_date || !filters.to_date) {
        Swal.fire({ icon: 'warning', title: 'Missing Filters', text: 'Please select an item and both dates.' });
        return;
    }

    const mySeq = ++detailSeq;
    const params = new URLSearchParams(filters);

    const response = await fetch(`/item-wise-summary-report/detail?${params.toString()}`);
    const result = await response.json();

    if (mySeq !== detailSeq) return;

    renderDetail(result, params);
}

document.getElementById('loadDetailBtn').addEventListener('click', loadDetail);

/* ==========================================================
   INIT (default: last 30 days)
========================================================== */

setFlatpickrValue('to_date-field', new Date().toISOString().substring(0, 10));
setFlatpickrValue('from_date-field', new Date(Date.now() - 29 * 24 * 60 * 60 * 1000).toISOString().substring(0, 10));
