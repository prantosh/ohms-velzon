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

function periodFilters() {
    return {
        from_date: document.getElementById('from_date-field').value,
        to_date: document.getElementById('to_date-field').value,
    };
}

function itemFilters() {
    return Object.assign(periodFilters(), {
        item_code: document.getElementById('item_code-field').value,
    });
}

/* ==========================================================
   TAB 1: ALL ITEMS SUMMARY
========================================================== */

let allItemsSeq = 0;

function renderAllItemsSummary(result, params) {

    document.getElementById('allItemsTotalsRow').style.display = 'none';
    document.getElementById('allItemsSummaryCard').style.display = 'none';
    document.getElementById('allItemsNoDataWrap').style.display = 'none';
    document.getElementById('printAllItemsSummaryBtn').style.display = 'none';

    if (!result.status || !result.rows.length) {
        document.getElementById('allItemsNoDataWrap').style.display = 'block';
        return;
    }

    let g = result.grand_total;

    document.getElementById('allitems-invoice_count').innerText = g.invoice_count;
    document.getElementById('allitems-receivable').innerText = '₹' + fmtMoney(g.receivable);
    document.getElementById('allitems-received_total').innerText = '₹' + fmtMoney(g.received_total);
    document.getElementById('allitems-settled_amount').innerText = '₹' + fmtMoney(g.settled_amount);
    document.getElementById('allitems-deposited').innerText = '₹' + fmtMoney(g.deposited);

    document.getElementById('allItemsTotalsRow').style.display = 'flex';

    let tbody = document.getElementById('allItemsSummaryTableBody');
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

    document.getElementById('allItemsSummaryCard').style.display = 'block';

    let printBtn = document.getElementById('printAllItemsSummaryBtn');
    printBtn.href = `/item-wise-report/print-all-items-summary?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

async function loadAllItemsSummary() {

    let filters = periodFilters();

    if (!filters.from_date || !filters.to_date) {
        Swal.fire({ icon: 'warning', title: 'Missing Filters', text: 'Please select both dates.' });
        return;
    }

    const mySeq = ++allItemsSeq;
    const params = new URLSearchParams(filters);

    const response = await fetch(`/item-wise-report/all-items-summary?${params.toString()}`);
    const result = await response.json();

    if (mySeq !== allItemsSeq) return;

    renderAllItemsSummary(result, params);
}

document.getElementById('loadReportBtn').addEventListener('click', loadAllItemsSummary);

document.getElementById('allItemsSummaryTableBody').addEventListener('click', function (e) {
    const btn = e.target.closest('.viewDetailBtn');
    if (!btn) return;

    document.getElementById('item_code-field').value = btn.getAttribute('data-code');

    let detailTabLink = document.querySelector('a[href="#detailTab"]');
    bootstrap.Tab.getOrCreateInstance(detailTabLink).show();

    loadItemViews();
});

/* ==========================================================
   TABS 2 & 3: ITEM DETAIL + ITEM SUMMARY (loaded together --
   both need the same item + date range)
========================================================== */

let itemViewsSeq = 0;

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
    document.getElementById('detail-item_name').innerText = result.item_name ?? '-';
    document.getElementById('detailCardTitle').innerText = 'Invoice Detail — ' + (result.item_name ?? '-');

    document.getElementById('detailTotalsRow').style.display = 'flex';

    let statusBadgeClass = {
        'Partial': 'bg-warning text-dark',
        'Full': 'bg-success',
        'Final': 'bg-info text-dark',
    };

    let tbody = document.getElementById('detailTableBody');
    tbody.innerHTML = '';

    result.rows.forEach(row => {
        tbody.innerHTML += `
        <tr>
            <td class="fw-semibold">${escapeHtml(row.invoice_no)}</td>
            <td>${escapeHtml(row.invoice_date_fmt)}</td>
            <td>${escapeHtml(row.patient_name) || '-'}</td>
            <td>${escapeHtml(row.sub_item_label) || '-'}</td>
            <td class="text-end">${fmtMoney(row.item_amount)}</td>
            <td>${escapeHtml(row.payment_mode)}</td>
            <td><span class="badge ${statusBadgeClass[row.payment_status] ?? 'bg-secondary'}">${escapeHtml(row.payment_status)}</span></td>
            <td class="text-center">
                <span class="badge ${row.is_settled ? 'bg-success' : 'bg-warning text-dark'}">${row.is_settled ? 'Settled' : 'Pending'}</span>
            </td>
            <td class="text-center">${printButtonHtml(row)}</td>
        </tr>
        `;
    });

    document.getElementById('detailCard').style.display = 'block';

    let printBtn = document.getElementById('printDetailBtn');
    printBtn.href = `/item-wise-report/print-detail?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

function renderItemSummary(result, params) {

    document.getElementById('summaryTotalsRow').style.display = 'none';
    document.getElementById('summaryByDateCard').style.display = 'none';
    document.getElementById('summaryBySubItemCard').style.display = 'none';
    document.getElementById('summaryNoDataWrap').style.display = 'none';
    document.getElementById('printSummaryBtn').style.display = 'none';

    if (!result.status || !result.by_date.length) {
        document.getElementById('summaryNoDataWrap').style.display = 'block';
        return;
    }

    let g = result.grand_total;

    document.getElementById('summary-invoice_count').innerText = g.invoice_count;
    document.getElementById('summary-amount').innerText = '₹' + fmtMoney(g.amount);

    document.getElementById('summaryTotalsRow').style.display = 'flex';

    let byDateBody = document.getElementById('summaryByDateTableBody');
    byDateBody.innerHTML = '';

    result.by_date.forEach(row => {
        byDateBody.innerHTML += `
        <tr>
            <td>${escapeHtml(row.date_fmt)}</td>
            <td class="text-center">${row.invoice_count}</td>
            <td class="text-end">${fmtMoney(row.amount)}</td>
        </tr>
        `;
    });

    byDateBody.innerHTML += `
    <tr class="table-light fw-bold">
        <td>Total</td>
        <td class="text-center">${g.invoice_count}</td>
        <td class="text-end">${fmtMoney(g.amount)}</td>
    </tr>
    `;

    let bySubItemBody = document.getElementById('summaryBySubItemTableBody');
    bySubItemBody.innerHTML = '';

    result.by_sub_item.forEach(row => {
        bySubItemBody.innerHTML += `
        <tr>
            <td>${escapeHtml(row.label)}</td>
            <td class="text-center">${row.invoice_count}</td>
            <td class="text-end">${fmtMoney(row.amount)}</td>
        </tr>
        `;
    });

    document.getElementById('summaryByDateCard').style.display = 'block';
    document.getElementById('summaryBySubItemCard').style.display = 'block';

    let printBtn = document.getElementById('printSummaryBtn');
    printBtn.href = `/item-wise-report/print-summary?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

async function loadItemViews() {

    let filters = itemFilters();

    if (!filters.item_code || !filters.from_date || !filters.to_date) {
        Swal.fire({ icon: 'warning', title: 'Missing Filters', text: 'Please select an item and both dates.' });
        return;
    }

    const mySeq = ++itemViewsSeq;

    const params = new URLSearchParams(filters);

    const [detailResponse, summaryResponse] = await Promise.all([
        fetch(`/item-wise-report/detail?${params.toString()}`),
        fetch(`/item-wise-report/summary?${params.toString()}`),
    ]);

    const detailResult = await detailResponse.json();
    const summaryResult = await summaryResponse.json();

    if (mySeq !== itemViewsSeq) return;

    renderDetail(detailResult, params);
    renderItemSummary(summaryResult, params);
}

document.getElementById('loadItemBtn').addEventListener('click', loadItemViews);

/* ==========================================================
   INIT (default: last 30 days)
========================================================== */

setFlatpickrValue('to_date-field', new Date().toISOString().substring(0, 10));
setFlatpickrValue('from_date-field', new Date(Date.now() - 29 * 24 * 60 * 60 * 1000).toISOString().substring(0, 10));
