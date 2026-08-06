function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function successRate(sent, total) {
    if (!total) return '0%';
    return Math.round((sent / total) * 100) + '%';
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

    const response = await fetch(`/whatsapp-message-report/summary?${params.toString()}`);
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

    document.getElementById('grand-total_count').innerText = g.total_count;
    document.getElementById('grand-sent_count').innerText = g.sent_count;
    document.getElementById('grand-failed_count').innerText = g.failed_count;
    document.getElementById('grand-success_rate').innerText = successRate(g.sent_count, g.total_count);

    document.getElementById('summaryTotalsRow').style.display = 'flex';

    let tbody = document.getElementById('summaryTableBody');
    tbody.innerHTML = '';

    result.rows.forEach(row => {
        tbody.innerHTML += `
        <tr>
            <td class="fw-semibold">${escapeHtml(row.date_fmt)}</td>
            <td class="text-end">${row.total_count}</td>
            <td class="text-end text-success">${row.sent_count}</td>
            <td class="text-end ${row.failed_count > 0 ? 'text-danger fw-semibold' : ''}">${row.failed_count}</td>
            <td class="text-end">${row.type_invoice}</td>
            <td class="text-end">${row.type_test_report}</td>
            <td class="text-end">${row.type_appointment}</td>
            <td class="text-end">${row.type_otp}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-soft-primary view-day-btn" data-date="${row.log_date}">
                    <i class="ri-eye-line"></i> View
                </button>
            </td>
        </tr>
        `;
    });

    tbody.innerHTML += `
    <tr class="table-light fw-bold">
        <td>Total</td>
        <td class="text-end">${g.total_count}</td>
        <td class="text-end">${g.sent_count}</td>
        <td class="text-end">${g.failed_count}</td>
        <td class="text-end">${g.type_invoice}</td>
        <td class="text-end">${g.type_test_report}</td>
        <td class="text-end">${g.type_appointment}</td>
        <td class="text-end">${g.type_otp}</td>
        <td></td>
    </tr>
    `;

    document.getElementById('summaryCard').style.display = 'block';

    let printBtn = document.getElementById('printSummaryBtn');
    printBtn.href = `/whatsapp-message-report/print-summary?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

document.getElementById('loadSummaryBtn').addEventListener('click', loadSummary);

document.getElementById('summaryTableBody').addEventListener('click', function (e) {

    let btn = e.target.closest('.view-day-btn');
    if (!btn) return;

    // Jump to the Detail tab and load that specific day.
    document.querySelector('a[href="#detailTab"]').click();
    setFlatpickrValue('detail_date-field', btn.dataset.date);
    loadDetail();
});

/* ==========================================================
   DETAIL TAB
========================================================== */

let typeBadgeClass = {
    'Invoice': 'bg-primary',
    'Test Report': 'bg-info text-dark',
    'Appointment': 'bg-success',
    'OTP': 'bg-secondary',
};

let detailRequestSeq = 0;

async function loadDetail() {

    let date = document.getElementById('detail_date-field').value;

    if (!date) {
        Swal.fire({ icon: 'warning', title: 'Missing Date', text: 'Please select a date.' });
        return;
    }

    const mySeq = ++detailRequestSeq;

    const params = new URLSearchParams({ date: date });

    const response = await fetch(`/whatsapp-message-report/detail?${params.toString()}`);
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

    document.getElementById('detail-total_count').innerText = s.total_count;
    document.getElementById('detail-sent_count').innerText = s.sent_count;
    document.getElementById('detail-failed_count').innerText = s.failed_count;
    document.getElementById('detail-success_rate').innerText = successRate(s.sent_count, s.total_count);

    document.getElementById('detailTotalsRow').style.display = 'flex';

    let statusBadgeClass = {
        'SENT': 'bg-success',
        'FAILED': 'bg-danger',
    };

    let tbody = document.getElementById('detailTableBody');
    tbody.innerHTML = '';

    result.rows.forEach(row => {
        tbody.innerHTML += `
        <tr>
            <td>${escapeHtml(row.time_fmt)}</td>
            <td class="fw-semibold">${escapeHtml(row.invoice_no)}</td>
            <td>${escapeHtml(row.mobile_no)}</td>
            <td><span class="badge ${typeBadgeClass[row.type_label] ?? 'bg-secondary'}">${escapeHtml(row.type_label)}</span></td>
            <td><span class="badge ${statusBadgeClass[row.status] ?? 'bg-secondary'}">${escapeHtml(row.status)}</span></td>
            <td class="small" title="${escapeHtml(row.response_preview ?? '')}">${row.status === 'SENT' ? escapeHtml(row.message_id) || '-' : escapeHtml(row.response_preview) || '-'}</td>
        </tr>
        `;
    });

    document.getElementById('detailCard').style.display = 'block';

    let printBtn = document.getElementById('printDetailBtn');
    printBtn.href = `/whatsapp-message-report/print-detail?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

document.getElementById('loadDetailBtn').addEventListener('click', loadDetail);

/* ==========================================================
   INIT (default: last 7 days for summary, today for detail)
========================================================== */

document.getElementById('summary_to_date-field').value = new Date().toISOString().substring(0, 10);
document.getElementById('summary_from_date-field').value = new Date(Date.now() - 6 * 24 * 60 * 60 * 1000).toISOString().substring(0, 10);
document.getElementById('detail_date-field').value = new Date().toISOString().substring(0, 10);
