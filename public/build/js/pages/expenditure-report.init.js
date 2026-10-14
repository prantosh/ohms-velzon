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

function dmy(iso) {
    return iso ? iso.split('-').reverse().join('-') : '';
}

let requestSeq = 0;

function currentParams(extra) {

    return new URLSearchParams(Object.assign({
        from_date: document.getElementById('from_date-field').value,
        to_date: document.getElementById('to_date-field').value
    }, extra || {}));
}

function categoryParam() {

    let v = document.getElementById('category-field').value;

    return v === 'ALL' ? {} : { category_id: v };
}

/* ==========================================================
   SUMMARY
========================================================== */

async function loadReport() {

    let from = document.getElementById('from_date-field').value;
    let to = document.getElementById('to_date-field').value;

    if (!from || !to) {
        Swal.fire({ icon: 'warning', title: 'Select Period', text: 'Please select both dates.' });
        return;
    }

    if (to < from) {
        Swal.fire({ icon: 'warning', title: 'Check Dates', text: 'To date cannot be before From date.' });
        return;
    }

    const mySeq = ++requestSeq;

    document.getElementById('summaryCard').style.display = 'none';
    document.getElementById('detailCard').style.display = 'none';
    document.getElementById('noDataWrap').style.display = 'none';
    document.getElementById('printSummaryBtn').style.display = 'none';
    document.getElementById('printDetailBtn').style.display = 'none';

    const response = await fetch('/expenditure-report/summary?' + currentParams(categoryParam()).toString());
    const result = await response.json();

    if (mySeq !== requestSeq) return;

    if (!result.status || !result.rows.length) {
        document.getElementById('noDataWrap').style.display = 'block';
        return;
    }

    let rows = result.rows.map((r, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>${escapeHtml(r.category)}</td>
            <td class="text-end">${r.entries}</td>
            <td class="text-end">${fmtMoney(r.amount)}</td>
            <td class="text-end">${Number(r.share).toFixed(1)}%</td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-primary showDetailBtn"
                        data-category-id="${escapeHtml(r.category_id)}" data-category="${escapeHtml(r.category)}">
                    <i class="ri-list-check-2"></i> Detail
                </button>
            </td>
        </tr>`).join('');

    rows += `
        <tr class="table-light fw-bold">
            <td colspan="2" class="text-end">Total</td>
            <td class="text-end">${result.total_entries}</td>
            <td class="text-end">${fmtMoney(result.total_amount)}</td>
            <td class="text-end">100%</td>
            <td></td>
        </tr>`;

    document.getElementById('summaryBody').innerHTML = rows;
    document.getElementById('summaryTitle').innerText = 'Summary -- ' + dmy(result.from) + ' to ' + dmy(result.to);
    document.getElementById('summaryCard').style.display = 'block';

    let p = currentParams(categoryParam());

    let ps = new URLSearchParams(p); ps.set('type', 'summary');
    let pd = new URLSearchParams(p); pd.set('type', 'detail');

    let printSummary = document.getElementById('printSummaryBtn');
    printSummary.href = '/expenditure-report/print?' + ps.toString();
    printSummary.style.display = '';

    let printDetail = document.getElementById('printDetailBtn');
    printDetail.href = '/expenditure-report/print?' + pd.toString();
    printDetail.innerText = '';
    printDetail.innerHTML = '<i class="ri-printer-line"></i> ' + (document.getElementById('category-field').value === 'ALL' ? 'Detail PDF (all categories)' : 'Detail PDF');
    printDetail.style.display = '';
}

/* ==========================================================
   DETAIL (one category)
========================================================== */

document.addEventListener('click', async function (e) {

    let btn = e.target.closest('.showDetailBtn');

    if (!btn) return;

    let card = document.getElementById('detailCard');
    let body = document.getElementById('detailBody');

    document.getElementById('detailTitle').innerText = btn.dataset.category + ' -- ' + dmy(document.getElementById('from_date-field').value) + ' to ' + dmy(document.getElementById('to_date-field').value);
    body.innerHTML = '<div class="text-center text-muted py-4">Loading...</div>';
    card.style.display = 'block';
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });

    const params = currentParams({ category_id: btn.dataset.categoryId });

    try {

        const response = await fetch('/expenditure-report/detail?' + params.toString());
        const result = await response.json();

        if (!result.status || !result.groups.length) {
            body.innerHTML = '<div class="alert alert-info">No entries.</div>';
            return;
        }

        let g = result.groups[0];

        let pp = new URLSearchParams(params); pp.set('type', 'detail');

        let rows = g.rows.map((r, i) => `
            <tr>
                <td>${i + 1}</td>
                <td>${escapeHtml(r.date_fmt)}</td>
                <td>${escapeHtml(r.voucher_number)}</td>
                <td>${escapeHtml(r.bill_number ?? '-')}</td>
                <td>${escapeHtml(r.paid_to ?? '-')}</td>
                <td>${escapeHtml(r.payment_mode ?? '')}${r.cheque_number ? ' <span class="text-muted small">(' + escapeHtml(r.cheque_number) + ')</span>' : ''}</td>
                <td>${escapeHtml(r.spent_by ?? '-')}</td>
                <td>${escapeHtml(r.remarks ?? '')}</td>
                <td class="text-end">${fmtMoney(r.amount)}</td>
            </tr>`).join('');

        body.innerHTML = `
        <div class="d-flex justify-content-end mb-2">
            <a href="/expenditure-report/print?${pp.toString()}" target="_blank" class="btn btn-success btn-sm"><i class="ri-printer-line"></i> Print this detail (PDF)</a>
        </div>
        <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th width="50">Sl</th><th width="100">Date</th><th>Voucher</th><th>Bill No</th><th>Paid To</th>
                    <th>Mode</th><th>Expenditure By</th><th>Remarks</th><th class="text-end" width="130">Amount (&#8377;)</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
            <tfoot>
                <tr class="table-light fw-bold"><td colspan="8" class="text-end">Total (${g.entries} entries)</td><td class="text-end">${fmtMoney(g.amount)}</td></tr>
            </tfoot>
        </table>
        </div>`;

    } catch (err) {
        body.innerHTML = '<div class="alert alert-danger">Unable to load the detail.</div>';
    }
});

document.getElementById('hideDetailBtn').addEventListener('click', function () {
    document.getElementById('detailCard').style.display = 'none';
});

document.getElementById('loadBtn').addEventListener('click', loadReport);

/* ==========================================================
   INIT (default: the current month so far)
========================================================== */

(function () {

    let now = new Date();
    let pad = n => String(n).padStart(2, '0');

    let first = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-01';
    let today = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());

    let fromEl = document.getElementById('from_date-field');
    let toEl = document.getElementById('to_date-field');

    if (fromEl._flatpickr) { fromEl._flatpickr.setDate(first, false); } else { fromEl.value = first; }
    if (toEl._flatpickr) { toEl._flatpickr.setDate(today, false); } else { toEl.value = today; }

    loadReport();
})();
