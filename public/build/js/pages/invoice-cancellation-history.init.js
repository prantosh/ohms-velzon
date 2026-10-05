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

let currentFromDate = null;
let currentToDate = null;
let currentUserId = 'ALL';

async function loadCancellationSummary() {

    let fromDate = document.getElementById('from_date-field').value;
    let toDate = document.getElementById('to_date-field').value;
    let userId = document.getElementById('user_id-field').value;

    if (!fromDate || !toDate) {
        Swal.fire({ icon: 'warning', title: 'Select Duration', text: 'Both From Date and To Date are required.' });
        return;
    }

    currentFromDate = fromDate;
    currentToDate = toDate;
    currentUserId = userId;

    const params = new URLSearchParams({ from_date: fromDate, to_date: toDate, user_id: userId });

    const response = await fetch(`/invoice-cancellation-history/list?${params.toString()}`);
    const result = await response.json();

    document.getElementById('listCard').style.display = 'none';
    document.getElementById('noDataWrap').style.display = 'none';

    if (!result.status || !result.data.length) {
        document.getElementById('noDataWrap').style.display = 'block';
        return;
    }

    let tbody = document.getElementById('summaryTableBody');
    tbody.innerHTML = '';

    result.data.forEach(row => {

        tbody.innerHTML += `
        <tr>
            <td class="fw-semibold">${escapeHtml(row.date_fmt)}</td>
            <td class="text-end">${row.cancelled_count}</td>
            <td class="text-end text-danger">${fmtMoney(row.total_amount)}</td>
            <td class="text-end text-danger">${fmtMoney(row.paid_amount)}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-soft-primary view-detail-btn"
                        data-date="${row.date}" data-date-fmt="${escapeHtml(row.date_fmt)}">
                    <i class="ri-eye-line"></i>
                </button>
            </td>
        </tr>
        `;
    });

    document.getElementById('total-cancelled_count').innerText = result.summary.cancelled_count;
    document.getElementById('total-total_amount').innerText = '₹' + fmtMoney(result.summary.total_amount);
    document.getElementById('total-paid_amount').innerText = '₹' + fmtMoney(result.summary.paid_amount);

    document.getElementById('listCard').style.display = 'block';
}

async function loadCancellationDetail(date, dateFmt) {

    document.getElementById('detailModalTitle').innerText = `Cancellations on ${dateFmt}`;

    const params = new URLSearchParams({ date, user_id: currentUserId });

    const response = await fetch(`/invoice-cancellation-history/detail?${params.toString()}`);
    const result = await response.json();

    new bootstrap.Modal(document.getElementById('detailModal')).show();

    if (!result.status) return;

    document.getElementById('detail-cancelled_count').innerText = result.summary.cancelled_count;
    document.getElementById('detail-total_amount').innerText = '₹' + fmtMoney(result.summary.total_amount);
    document.getElementById('detail-paid_amount').innerText = '₹' + fmtMoney(result.summary.paid_amount);

    let tbody = document.getElementById('detailTableBody');
    tbody.innerHTML = '';

    if (result.data.length) {

        result.data.forEach(row => {
            tbody.innerHTML += `
            <tr>
                <td class="fw-semibold">${escapeHtml(row.invoice_no)}</td>
                <td>${escapeHtml(row.invoice_type_label)}</td>
                <td>${escapeHtml(row.patient_name)}</td>
                <td>${escapeHtml(row.patient_mobile_no)}</td>
                <td>${escapeHtml(row.invoice_date_fmt)}</td>
                <td class="text-end">${fmtMoney(row.total_amount)}</td>
                <td class="text-end">${fmtMoney(row.paid_amount)}</td>
                <td>${escapeHtml(row.cancelled_by_name)}</td>
                <td>${escapeHtml(row.cancelled_at_fmt)}</td>
                <td>${escapeHtml(row.approved_by_name ?? '-')}</td>
                <td>${escapeHtml(row.cancellation_remarks ?? '-')}</td>
            </tr>
            `;
        });
        document.getElementById('detailNoDataWrap').style.display = 'none';

    } else {
        document.getElementById('detailNoDataWrap').style.display = 'block';
    }
}

document.getElementById('loadReportBtn').addEventListener('click', loadCancellationSummary);

document.getElementById('summaryTableBody').addEventListener('click', function (e) {

    let btn = e.target.closest('.view-detail-btn');
    if (!btn) return;

    loadCancellationDetail(btn.dataset.date, btn.dataset.dateFmt);
});

document.addEventListener('DOMContentLoaded', function () {

    let today = new Date();
    let firstOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

    setFlatpickrValue('from_date-field', firstOfMonth.toISOString().substring(0, 10));
    setFlatpickrValue('to_date-field', today.toISOString().substring(0, 10));
});
