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

// Both diagnostic and doctor-visit invoice print routes stream the PDF
// directly (no JSON wrapper -- see DoctorPayableByUserController::
// attachPrintInfo()'s comment), so a plain link is enough for either type.
function printButtonCell(row) {
    if (!row.print_url) {
        return '<td class="text-center text-muted">-</td>';
    }
    return `<td class="text-center">
        <a href="${escapeHtml(row.print_url)}" target="_blank" class="btn btn-sm btn-primary"
            title="Print Invoice">
            <i class="ri-printer-line"></i>
        </a>
    </td>`;
}

// Only settled payables carry a settlement number, so the payment slip
// (settlement voucher PDF) is only ever available on the settled table.
function voucherButtonCell(row) {
    if (!row.voucher_url) {
        return '<td class="text-center text-muted">-</td>';
    }
    return `<td class="text-center">
        <a href="${escapeHtml(row.voucher_url)}" target="_blank" class="btn btn-sm btn-success"
            title="Print Payment Slip">
            <i class="ri-file-list-3-line"></i>
        </a>
    </td>`;
}

let isAllUsersGlobal = false;

function userCell(row) {
    if (!isAllUsersGlobal) return '';
    if (row.collection_due) {
        return `<td><span class="text-danger fw-semibold">${escapeHtml(row.user_name)}</span></td>`;
    }
    return `<td>${escapeHtml(row.user_name)}</td>`;
}

async function loadReport() {

    let userId = document.getElementById('user_id-field').value;
    let range = document.getElementById('range-field').value;

    if (!userId) {
        Swal.fire({ icon: 'warning', title: 'Select User', text: 'Please select a user to load the dashboard.' });
        return;
    }

    isAllUsersGlobal = (userId === 'ALL');

    // --- toggle the User column on/off depending on single-user vs all-users ---
    document.querySelectorAll('.user-col').forEach(el => {
        el.style.display = isAllUsersGlobal ? '' : 'none';
    });

    document.getElementById('summaryRow').style.display = 'none';
    document.getElementById('dailySummaryCard').style.display = 'none';
    document.getElementById('dayDetailWrap').style.display = 'none';
    document.getElementById('noDataWrap').style.display = 'none';

    await loadDailySummary(userId, range);
}

/*
|--------------------------------------------------------------------------
| DAILY SUMMARY -- one row per date, settled + unsettled side by side
|--------------------------------------------------------------------------
*/

let dailySummaryRequestSeq = 0;

async function loadDailySummary(userId, range) {

    const seq = ++dailySummaryRequestSeq;

    const params = new URLSearchParams({ user_id: userId, range: range });

    const response = await fetch(`/doctor-payable-by-user/daily-summary?${params.toString()}`);
    const result = await response.json();

    if (seq !== dailySummaryRequestSeq) return;

    if (!result.status) {
        document.getElementById('noDataWrap').style.display = 'block';
        return;
    }

    let t = result.totals;

    document.getElementById('summary-settled_count').innerText = t.settled_count;
    document.getElementById('summary-settled_amount').innerText = '₹' + fmtMoney(t.settled_amount);
    document.getElementById('summary-unsettled_count').innerText = t.unsettled_count;
    document.getElementById('summary-unsettled_amount').innerText = '₹' + fmtMoney(t.unsettled_amount);
    document.getElementById('summary-settled_count_label').innerText = 'Settled Payables (' + result.range_label + ')';
    document.getElementById('summary-settled_amount_label').innerText = 'Settled Amount (' + result.range_label + ')';
    document.getElementById('summary-unsettled_count_label').innerText = 'Unsettled Payables (' + result.range_label + ')';
    document.getElementById('summary-unsettled_amount_label').innerText = 'Unsettled Amount (' + result.range_label + ')';
    document.getElementById('dailySummaryCardTitle').innerText = 'Doctor Payables — Daily Summary (' + result.range_label + ')';

    document.getElementById('summaryRow').style.display = 'flex';

    let tbody = document.getElementById('dailySummaryTableBody');
    tbody.innerHTML = '';

    if (result.data.length) {
        result.data.forEach(row => {
            tbody.innerHTML += `
            <tr>
                <td class="fw-semibold">${escapeHtml(row.date_fmt)}</td>
                <td>${row.settled_count}</td>
                <td class="text-end text-success fw-semibold">${fmtMoney(row.settled_amount)}</td>
                <td>${row.unsettled_count}</td>
                <td class="text-end text-danger fw-semibold">${fmtMoney(row.unsettled_amount)}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-primary show-day-detail-btn"
                        data-date="${escapeHtml(row.date)}" data-date-fmt="${escapeHtml(row.date_fmt)}">
                        <i class="ri-eye-line"></i>
                        Show Detail
                    </button>
                </td>
            </tr>
            `;
        });
        document.getElementById('noDailySummaryWrap').style.display = 'none';
    } else {
        document.getElementById('noDailySummaryWrap').style.display = 'block';
    }

    let printSummaryBtn = document.getElementById('printDailySummaryBtn');
    printSummaryBtn.href = `/doctor-payable-by-user/print-daily-summary?${params.toString()}`;

    document.getElementById('dailySummaryCard').style.display = 'block';
}

