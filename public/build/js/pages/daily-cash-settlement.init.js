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

let isAllUsersGlobal = false;

async function loadReport() {

    let userId = document.getElementById('user_id-field').value;
    let date = document.getElementById('date-field').value;

    if (!userId) {
        Swal.fire({ icon: 'warning', title: 'Select User', text: 'Please select a user to load the report.' });
        return;
    }

    if (!date) {
        Swal.fire({ icon: 'warning', title: 'Select Date', text: 'Please select a date to load the report.' });
        return;
    }

    isAllUsersGlobal = (userId === 'ALL');

    document.getElementById('summaryRow').style.display = 'none';
    document.getElementById('reportHeaderWrap').style.display = 'none';
    document.getElementById('doctorGroupsWrap').innerHTML = '';
    document.getElementById('noDataWrap').style.display = 'none';

    const params = new URLSearchParams({ user_id: userId, date: date });

    const response = await fetch(`/daily-cash-settlement/list?${params.toString()}`);
    const result = await response.json();

    if (!result.status) {
        Swal.fire({ icon: 'error', title: 'Failed to Load', text: result.message || 'Please try again.' });
        return;
    }

    if (!result.groups.length) {
        document.getElementById('noDataWrap').style.display = 'block';
        return;
    }

    let userLabel = isAllUsersGlobal
        ? 'All Users'
        : document.getElementById('user_id-field').selectedOptions[0].text;
    let dateFmt = new Date(date + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' }).replace(/\//g, '-');

    document.getElementById('summary-total_doctors').innerText = result.summary.total_doctors;
    document.getElementById('summary-total_invoices').innerText = result.summary.total_invoices;
    document.getElementById('summary-total_amount').innerText = '₹' + fmtMoney(result.summary.total_amount);
    document.getElementById('summaryRow').style.display = 'flex';

    document.getElementById('reportTitle').innerText = 'Daily Cash Settlement — ' + userLabel + ' — ' + dateFmt;
    document.getElementById('printReportBtn').href = `/daily-cash-settlement/print?${params.toString()}`;
    document.getElementById('reportHeaderWrap').style.display = 'flex';

    renderGroups(result.groups);
}

function renderGroups(groups) {

    let wrap = document.getElementById('doctorGroupsWrap');
    wrap.innerHTML = '';

    groups.forEach(group => {

        let rowsHtml = group.items.map(item => `
            <tr>
                <td>${escapeHtml(item.invoice_no)}</td>
                <td>${escapeHtml(item.category)}</td>
                ${isAllUsersGlobal ? `<td>${escapeHtml(item.user_name)}</td>` : ''}
                <td>${escapeHtml(item.patient_name)}</td>
                <td>${escapeHtml(item.item_description)}</td>
                <td>${escapeHtml(item.last_settlement_no)}</td>
                <td class="text-end">${fmtMoney(item.paid_amount)}</td>
            </tr>
        `).join('');

        wrap.innerHTML += `
        <div class="card doctor-group-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0"><i class="ri-user-heart-line"></i> ${escapeHtml(group.doctor_name)}</h5>
                <span class="badge bg-success">${group.invoice_count} Invoice(s) &mdash; ₹${fmtMoney(group.total_amount)}</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice No</th>
                                <th>Category</th>
                                ${isAllUsersGlobal ? '<th>User</th>' : ''}
                                <th>Patient</th>
                                <th>Item</th>
                                <th>Settlement No</th>
                                <th width="110">Amount (&#8377;)</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rowsHtml}
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-bold">
                                <td colspan="${isAllUsersGlobal ? 6 : 5}" class="text-end">Total for ${escapeHtml(group.doctor_name)}</td>
                                <td class="text-end">${fmtMoney(group.total_amount)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        `;
    });
}

document.getElementById('loadReportBtn').addEventListener('click', loadReport);
