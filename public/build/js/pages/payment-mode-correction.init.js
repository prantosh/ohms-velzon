(function () {

    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

    const money = (v) => Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));

    const $ = (id) => document.getElementById(id);

    let currentInvoice = null;
    let currentPayment = null;
    let modal = null;

    async function search(value) {

        const q = (value ?? $('searchInput').value).trim();

        if (!q) {
            Swal.fire({ icon: 'warning', title: 'Enter an Invoice No, Transaction No or Mobile No' });
            return;
        }

        $('listCard').style.display = 'none';
        $('invoiceCard').style.display = 'none';

        try {
            const res = await fetch(`/payment-mode-correction/search?${new URLSearchParams({ q })}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();

            if (!data.status) {
                Swal.fire({ icon: 'info', title: 'Not found', text: data.message || 'No invoice found.' });
                return;
            }

            if (data.mode === 'list') {
                renderList(data.invoices);
            } else {
                renderInvoice(data.invoice);
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Search failed', text: e.message });
        }
    }

    function renderList(invoices) {

        $('listBody').innerHTML = invoices.map((i) => `
            <tr>
                <td data-label="Invoice No" class="fw-semibold">${esc(i.invoice_no)}${i.cancelled ? ' <span class="badge bg-danger">Cancelled</span>' : ''}</td>
                <td data-label="Date">${esc(i.invoice_date)}</td>
                <td data-label="Patient">${esc(i.patient_name)}</td>
                <td data-label="Paid" class="text-end">${money(i.paid_amount)}</td>
                <td data-label="Mode">${esc(i.payment_mode || '-')}</td>
                <td><button type="button" class="btn btn-sm btn-soft-primary open-invoice" data-no="${esc(i.invoice_no)}">Open</button></td>
            </tr>`).join('');

        $('listCard').style.display = 'block';
    }

    function modeBadge(mode) {
        const cls = mode === 'Cash' ? 'bg-success-subtle text-success' : 'bg-info-subtle text-info';
        return `<span class="badge ${cls}">${esc(mode || '-')}</span>`;
    }

    function renderInvoice(inv) {

        currentInvoice = inv;

        $('invNo').textContent = inv.invoice_no;
        $('invPatient').textContent = `${inv.patient_name ?? ''} ${inv.patient_mobile_no ? '(' + inv.patient_mobile_no + ')' : ''}`;
        $('invDate').textContent = inv.invoice_date;
        $('invTotal').textContent = money(inv.total_amount);
        $('invPaid').textContent = money(inv.paid_amount);
        $('invDue').textContent = money(inv.due_amount);
        $('invDue').classList.toggle('text-danger', inv.due_amount > 0);
        $('cancelledWarning').style.display = inv.cancelled ? 'block' : 'none';

        $('paymentsBody').innerHTML = inv.payments.length
            ? inv.payments.map((p) => `
                <tr>
                    <td data-label="Transaction No" class="fw-semibold">${esc(p.transaction_no)}</td>
                    <td data-label="Date / Time">${esc(p.date)} ${esc(p.time)}</td>
                    <td data-label="Collected By">${esc(p.operator)}</td>
                    <td data-label="Remarks" class="small">${esc(p.remarks)}</td>
                    <td data-label="Amount" class="text-end">${money(p.amount)}</td>
                    <td data-label="Mode">${modeBadge(p.payment_mode)}${p.payment_reference ? ' <span class="text-muted small">#' + esc(p.payment_reference) + '</span>' : ''}</td>
                    <td>${inv.cancelled ? '' : `<button type="button" class="btn btn-sm btn-primary change-mode" data-id="${p.id}">Change</button>`}</td>
                </tr>`).join('')
            : '<tr><td colspan="7" class="text-center text-muted">No active collection on this invoice.</td></tr>';

        $('invoiceCard').style.display = 'block';
    }

    function refreshModalHints() {

        const mode = $('mMode').value;
        $('mRefWrap').style.display = mode === 'Cash' ? 'none' : 'block';

        const wasCash = currentPayment.payment_mode === 'Cash';
        const nowCash = mode === 'Cash';
        const note = $('mCashNote');

        if (wasCash !== nowCash) {
            note.textContent = nowCash
                ? `${currentPayment.operator}'s cash in hand for ${currentPayment.date} will go UP by ${money(currentPayment.amount)}.`
                : `${currentPayment.operator}'s cash in hand for ${currentPayment.date} will go DOWN by ${money(currentPayment.amount)}.`;
            note.style.display = 'block';
        } else {
            note.style.display = 'none';
        }
    }

    function openModal(id) {

        currentPayment = currentInvoice.payments.find((p) => String(p.id) === String(id));

        if (!currentPayment) {
            return;
        }

        $('mTxn').textContent = currentPayment.transaction_no;
        $('mAmount').textContent = money(currentPayment.amount);
        $('mCurrent').textContent = currentPayment.payment_mode || '-';
        $('mMode').value = currentPayment.payment_mode === 'Cash' ? 'UPI' : 'Cash';
        $('mRef').value = '';
        $('mReason').value = '';

        refreshModalHints();

        modal = modal || new bootstrap.Modal($('modeModal'));
        modal.show();
    }

    async function save() {

        const mode = $('mMode').value;
        const reason = $('mReason').value.trim();
        const reference = $('mRef').value.trim();

        if (reason.length < 5) {
            Swal.fire({ icon: 'warning', title: 'Enter a reason (at least 5 characters)' });
            return;
        }

        if (mode !== 'Cash' && !/^\d{4}$/.test(reference)) {
            Swal.fire({ icon: 'warning', title: 'Enter the 4-digit reference for Card / UPI' });
            return;
        }

        const confirm = await Swal.fire({
            icon: 'question',
            title: 'Change payment mode?',
            html: `${esc(currentPayment.transaction_no)}: <b>${esc(currentPayment.payment_mode || '-')}</b> &rarr; <b>${esc(mode)}</b>`,
            showCancelButton: true,
            confirmButtonText: 'Yes, change it',
        });

        if (!confirm.isConfirmed) {
            return;
        }

        $('mSave').disabled = true;

        try {
            const res = await fetch('/payment-mode-correction/update', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({
                    transaction_id: currentPayment.id,
                    payment_mode: mode,
                    payment_reference: mode === 'Cash' ? null : reference,
                    reason,
                }),
            });
            const data = await res.json();

            if (!res.ok || !data.status) {
                const msg = data.message || Object.values(data.errors || {}).flat().join(' ') || 'Could not save.';
                Swal.fire({ icon: 'error', title: 'Not changed', text: msg });
                return;
            }

            modal.hide();
            renderInvoice(data.invoice);
            Swal.fire({ icon: 'success', title: 'Updated', text: data.message, timer: 2500, showConfirmButton: false });

        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Save failed', text: e.message });
        } finally {
            $('mSave').disabled = false;
        }
    }

    $('searchBtn').addEventListener('click', () => search());
    $('searchInput').addEventListener('keydown', (e) => { if (e.key === 'Enter') { search(); } });
    $('mMode').addEventListener('change', refreshModalHints);
    $('mSave').addEventListener('click', save);

    document.addEventListener('click', (e) => {
        const open = e.target.closest('.open-invoice');
        if (open) {
            $('searchInput').value = open.dataset.no;
            search(open.dataset.no);
            return;
        }
        const change = e.target.closest('.change-mode');
        if (change) {
            openModal(change.dataset.id);
        }
    });

})();
