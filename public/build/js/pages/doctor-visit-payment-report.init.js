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

    let doctorId = document.getElementById('doctor_id-field').value;
    let date = document.getElementById('date-field').value;

    if (!doctorId || !date) {
        Swal.fire({ icon: 'warning', title: 'Select Doctor and Date', text: 'Both fields are required to load the report.' });
        return;
    }

    const params = new URLSearchParams({ doctor_id: doctorId, date: date });

    const response = await fetch(`/doctor-visit-payment-report/list?${params.toString()}`);
    const result = await response.json();

    document.getElementById('summaryRow').style.display = 'none';
    document.getElementById('listCard').style.display = 'none';
    document.getElementById('noDataWrap').style.display = 'none';
    document.getElementById('printReportBtn').style.display = 'none';

    if (!result.status || !result.rows.length) {
        document.getElementById('noDataWrap').style.display = 'block';
        return;
    }

    let s = result.summary;

    document.getElementById('summary-total_invoices').innerText = s.total_invoices;
    document.getElementById('summary-settled_count').innerText = s.settled_count;
    document.getElementById('summary-not_settled_count').innerText = s.not_settled_count;
    document.getElementById('summary-total_payable').innerText = '₹' + fmtMoney(s.total_payable);
    document.getElementById('summary-total_settled_amount').innerText = '₹' + fmtMoney(s.total_settled_amount);
    document.getElementById('summary-total_balance').innerText = '₹' + fmtMoney(s.total_balance);

    document.getElementById('summaryRow').style.display = 'flex';

    let tbody = document.getElementById('listTableBody');
    tbody.innerHTML = '';

    result.rows.forEach(row => {

        let statusBadge = row.is_settled
            ? '<span class="badge bg-success">Settled</span>'
            : '<span class="badge bg-danger">Not Settled</span>';

        tbody.innerHTML += `
        <tr>
            <td class="fw-semibold">${escapeHtml(row.invoice_no)}</td>
            <td>${escapeHtml(row.patient_name)}</td>
            <td>${escapeHtml(row.invoice_time ?? '-')}</td>
            <td class="text-end">${row.is_settled ? fmtMoney(row.payable_amount) : '-'}</td>
            <td class="text-end">${row.is_settled ? fmtMoney(row.settled_amount) : '-'}</td>
            <td class="text-end">${row.is_settled ? fmtMoney(row.balance) : '-'}</td>
            <td>${escapeHtml(row.settlement_numbers ?? '-')}</td>
            <td>${statusBadge}</td>
        </tr>
        `;
    });

    document.getElementById('listCard').style.display = 'block';

    let printBtn = document.getElementById('printReportBtn');
    printBtn.href = `/doctor-visit-payment-report/print?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

document.getElementById('loadReportBtn').addEventListener('click', loadReport);

document.getElementById('date-field').value = new Date().toISOString().substring(0, 10);
