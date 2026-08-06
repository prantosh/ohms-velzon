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

async function loadDetail() {

    let userId = document.getElementById('user_id-field').value;
    let date = document.getElementById('date-field').value;

    if (!userId || !date) {
        Swal.fire({ icon: 'warning', title: 'Select User and Date', text: 'Both fields are required to load the report.' });
        return;
    }

    const params = new URLSearchParams({ user_id: userId, date: date });

    const response = await fetch(`/cash-submission-report/list?${params.toString()}`);
    const result = await response.json();

    document.getElementById('summaryRow').style.display = 'none';
    document.getElementById('detailRow').style.display = 'none';
    document.getElementById('listCard').style.display = 'none';
    document.getElementById('noDataWrap').style.display = 'none';
    document.getElementById('printDetailBtn').style.display = 'none';

    let hasActivity = result.status && (result.ledger.length || Number(result.summary?.non_cash_collected ?? 0) !== 0);

    if (!hasActivity) {
        document.getElementById('noDataWrap').style.display = 'block';
        return;
    }

    let s = result.summary;

    document.getElementById('summary-cash_collected').innerText = '₹' + fmtMoney(s.cash_collected);
    document.getElementById('summary-non_cash_collected').innerText = '₹' + fmtMoney(s.non_cash_collected);
    document.getElementById('summary-cash_refunded').innerText = '₹' + fmtMoney(s.cash_refunded);
    document.getElementById('summary-cash_paid_to_doctors').innerText = '₹' + fmtMoney(s.cash_paid_to_doctors);
    document.getElementById('summary-net_cash_to_deposit').innerText = '₹' + fmtMoney(s.net_cash_to_deposit);

    document.getElementById('summaryRow').style.display = 'flex';
    document.getElementById('detailRow').style.display = 'flex';

    // --- Doctor payment detail table ---
    let doctorTbody = document.getElementById('doctorPaymentTableBody');
    doctorTbody.innerHTML = '';

    if (result.doctor_payments.length) {
        result.doctor_payments.forEach(row => {
            doctorTbody.innerHTML += `
            <tr>
                <td class="fw-semibold">${escapeHtml(row.invoice_no)}</td>
                <td>${escapeHtml(row.doctor_name)}</td>
                <td>${escapeHtml(row.settlement_no)}</td>
                <td>${escapeHtml(row.settlement_time)}</td>
                <td class="text-end text-danger">${fmtMoney(row.amount)}</td>
            </tr>
            `;
        });
        document.getElementById('noDoctorPaymentWrap').style.display = 'none';
    } else {
        document.getElementById('noDoctorPaymentWrap').style.display = 'block';
    }

    // --- Group-wise breakdown table ---
    let groupTbody = document.getElementById('groupBreakdownTableBody');
    groupTbody.innerHTML = '';

    result.group_breakdown.forEach(row => {
        let amountClass = row.amount >= 0 ? '' : 'text-danger';
        groupTbody.innerHTML += `
        <tr>
            <td>${escapeHtml(row.group_name)}</td>
            <td class="text-end ${amountClass}">${fmtMoney(row.amount)}</td>
        </tr>
        `;
    });

    document.getElementById('groupBreakdownTotal').innerText = '₹' + fmtMoney(s.net_cash_to_deposit);

    let tbody = document.getElementById('listTableBody');
    tbody.innerHTML = '';

    let typeBadgeClass = {
        'Collection': 'bg-success',
        'Refund': 'bg-danger',
        'Doctor Payment': 'bg-warning text-dark',
    };

    result.ledger.forEach(row => {

        let amountClass = row.amount >= 0 ? 'text-success' : 'text-danger';

        tbody.innerHTML += `
        <tr>
            <td class="fw-semibold">${escapeHtml(row.invoice_no)}</td>
            <td>${escapeHtml(row.category)}</td>
            <td>${escapeHtml(row.transaction_no)}</td>
            <td>${escapeHtml(row.transaction_to)}</td>
            <td>${escapeHtml(row.invoice_date_fmt)}</td>
            <td>${escapeHtml(row.time_fmt)}</td>
            <td class="text-end ${amountClass}">${fmtMoney(row.amount)}</td>
            <td><span class="badge ${typeBadgeClass[row.type] ?? 'bg-secondary'}">${escapeHtml(row.type)}</span></td>
        </tr>
        `;
    });

    document.getElementById('listCard').style.display = 'block';

    let printBtn = document.getElementById('printDetailBtn');
    printBtn.href = `/cash-submission-report/print?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

async function loadCategorySummary() {

    let userId = document.getElementById('user_id-field').value;
    let date = document.getElementById('date-field').value;

    if (!userId || !date) {
        Swal.fire({ icon: 'warning', title: 'Select User and Date', text: 'Both fields are required to load the summary.' });
        return;
    }

    const params = new URLSearchParams({ user_id: userId, date: date });

    document.getElementById('categorySummaryCard').style.display = 'none';
    document.getElementById('printSummaryBtn').style.display = 'none';

    const response = await fetch(`/cash-submission-report/summary?${params.toString()}`);
    const result = await response.json();

    if (!result.status) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Unable to load category wise summary.' });
        return;
    }

    let tbody = document.getElementById('categorySummaryTableBody');
    tbody.innerHTML = '';

    result.rows.forEach(row => {
        tbody.innerHTML += `
        <tr>
            <td>${escapeHtml(row.item_name)}</td>
            <td class="text-end">${fmtMoney(row.cash_collected)}</td>
            <td class="text-end">${fmtMoney(row.non_cash_collected)}</td>
            <td class="text-end fw-semibold">${fmtMoney(row.total_collected)}</td>
            <td class="text-end text-danger">${fmtMoney(row.refund)}</td>
            <td class="text-end text-danger">${fmtMoney(row.doctor_payment)}</td>
            <td class="text-end fw-semibold">${fmtMoney(row.amount_to_deposit)}</td>
        </tr>
        `;
    });

    let g = result.grand_total;

    document.getElementById('catsummary-total-cash_collected').innerText = fmtMoney(g.cash_collected);
    document.getElementById('catsummary-total-non_cash_collected').innerText = fmtMoney(g.non_cash_collected);
    document.getElementById('catsummary-total-total_collected').innerText = fmtMoney(g.total_collected);
    document.getElementById('catsummary-total-refund').innerText = fmtMoney(g.refund);
    document.getElementById('catsummary-total-doctor_payment').innerText = fmtMoney(g.doctor_payment);
    document.getElementById('catsummary-total-amount_to_deposit').innerText = fmtMoney(g.amount_to_deposit);

    document.getElementById('categorySummaryCard').style.display = 'block';

    let printBtn = document.getElementById('printSummaryBtn');
    printBtn.href = `/cash-submission-report/print-summary?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

// Selecting a tab both loads its report and controls which Print button
// shows -- only the active tab's Print button is ever visible. Bound to
// plain click (not shown.bs.tab) so re-clicking the already-active tab
// still reloads for a changed user/date, which shown.bs.tab wouldn't fire
// for since Bootstrap only raises it on an actual tab switch.
document.getElementById('detailTabLink').addEventListener('click', function () {
    document.getElementById('printSummaryBtn').style.display = 'none';
    loadDetail();
});

document.getElementById('summaryTabLink').addEventListener('click', function () {
    document.getElementById('printDetailBtn').style.display = 'none';
    loadCategorySummary();
});

document.getElementById('date-field').value = new Date().toISOString().substring(0, 10);
