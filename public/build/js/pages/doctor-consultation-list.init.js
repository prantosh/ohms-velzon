function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

async function loadList() {

    let doctorId = document.getElementById('doctor_id-field').value;
    let date = document.getElementById('date-field').value;

    if (!doctorId || !date) {
        Swal.fire({ icon: 'warning', title: 'Select Doctor and Date', text: 'Both fields are required to load the list.' });
        return;
    }

    const params = new URLSearchParams({ doctor_id: doctorId, date: date });

    const response = await fetch(`/doctor-consultation-list/list?${params.toString()}`);
    const result = await response.json();

    document.getElementById('listCard').style.display = 'none';
    document.getElementById('noDataWrap').style.display = 'none';
    document.getElementById('printListBtn').style.display = 'none';

    if (!result.status || !result.data.length) {
        document.getElementById('noDataWrap').style.display = 'block';
        return;
    }

    let tbody = document.getElementById('listTableBody');
    tbody.innerHTML = '';

    let preparedCount = 0;

    result.data.forEach(row => {

        let invoiceBadge = row.invoice_prepared
            ? `<span class="badge bg-success">${escapeHtml(row.invoice_no ?? '')}</span>`
            : '<span class="badge bg-danger">Not Prepared</span>';

        if (row.invoice_prepared) preparedCount++;

        tbody.innerHTML += `
        <tr>
            <td>${row.token_no}</td>
            <td>${escapeHtml(row.patient_name)}</td>
            <td>${escapeHtml(row.patient_mobile_no ?? '-')}</td>
            <td>${row.patient_age ?? '-'}/${escapeHtml(row.patient_gender ?? '-')}</td>
            <td>${row.appointment_time_fmt ?? '-'}</td>
            <td>${invoiceBadge}</td>
        </tr>
        `;
    });

    document.getElementById('preparedCountBadge').innerText = `${preparedCount} Prepared`;
    document.getElementById('notPreparedCountBadge').innerText = `${result.data.length - preparedCount} Not Prepared`;

    document.getElementById('listCard').style.display = 'block';

    let printBtn = document.getElementById('printListBtn');
    printBtn.href = `/doctor-consultation-list/print?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

document.getElementById('loadListBtn').addEventListener('click', loadList);

document.getElementById('date-field').value = new Date().toISOString().substring(0, 10);
