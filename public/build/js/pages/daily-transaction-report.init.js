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

/* ==========================================================
   SUMMARY TAB
========================================================== */

let summaryRequestSeq = 0;

async function loadSummary() {

    let fromDate = document.getElementById('summary_from_date-field').value;
    let toDate = document.getElementById('summary_to_date-field').value;

    if (!fromDate || !toDate) {
        Swal.fire({ icon: 'warning', title: 'Missing Dates', text: 'Please select both From Date and To Date.' });
        return;
    }

    const mySeq = ++summaryRequestSeq;

    const params = new URLSearchParams({ from_date: fromDate, to_date: toDate });

    const response = await fetch(`/daily-transaction-report/summary?${params.toString()}`);
    const result = await response.json();

    if (mySeq !== summaryRequestSeq) return;

    document.getElementById('summaryTotalsRow').style.display = 'none';
    document.getElementById('summaryCard').style.display = 'none';
    document.getElementById('summaryNoDataWrap').style.display = 'none';
    document.getElementById('printSummaryBtn').style.display = 'none';

    if (!result.status || !result.rows.length) {
        document.getElementById('summaryNoDataWrap').style.display = 'block';
        return;
    }

    let g = result.grand_total;

    document.getElementById('grand-collection_total').innerText = '₹' + fmtMoney(g.collection_total);
    document.getElementById('grand-refund_total').innerText = '₹' + fmtMoney(g.refund_total);
    document.getElementById('grand-payment_total').innerText = '₹' + fmtMoney(g.payment_total);
    document.getElementById('grand-net_total').innerText = '₹' + fmtMoney(g.net_total);

    document.getElementById('summaryTotalsRow').style.display = 'flex';

    let tbody = document.getElementById('summaryTableBody');
    tbody.innerHTML = '';

    result.rows.forEach(row => {
        tbody.innerHTML += `
        <tr>
            <td class="fw-semibold">${escapeHtml(row.date_fmt)}</td>
            <td class="text-end">${fmtMoney(row.collection_cash)}</td>
            <td class="text-end">${fmtMoney(row.collection_noncash)}</td>
            <td class="text-end fw-semibold">${fmtMoney(row.collection_total)}</td>
            <td class="text-end">${fmtMoney(row.refund_cash)}</td>
            <td class="text-end">${fmtMoney(row.refund_noncash)}</td>
            <td class="text-end fw-semibold">${fmtMoney(row.refund_total)}</td>
            <td class="text-end">${fmtMoney(row.payment_cash)}</td>
            <td class="text-end">${fmtMoney(row.payment_noncash)}</td>
            <td class="text-end fw-semibold">${fmtMoney(row.payment_total)}</td>
            <td class="text-end ${row.net_total >= 0 ? 'text-success' : 'text-danger'} fw-semibold">${fmtMoney(row.net_total)}</td>
        </tr>
        `;
    });

    tbody.innerHTML += `
    <tr class="table-light fw-bold">
        <td>Total</td>
        <td class="text-end">${fmtMoney(g.collection_cash)}</td>
        <td class="text-end">${fmtMoney(g.collection_noncash)}</td>
        <td class="text-end">${fmtMoney(g.collection_total)}</td>
        <td class="text-end">${fmtMoney(g.refund_cash)}</td>
        <td class="text-end">${fmtMoney(g.refund_noncash)}</td>
        <td class="text-end">${fmtMoney(g.refund_total)}</td>
        <td class="text-end">${fmtMoney(g.payment_cash)}</td>
        <td class="text-end">${fmtMoney(g.payment_noncash)}</td>
        <td class="text-end">${fmtMoney(g.payment_total)}</td>
        <td class="text-end">${fmtMoney(g.net_total)}</td>
    </tr>
    `;

    document.getElementById('summaryCard').style.display = 'block';

    let printBtn = document.getElementById('printSummaryBtn');
    printBtn.href = `/daily-transaction-report/print-summary?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

document.getElementById('loadSummaryBtn').addEventListener('click', loadSummary);

/* ==========================================================
   DETAIL TAB
========================================================== */

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

let detailRequestSeq = 0;

async function loadDetail() {

    let date = document.getElementById('detail_date-field').value;
    let paymentMode = document.getElementById('detail_payment_mode-field').value;

    if (!date) {
        Swal.fire({ icon: 'warning', title: 'Missing Date', text: 'Please select a date.' });
        return;
    }

    const mySeq = ++detailRequestSeq;

    const params = new URLSearchParams({ date: date, payment_mode: paymentMode });

    const response = await fetch(`/daily-transaction-report/detail?${params.toString()}`);
    const result = await response.json();

    if (mySeq !== detailRequestSeq) return;

    document.getElementById('detailTotalsRow').style.display = 'none';
    document.getElementById('detailCard').style.display = 'none';
    document.getElementById('detailNoDataWrap').style.display = 'none';
    document.getElementById('printDetailBtn').style.display = 'none';

    if (!result.status || !result.rows.length) {
        document.getElementById('detailNoDataWrap').style.display = 'block';
        return;
    }

    let s = result.summary;

    document.getElementById('detail-total_records').innerText = s.total_records;
    document.getElementById('detail-total_collection').innerText = '₹' + fmtMoney(s.total_collection);
    document.getElementById('detail-total_refund_payment').innerText = '₹' + fmtMoney(s.total_refund + s.total_payment);
    document.getElementById('detail-net_total').innerText = '₹' + fmtMoney(s.net_total);

    document.getElementById('detailTotalsRow').style.display = 'flex';

    let typeBadgeClass = {
        'Collection': 'bg-success',
        'Refund': 'bg-danger',
        'Payment': 'bg-warning text-dark',
    };

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
            <td>${escapeHtml(row.time_fmt)}</td>
            <td class="fw-semibold">${escapeHtml(row.transaction_no)}</td>
            <td><span class="badge ${typeBadgeClass[row.type_label] ?? 'bg-secondary'}">${escapeHtml(row.type_label)}</span></td>
            <td>${escapeHtml(row.patient_name) || '-'}</td>
            <td>${escapeHtml(row.doctor_name) || '-'}</td>
            <td>${escapeHtml(row.invoice_reference) || '-'}</td>
            <td class="text-end">${row.rate !== null ? fmtMoney(row.rate) : '-'}</td>
            <td class="text-end">${row.discount !== null ? fmtMoney(row.discount) : '-'}</td>
            <td class="text-end">${row.billed_amount !== null ? fmtMoney(row.billed_amount) : '-'}</td>
            <td class="text-end ${row.received_total >= 0 ? 'text-success' : 'text-danger'}">${fmtMoney(row.received_total)}</td>
            <td>${row.payment_status !== '-' ? `<span class="badge ${statusBadgeClass[row.payment_status] ?? 'bg-secondary'}">${escapeHtml(row.payment_status)}</span>` : '-'}</td>
            <td>${row.is_cash ? 'Cash' : escapeHtml(row.payment_mode)}</td>
            <td>${escapeHtml(row.operator_name) || '-'}</td>
            <td>${escapeHtml(row.remarks) || '-'}</td>
            <td class="text-center">${printButtonHtml(row)}</td>
        </tr>
        `;
    });

    document.getElementById('detailCard').style.display = 'block';

    let printBtn = document.getElementById('printDetailBtn');
    printBtn.href = `/daily-transaction-report/print-detail?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

document.getElementById('loadDetailBtn').addEventListener('click', loadDetail);

/* ==========================================================
   INIT (default: last 7 days for summary, today for detail)
========================================================== */

document.getElementById('summary_to_date-field').value = new Date().toISOString().substring(0, 10);
document.getElementById('summary_from_date-field').value = new Date(Date.now() - 6 * 24 * 60 * 60 * 1000).toISOString().substring(0, 10);
document.getElementById('detail_date-field').value = new Date().toISOString().substring(0, 10);
