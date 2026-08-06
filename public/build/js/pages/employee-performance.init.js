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

async function loadEmployeePerformance() {

    let fromDate = document.getElementById('from_date-field').value;
    let toDate = document.getElementById('to_date-field').value;

    if (!fromDate || !toDate) {
        Swal.fire({ icon: 'warning', title: 'Select Duration', text: 'Both From Date and To Date are required.' });
        return;
    }

    currentFromDate = fromDate;
    currentToDate = toDate;

    const params = new URLSearchParams({ from_date: fromDate, to_date: toDate });

    const response = await fetch(`/employee-performance/list?${params.toString()}`);
    const result = await response.json();

    document.getElementById('listCard').style.display = 'none';
    document.getElementById('noDataWrap').style.display = 'none';

    if (!result.status || !result.data.length) {
        document.getElementById('noDataWrap').style.display = 'block';
        return;
    }

    let tbody = document.getElementById('employeeTableBody');
    tbody.innerHTML = '';

    let totals = {
        invoice_count: 0,
        cash_collected: 0,
        non_cash_collected: 0,
        cash_refunded: 0,
        cash_paid_to_doctors: 0,
        net_cash_to_deposit: 0,
    };

    result.data.forEach(row => {

        Object.keys(totals).forEach(key => totals[key] += Number(row[key] ?? 0));

        let netClass = row.net_cash_to_deposit >= 0 ? 'text-success' : 'text-danger';

        tbody.innerHTML += `
        <tr>
            <td class="fw-semibold">${escapeHtml(row.name)}</td>
            <td>${escapeHtml(row.role)}</td>
            <td class="text-end">${row.invoice_count}</td>
            <td class="text-end">${fmtMoney(row.cash_collected)}</td>
            <td class="text-end">${fmtMoney(row.non_cash_collected)}</td>
            <td class="text-end text-danger">${fmtMoney(row.cash_refunded)}</td>
            <td class="text-end text-danger">${fmtMoney(row.cash_paid_to_doctors)}</td>
            <td class="text-end fw-semibold ${netClass}">${fmtMoney(row.net_cash_to_deposit)}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-soft-primary view-detail-btn"
                        data-user-id="${row.user_id}" data-name="${escapeHtml(row.name)}">
                    <i class="ri-eye-line"></i>
                </button>
            </td>
        </tr>
        `;
    });

    document.getElementById('total-invoice_count').innerText = totals.invoice_count;
    document.getElementById('total-cash_collected').innerText = '₹' + fmtMoney(totals.cash_collected);
    document.getElementById('total-non_cash_collected').innerText = '₹' + fmtMoney(totals.non_cash_collected);
    document.getElementById('total-cash_refunded').innerText = '₹' + fmtMoney(totals.cash_refunded);
    document.getElementById('total-cash_paid_to_doctors').innerText = '₹' + fmtMoney(totals.cash_paid_to_doctors);
    document.getElementById('total-net_cash_to_deposit').innerText = '₹' + fmtMoney(totals.net_cash_to_deposit);

    document.getElementById('listCard').style.display = 'block';

    let printAllBtn = document.getElementById('printAllBtn');
    printAllBtn.href = `/employee-performance/print-all?${params.toString()}`;
    printAllBtn.style.display = 'inline-block';
}

async function loadEmployeeDetail(userId, name) {

    document.getElementById('detailModalTitle').innerText = `${name} — Detail (${currentFromDate} to ${currentToDate})`;

    const params = new URLSearchParams({
        user_id: userId,
        from_date: currentFromDate,
        to_date: currentToDate,
    });

    document.getElementById('printDetailBtn').href = `/employee-performance/print-detail?${params.toString()}`;

    const response = await fetch(`/employee-performance/detail?${params.toString()}`);
    const result = await response.json();

    new bootstrap.Modal(document.getElementById('detailModal')).show();

    if (!result.status) return;

    let s = result.summary;

    document.getElementById('detail-cash_collected').innerText = '₹' + fmtMoney(s.cash_collected);
    document.getElementById('detail-non_cash_collected').innerText = '₹' + fmtMoney(s.non_cash_collected);
    document.getElementById('detail-cash_refunded').innerText = '₹' + fmtMoney(s.cash_refunded);
    document.getElementById('detail-cash_paid_to_doctors').innerText = '₹' + fmtMoney(s.cash_paid_to_doctors);
    document.getElementById('detail-net_cash_to_deposit').innerText = '₹' + fmtMoney(s.net_cash_to_deposit);
    document.getElementById('detail-invoice_count').innerText = s.invoice_count;

    // --- Doctor payments ---
    let doctorTbody = document.getElementById('detailDoctorPaymentTableBody');
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
        document.getElementById('detailNoDoctorPaymentWrap').style.display = 'none';
    } else {
        document.getElementById('detailNoDoctorPaymentWrap').style.display = 'block';
    }

    // --- Group breakdown ---
    let groupTbody = document.getElementById('detailGroupBreakdownTableBody');
    groupTbody.innerHTML = '';

    result.group_breakdown.forEach(row => {
        let amountClass = row.amount >= 0 ? '' : 'text-danger';

        let itemsHtml = '';
        if (row.items && row.items.length) {
            let itemLines = row.items.map(it =>
                `${escapeHtml(it.item_code ?? '-')} ${escapeHtml(it.item_description ?? '')} (Invoice ${escapeHtml(it.invoice_no)})`
            ).join('<br>');
            itemsHtml = `<br><small class="text-muted">${itemLines}</small>`;
        }

        groupTbody.innerHTML += `
        <tr>
            <td>${escapeHtml(row.group_name)}${itemsHtml}</td>
            <td class="text-end ${amountClass}">${fmtMoney(row.amount)}</td>
        </tr>
        `;
    });

    document.getElementById('detailGroupBreakdownTotal').innerText = '₹' + fmtMoney(s.net_cash_to_deposit);

    // --- Ledger ---
    let ledgerTbody = document.getElementById('detailLedgerTableBody');
    ledgerTbody.innerHTML = '';

    let typeBadgeClass = {
        'Collection': 'bg-success',
        'Refund': 'bg-danger',
        'Doctor Payment': 'bg-warning text-dark',
    };

    if (result.ledger.length) {
        result.ledger.forEach(row => {

            let amountClass = row.amount >= 0 ? 'text-success' : 'text-danger';

            ledgerTbody.innerHTML += `
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
        document.getElementById('detailNoLedgerWrap').style.display = 'none';
    } else {
        document.getElementById('detailNoLedgerWrap').style.display = 'block';
    }
}

document.getElementById('loadReportBtn').addEventListener('click', loadEmployeePerformance);

document.getElementById('employeeTableBody').addEventListener('click', function (e) {

    let btn = e.target.closest('.view-detail-btn');
    if (!btn) return;

    loadEmployeeDetail(btn.dataset.userId, btn.dataset.name);
});

document.addEventListener('DOMContentLoaded', function () {

    let today = new Date();
    let firstOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

    setFlatpickrValue('from_date-field', firstOfMonth.toISOString().substring(0, 10));
    setFlatpickrValue('to_date-field', today.toISOString().substring(0, 10));
});