/*
|--------------------------------------------------------------------------
| DAY DETAIL -- both Settled and Unsettled for one date, plus a total
|--------------------------------------------------------------------------
*/

document.getElementById('dailySummaryTableBody').addEventListener('click', function (e) {

    let btn = e.target.closest('.show-day-detail-btn');
    if (!btn) return;

    loadDayDetail(btn.dataset.date, btn.dataset.dateFmt);
});

async function loadDayDetail(date, dateFmt) {

    let userId = document.getElementById('user_id-field').value;
    let range = document.getElementById('range-field').value;

    const params = new URLSearchParams({ user_id: userId, range: range, date: date });

    const response = await fetch(`/doctor-payable-by-user/list?${params.toString()}`);
    const result = await response.json();

    if (!result.status) return;

    document.getElementById('dayDetailTitle').innerText = 'Doctor Payables Detail — ' + dateFmt;

    let s = result.summary;

    document.getElementById('dayDetailTotals').innerHTML = `
        <div class="col-md-3">
            <small class="text-muted d-block">Settled Payables</small>
            <span class="fw-semibold text-success">${s.settled_count} (₹${fmtMoney(s.settled_amount)})</span>
        </div>
        <div class="col-md-3">
            <small class="text-muted d-block">Unsettled Payables</small>
            <span class="fw-semibold text-danger">${s.pending_count} (₹${fmtMoney(s.pending_balance_amount)})</span>
        </div>
        <div class="col-md-3">
            <small class="text-muted d-block">Total (Settled + Unsettled)</small>
            <span class="fw-bold">₹${fmtMoney(s.settled_amount + s.pending_balance_amount)}</span>
        </div>
    `;

    // --- Settled table ---
    let settledTbody = document.getElementById('settledTableBody');
    settledTbody.innerHTML = '';

    if (result.settled.length) {
        result.settled.forEach(row => {
            settledTbody.innerHTML += `
            <tr>
                <td class="fw-semibold">${escapeHtml(row.payable_no)}</td>
                <td>${escapeHtml(row.invoice_no)}</td>
                <td>${escapeHtml(row.category)}</td>
                ${userCell(row)}
                <td>${escapeHtml(row.doctor_name)}</td>
                <td>${escapeHtml(row.patient_name)}</td>
                <td>${escapeHtml(row.item_description)}</td>
                <td class="text-end">${fmtMoney(row.payable_amount)}</td>
                <td class="text-end text-success fw-semibold">${fmtMoney(row.paid_amount)}</td>
                <td>${escapeHtml(row.last_settlement_no)}</td>
                <td>${escapeHtml(row.last_settlement_date_fmt)}</td>
                <td class="text-center">${escapeHtml(row.settlement_count)}</td>
                ${printButtonCell(row)}
                ${voucherButtonCell(row)}
            </tr>
            `;
        });
        document.getElementById('noSettledWrap').style.display = 'none';
    } else {
        document.getElementById('noSettledWrap').style.display = 'block';
    }

    // --- Unsettled/Pending table ---
    let pendingTbody = document.getElementById('pendingTableBody');
    pendingTbody.innerHTML = '';

    if (result.pending.length) {
        result.pending.forEach(row => {
            pendingTbody.innerHTML += `
            <tr>
                <td class="fw-semibold">${escapeHtml(row.payable_no)}</td>
                <td>${escapeHtml(row.invoice_no)}</td>
                <td>${escapeHtml(row.category)}</td>
                ${userCell(row)}
                <td>${escapeHtml(row.doctor_name)}</td>
                <td>${escapeHtml(row.patient_name)}</td>
                <td>${escapeHtml(row.item_description)}</td>
                <td class="text-end">${fmtMoney(row.gross_amount)}</td>
                <td class="text-end">${fmtMoney(row.payable_amount)}</td>
                <td class="text-end">${fmtMoney(row.paid_amount)}</td>
                <td class="text-end text-danger fw-semibold">${fmtMoney(row.balance_amount)}</td>
                <td><span class="badge ${row.payment_status === 'APPROVED' ? 'bg-info text-dark' : 'bg-warning text-dark'}">${escapeHtml(row.payment_status)}</span></td>
                <td>${escapeHtml(row.created_at_fmt)}</td>
                ${printButtonCell(row)}
            </tr>
            `;
        });
        document.getElementById('noPendingWrap').style.display = 'none';
    } else {
        document.getElementById('noPendingWrap').style.display = 'block';
    }

    let printDayBtn = document.getElementById('printDayDetailBtn');
    printDayBtn.href = `/doctor-payable-by-user/print?${params.toString()}`;

    document.getElementById('dailySummaryCard').style.display = 'none';
    document.getElementById('dayDetailWrap').style.display = 'block';
}

document.getElementById('backToSummaryBtn').addEventListener('click', function () {
    document.getElementById('dayDetailWrap').style.display = 'none';
    document.getElementById('dailySummaryCard').style.display = 'block';
});

document.getElementById('loadReportBtn').addEventListener('click', loadReport);

document.getElementById('range-field').addEventListener('change', function () {
    if (document.getElementById('user_id-field').value) {
        loadReport();
    }
});
