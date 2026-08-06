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

async function loadReport() {

    let userId = document.getElementById('user_id-field').value;
    let date = document.getElementById('date-field').value;

    if (!userId || !date) {
        Swal.fire({ icon: 'warning', title: 'Select User and Date', text: 'Both fields are required to load the report.' });
        return;
    }

    const params = new URLSearchParams({ user_id: userId, date: date });

    const response = await fetch(`/user-invoice-report/list?${params.toString()}`);
    const result = await response.json();

    document.getElementById('summaryRow').style.display = 'none';
    document.getElementById('paymentModeCard').style.display = 'none';
    document.getElementById('listCard').style.display = 'none';
    document.getElementById('noDataWrap').style.display = 'none';
    document.getElementById('printReportBtn').style.display = 'none';

    if (!result.status || !result.rows.length) {
        document.getElementById('noDataWrap').style.display = 'block';
        return;
    }

    let s = result.summary;

    document.getElementById('summary-total_invoices').innerText = s.total_invoices;
    document.getElementById('summary-total_amount').innerText = '₹' + fmtMoney(s.total_amount);
    document.getElementById('summary-doc_invoice_count').innerText = s.doc_invoice_count;
    document.getElementById('summary-doc_settled_count').innerText = s.doc_settled_count;
    document.getElementById('summary-doc_not_settled_count').innerText = s.doc_not_settled_count;
    document.getElementById('summary-doc_total_balance').innerText = '₹' + fmtMoney(s.doc_total_balance);

    document.getElementById('summaryRow').style.display = 'flex';

    let pmRow = document.getElementById('paymentModeRow');
    pmRow.innerHTML = '';

    Object.keys(s.payment_mode_breakdown).forEach(mode => {
        pmRow.innerHTML += `
        <div class="col">
            <div class="border rounded p-2">
                <div class="text-muted small">${escapeHtml(mode)}</div>
                <div class="fw-semibold">₹${fmtMoney(s.payment_mode_breakdown[mode])}</div>
            </div>
        </div>
        `;
    });

    document.getElementById('paymentModeCard').style.display = 'block';

    let tbody = document.getElementById('listTableBody');
    tbody.innerHTML = '';

    result.rows.forEach(row => {

        let statusCell;

        if (!row.is_doc) {
            statusCell = '<span class="badge bg-secondary">N/A</span>';
        } else if (row.is_settled) {
            statusCell = '<span class="badge bg-success">Settled</span>';
        } else {
            statusCell = '<span class="badge bg-danger">Not Settled</span>';
        }

        let payableCell = row.is_doc ? (row.is_settled ? fmtMoney(row.payable_amount) : '-') : 'N/A';
        let settledCell = row.is_doc ? (row.is_settled ? fmtMoney(row.settled_amount) : '-') : 'N/A';
        let balanceCell = row.is_doc ? (row.is_settled ? fmtMoney(row.balance) : '-') : 'N/A';
        let settlementNoCell = row.is_doc ? (row.is_settled ? escapeHtml(row.settlement_numbers) : '-') : '-';

        tbody.innerHTML += `
        <tr>
            <td class="fw-semibold">${escapeHtml(row.invoice_no)}</td>
            <td>${escapeHtml(row.invoice_type_label)}</td>
            <td>${escapeHtml(row.patient_name)}</td>
            <td>${escapeHtml(row.time)}</td>
            <td class="text-end">${fmtMoney(row.total_amount)}</td>
            <td>${escapeHtml(row.payment_mode)}</td>
            <td class="text-end">${payableCell}</td>
            <td class="text-end">${settledCell}</td>
            <td class="text-end">${balanceCell}</td>
            <td>${settlementNoCell}</td>
            <td>${statusCell}</td>
        </tr>
        `;
    });

    document.getElementById('listCard').style.display = 'block';

    let printBtn = document.getElementById('printReportBtn');
    printBtn.href = `/user-invoice-report/print?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

document.getElementById('loadReportBtn').addEventListener('click', loadReport);

document.getElementById('date-field').value = new Date().toISOString().substring(0, 10);
