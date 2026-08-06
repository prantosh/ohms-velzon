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

function printButtonCell(row) {
    if (!row.print_url && !row.print_ajax_url) {
        return '<td class="text-center text-muted">-</td>';
    }
    return `<td class="text-center">
        <button type="button" class="btn btn-sm btn-primary print-invoice-btn"
            data-print-url="${escapeHtml(row.print_url || '')}"
            data-print-ajax-url="${escapeHtml(row.print_ajax_url || '')}"
            title="Print Invoice">
            <i class="ri-printer-line"></i>
        </button>
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

// Doctor Visit invoice PDFs are generated on demand and return JSON
// {status, pdf_url} -- must be fetched via AJAX, not opened as a direct link.
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.print-invoice-btn');
    if (!btn) return;

    const printUrl = btn.dataset.printUrl;
    const ajaxUrl = btn.dataset.printAjaxUrl;

    if (printUrl) {
        window.open(printUrl, '_blank');
        return;
    }

    if (!ajaxUrl) return;

    btn.disabled = true;

    fetch(ajaxUrl)
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            if (data.status && data.pdf_url) {
                window.open(data.pdf_url, '_blank');
            } else {
                Swal.fire({ icon: 'error', title: 'PDF Error', text: data.message || 'Unable to generate PDF.' });
            }
        })
        .catch(() => {
            btn.disabled = false;
            Swal.fire({ icon: 'error', title: 'PDF Generation Failed', text: 'Unable to generate PDF.' });
        });
});

let requestSeq = 0;

async function loadReport() {

    let userId = document.getElementById('user_id-field').value;
    let range = document.getElementById('range-field').value;

    if (!userId) {
        Swal.fire({ icon: 'warning', title: 'Select User', text: 'Please select a user to load the dashboard.' });
        return;
    }

    const mySeq = ++requestSeq;

    const params = new URLSearchParams({ user_id: userId, range: range });

    const response = await fetch(`/doctor-payable-by-user/list?${params.toString()}`);
    const result = await response.json();

    if (mySeq !== requestSeq) return;

    document.getElementById('summaryRow').style.display = 'none';
    document.getElementById('pendingCard').style.display = 'none';
    document.getElementById('settledCard').style.display = 'none';
    document.getElementById('noDataWrap').style.display = 'none';

    if (!result.status) {
        document.getElementById('noDataWrap').style.display = 'block';
        return;
    }

    let s = result.summary;

    document.getElementById('summary-pending_count').innerText = s.pending_count;
    document.getElementById('summary-pending_balance_amount').innerText = '₹' + fmtMoney(s.pending_balance_amount);
    document.getElementById('summary-settled_count').innerText = s.settled_count;
    document.getElementById('summary-settled_amount').innerText = '₹' + fmtMoney(s.settled_amount);
    document.getElementById('summary-settled_count_label').innerText = 'Settled Payables (' + result.range_label + ')';
    document.getElementById('summary-settled_amount_label').innerText = 'Settled Amount (' + result.range_label + ')';
    document.getElementById('settledCardTitle').innerText = 'Settled Doctor Payables — ' + result.range_label;

    document.getElementById('summaryRow').style.display = 'flex';

    // --- toggle the User column on/off depending on single-user vs all-users ---
    document.querySelectorAll('.user-col').forEach(el => {
        el.style.display = result.is_all_users ? '' : 'none';
    });
    let userCell = row => {
        if (!result.is_all_users) return '';
        if (row.collection_due) {
            return `<td><span class="text-danger fw-semibold">${escapeHtml(row.user_name)}</span></td>`;
        }
        return `<td>${escapeHtml(row.user_name)}</td>`;
    };

    // --- Pending table ---
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

    document.getElementById('pendingCard').style.display = 'block';

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

    document.getElementById('settledCard').style.display = 'block';

    let printBtn = document.getElementById('printReportBtn');
    printBtn.href = `/doctor-payable-by-user/print?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

document.getElementById('loadReportBtn').addEventListener('click', loadReport);

document.getElementById('range-field').addEventListener('change', function () {
    if (document.getElementById('user_id-field').value) {
        loadReport();
    }
});
