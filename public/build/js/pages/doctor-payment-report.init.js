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

let requestSeq = 0;

async function loadReport() {

    let userId = document.getElementById('user_id-field').value;
    let doctorId = document.getElementById('doctor_id-field').value;
    let date = document.getElementById('date-field').value;

    if (!userId || !doctorId || !date) {
        Swal.fire({ icon: 'warning', title: 'Missing Selection', text: 'Please select a user, doctor and date to load the report.' });
        return;
    }

    const mySeq = ++requestSeq;

    const params = new URLSearchParams({ user_id: userId, doctor_id: doctorId, date: date });

    const response = await fetch(`/doctor-payment-report/list?${params.toString()}`);
    const result = await response.json();

    if (mySeq !== requestSeq) return;

    document.getElementById('summaryRow').style.display = 'none';
    document.getElementById('listCard').style.display = 'none';
    document.getElementById('noDataWrap').style.display = 'none';
    document.getElementById('printReportBtn').style.display = 'none';

    if (!result.status || !result.rows.length) {
        document.getElementById('noDataWrap').style.display = 'block';
        return;
    }

    let s = result.summary;

    document.getElementById('summary-total_items').innerText = s.total_items;
    document.getElementById('summary-total_gross').innerText = '₹' + fmtMoney(s.total_gross);
    document.getElementById('summary-total_doctor_fees').innerText = '₹' + fmtMoney(s.total_doctor_fees);
    document.getElementById('summary-total_clinic_charge').innerText = '₹' + fmtMoney(s.total_clinic_charge);

    document.getElementById('summaryRow').style.display = 'flex';

    document.querySelectorAll('.user-col').forEach(el => {
        el.style.display = result.is_all_users ? '' : 'none';
    });
    document.querySelectorAll('.doctor-col').forEach(el => {
        el.style.display = result.is_all_doctors ? '' : 'none';
    });

    let tbody = document.getElementById('listTableBody');
    tbody.innerHTML = '';

    result.rows.forEach(row => {
        tbody.innerHTML += `
        <tr>
            <td class="fw-semibold">${escapeHtml(row.invoice_no)}</td>
            ${result.is_all_users ? `<td>${escapeHtml(row.user_name)}</td>` : ''}
            ${result.is_all_doctors ? `<td>${escapeHtml(row.doctor_name)}</td>` : ''}
            <td>${escapeHtml(row.patient_name)}</td>
            <td>${escapeHtml(row.patient_gender) || '-'}</td>
            <td>${escapeHtml(row.patient_age) || '-'}</td>
            <td>${escapeHtml(row.card_number) || '-'}</td>
            <td>${escapeHtml(row.item_description)}</td>
            <td>${escapeHtml(row.time_fmt)}</td>
            <td class="text-end text-danger">${fmtMoney(row.doctor_fees)}</td>
            <td class="text-end text-success">${fmtMoney(row.clinic_charge)}</td>
            <td>${row.settlement_display === 'Not Settled' ? '<span class="badge bg-warning text-dark">Not Settled</span>' : escapeHtml(row.settlement_display)}</td>
        </tr>
        `;
    });

    document.getElementById('listCard').style.display = 'block';

    let printBtn = document.getElementById('printReportBtn');
    printBtn.href = `/doctor-payment-report/print?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

document.getElementById('loadReportBtn').addEventListener('click', loadReport);
