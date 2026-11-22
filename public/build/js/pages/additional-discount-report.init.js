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

function buildParams() {
    const params = new URLSearchParams({
        from_date: document.getElementById('from_date-field').value,
        to_date: document.getElementById('to_date-field').value,
        invoice_type: document.getElementById('invoice_type-field').value,
        include_cancelled: document.getElementById('include_cancelled-field').checked ? 1 : 0,
    });

    const approver = document.getElementById('approver_id-field').value;
    const operator = document.getElementById('operator_id-field').value;
    if (approver) params.set('approver_id', approver);
    if (operator) params.set('operator_id', operator);

    return params;
}

function renderBreakup(tbodyId, items) {
    document.getElementById(tbodyId).innerHTML = items.length
        ? items.map(i => `<tr><td>${escapeHtml(i.name)} <small class="text-muted">(${i.count})</small></td>
            <td class="text-end">&#8377;${fmtMoney(i.total)}</td></tr>`).join('')
        : '<tr><td class="text-muted">-</td></tr>';
}

let requestSeq = 0;

async function loadReport() {
    const from = document.getElementById('from_date-field').value;
    const to = document.getElementById('to_date-field').value;

    if (!from || !to) {
        Swal.fire({ icon: 'warning', title: 'Missing Dates', text: 'Please select both From Date and To Date.' });
        return;
    }

    const mySeq = ++requestSeq;
    const params = buildParams();

    const response = await fetch(`/additional-discount-report/data?${params.toString()}`, {
        headers: { 'Accept': 'application/json' }
    });
    const result = await response.json();

    if (mySeq !== requestSeq) return;

    ['totalsRow', 'breakupRow', 'detailCard', 'noDataWrap', 'printBtn']
        .forEach(id => document.getElementById(id).style.display = 'none');

    if (!response.ok) {
        const msg = result.errors ? Object.values(result.errors).flat().join('\n') : (result.message || 'Failed to load report.');
        Swal.fire({ icon: 'error', title: 'Error', text: msg });
        return;
    }

    if (!result.rows.length) {
        document.getElementById('noDataWrap').style.display = 'block';
        return;
    }

    const s = result.summary;
    document.getElementById('sum-invoices').innerText = s.invoices;
    document.getElementById('sum-lines').innerText = s.lines;
    document.getElementById('sum-total').innerText = '₹' + fmtMoney(s.total_discount);

    renderBreakup('byApproverBody', s.by_approver);
    renderBreakup('byOperatorBody', s.by_operator);
    renderBreakup('byTypeBody', s.by_type);

    document.getElementById('detailBody').innerHTML = result.rows.map(r => {
        const pct = Number(r.additional_discount_percent) > 0 ? Number(r.additional_discount_percent) + '%' : '-';
        const cancelled = r.is_cancelled ? ' <span class="badge bg-danger">Cancelled</span>' : '';
        return `<tr class="${r.is_cancelled ? 'text-decoration-line-through text-muted' : ''}">
            <td>${escapeHtml(r.date_fmt)}</td>
            <td>${escapeHtml(r.invoice_no)}${cancelled}</td>
            <td>${escapeHtml(r.type_label)}</td>
            <td>${escapeHtml(r.patient_name)}</td>
            <td>${escapeHtml(r.item_description)}</td>
            <td class="text-end">${fmtMoney(r.rate)}</td>
            <td class="text-end">${pct}</td>
            <td class="text-end fw-semibold">${fmtMoney(r.discount_total)}</td>
            <td>${escapeHtml(r.approver_name)}</td>
            <td>${escapeHtml(r.operator_name)}</td>
            <td>${escapeHtml(r.discount_approval_remarks)}</td>
        </tr>`;
    }).join('');

    document.getElementById('totalsRow').style.display = 'flex';
    document.getElementById('breakupRow').style.display = 'flex';
    document.getElementById('detailCard').style.display = 'block';

    const printBtn = document.getElementById('printBtn');
    printBtn.href = `/additional-discount-report/print?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

document.getElementById('loadBtn').addEventListener('click', loadReport);
