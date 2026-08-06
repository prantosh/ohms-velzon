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

let requestSeq = 0;

function currentFilters() {
    return {
        item_code: document.getElementById('item_code-field').value,
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

    document.getElementById('detail-invoice_count').innerText = g.invoice_count;
    document.getElementById('detail-amount').innerText = '₹' + fmtMoney(g.amount);
    document.getElementById('detail-item_name').innerText = result.item_name ?? '-';

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
            <td><span class="badge ${statusBadgeClass[row.payment_status] ?? 'bg-secondary'}">${escapeHtml(row.payment_status)}</span></td>
            <td class="text-center">${printButtonHtml(row)}</td>
        </tr>
        `;
    });

    document.getElementById('detailCard').style.display = 'block';

    let printBtn = document.getElementById('printDetailBtn');
    printBtn.href = `/item-wise-report/print-detail?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

function renderSummary(result, params) {

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

async function loadReport() {

    let filters = currentFilters();

    if (!filters.item_code || !filters.from_date || !filters.to_date) {
        Swal.fire({ icon: 'warning', title: 'Missing Filters', text: 'Please select an item and both dates.' });
        return;
    }

    const mySeq = ++requestSeq;

    const params = new URLSearchParams(filters);

    const [detailResponse, summaryResponse] = await Promise.all([
        fetch(`/item-wise-report/detail?${params.toString()}`),
        fetch(`/item-wise-report/summary?${params.toString()}`),
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
