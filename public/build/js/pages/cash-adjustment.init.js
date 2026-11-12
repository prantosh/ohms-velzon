(function () {

    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
    const money = (v) => Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
    const $ = (id) => document.getElementById(id);

    let selected = null;

    async function load() {

        const res = await fetch('/cash-adjustment/data', { headers: { 'Accept': 'application/json' } });
        const data = await res.json();

        const deficitBody = $('deficitBody');
        deficitBody.innerHTML = '';

        if (!data.deficits.length) {
            deficitBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No pending cash deficits.</td></tr>';
        }

        data.deficits.forEach((d, i) => {
            deficitBody.insertAdjacentHTML('beforeend', `
                <tr>
                    <td data-label="User">${esc(d.user_name)}</td>
                    <td data-label="Deficit Date">${esc(d.date_fmt)}</td>
                    <td data-label="Pending Deficit" class="text-end text-danger fw-semibold">${money(d.deficit)}</td>
                    <td><button type="button" class="btn btn-sm btn-primary" data-idx="${i}">Adjust</button></td>
                </tr>`);
        });

        deficitBody.querySelectorAll('button[data-idx]').forEach((btn) => {
            btn.addEventListener('click', () => openForm(data.deficits[btn.dataset.idx]));
        });

        const recentBody = $('recentBody');
        recentBody.innerHTML = '';

        if (!data.recent.length) {
            recentBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No adjustments yet.</td></tr>';
        }

        data.recent.forEach((a) => {
            const cancelled = a.status === 'CANCELLED';
            recentBody.insertAdjacentHTML('beforeend', `
                <tr class="${cancelled ? 'text-muted' : ''}">
                    <td data-label="Adj. No">${esc(a.adjustment_no)}</td>
                    <td data-label="User">${esc(a.user_name)}</td>
                    <td data-label="Deficit Date">${esc(a.adjustment_date_fmt)}</td>
                    <td data-label="Amount" class="text-end">${money(a.amount)}</td>
                    <td data-label="Source">${esc(a.source_label)}</td>
                    <td data-label="Reference / Remarks">${a.reference_date_fmt ? 'Against deposit of ' + esc(a.reference_date_fmt) + '. ' : ''}${esc(a.reference_note)} ${esc(a.remarks)}${cancelled ? '<br><b>Cancelled:</b> ' + esc(a.cancel_reason) : ''}</td>
                    <td data-label="Approved By">${esc(a.created_by_name)}</td>
                    <td data-label="Status">${cancelled ? '<span class="badge bg-secondary">CANCELLED</span>' : '<span class="badge bg-success">ACTIVE</span>'}</td>
                    <td>${cancelled ? '' : `<button type="button" class="btn btn-sm btn-outline-danger" data-cancel="${a.id}">Cancel</button>`}</td>
                </tr>`);
        });

        recentBody.querySelectorAll('button[data-cancel]').forEach((btn) => {
            btn.addEventListener('click', () => cancelAdjustment(btn.dataset.cancel));
        });
    }

    function openForm(deficit) {
        selected = deficit;
        $('adjTarget').value = deficit.user_name + ' - ' + deficit.date_fmt;
        $('adjDeficit').value = money(deficit.deficit);
        $('adjAmount').value = deficit.deficit;
        $('adjRefDate').value = '';
        $('adjRefNote').value = '';
        $('adjRemarks').value = '';
        $('formCard').style.display = 'block';
        $('formCard').scrollIntoView({ behavior: 'smooth' });
    }

    $('adjCancel').addEventListener('click', () => {
        selected = null;
        $('formCard').style.display = 'none';
    });

    $('adjSave').addEventListener('click', async () => {

        if (!selected) return;

        const confirmation = await Swal.fire({
            icon: 'question',
            title: 'Post adjustment?',
            html: `This clears ₹${money($('adjAmount').value)} of ${esc(selected.user_name)}'s deficit for ${esc(selected.date_fmt)}.`,
            showCancelButton: true,
            confirmButtonText: 'Post'
        });

        if (!confirmation.isConfirmed) return;

        const res = await fetch('/cash-adjustment/store', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
            body: JSON.stringify({
                user_id: selected.user_id,
                adjustment_date: selected.date,
                amount: $('adjAmount').value,
                source: $('adjSource').value,
                reference_date: $('adjRefDate').value || null,
                reference_note: $('adjRefNote').value,
                remarks: $('adjRemarks').value
            })
        });

        const data = await res.json();

        if (!res.ok || !data.status) {
            const text = data.errors ? Object.values(data.errors).flat().join(', ') : (data.message || 'Unable to post adjustment.');
            Swal.fire({ icon: 'error', title: 'Error', text });
            return;
        }

        Swal.fire({ icon: 'success', title: 'Posted', text: data.message });
        selected = null;
        $('formCard').style.display = 'none';
        load();
    });

    async function cancelAdjustment(id) {

        const { value: reason, isConfirmed } = await Swal.fire({
            icon: 'warning',
            title: 'Cancel this adjustment?',
            text: 'The deficit becomes pending again.',
            input: 'text',
            inputLabel: 'Reason',
            showCancelButton: true,
            inputValidator: (v) => (!v || v.trim().length < 5) ? 'Enter a reason (min 5 characters)' : null
        });

        if (!isConfirmed) return;

        const res = await fetch(`/cash-adjustment/${id}/cancel`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
            body: JSON.stringify({ reason })
        });

        const data = await res.json();

        if (!res.ok || !data.status) {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Unable to cancel.' });
            return;
        }

        Swal.fire({ icon: 'success', title: 'Cancelled', text: data.message });
        load();
    }

    load();

})();
