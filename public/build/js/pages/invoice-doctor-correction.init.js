(function () {

    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

    const money = (v) => Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));

    const $ = (id) => document.getElementById(id);

    let currentInvoice = null;

    async function search(value) {

        const q = (value ?? $('searchInput').value).trim();

        if (!q) {
            Swal.fire({ icon: 'warning', title: 'Enter an Invoice No or Mobile No' });
            return;
        }

        $('listCard').style.display = 'none';
        $('invoiceCard').style.display = 'none';

        try {
            const res = await fetch(`/invoice-doctor-correction/search?${new URLSearchParams({ q })}`, {
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
                <td data-label="Total" class="text-end">${money(i.total_amount)}</td>
                <td data-label="Due" class="text-end ${i.due_amount > 0 ? 'text-danger fw-semibold' : ''}">${money(i.due_amount)}</td>
                <td><button type="button" class="btn btn-sm btn-soft-primary open-invoice" data-no="${esc(i.invoice_no)}">Open</button></td>
            </tr>`).join('');

        $('listCard').style.display = 'block';
    }

    function statusBadge(status) {
        const map = { PENDING: 'bg-warning-subtle text-warning', APPROVED: 'bg-info-subtle text-info', PAID: 'bg-success-subtle text-success', CANCELLED: 'bg-danger-subtle text-danger' };
        return status ? `<span class="badge ${map[status] || 'bg-light text-muted'}">${esc(status)}</span>` : '<span class="text-muted">-</span>';
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
        $('invDoctorShare').textContent = money(inv.doctor_payment_amount);
        $('invSettledBy').textContent = inv.settled_by || '-';
        $('cancelledWarning').style.display = inv.cancelled ? 'block' : 'none';
        $('dueInfo').style.display = !inv.cancelled && inv.due_amount > 0 ? 'block' : 'none';

        $('linesBody').innerHTML = inv.lines.map((l) => {

            let action;

            if (l.editable) {
                const opts = l.options.map((o) => `
                    <option value="${o.doctor_id}" ${o.doctor_id === l.doctor_id ? 'selected' : ''}>
                        ${esc(o.doctor_name)}${o.payment_value !== null ? ` (${money(o.payment_value)})` : ''}
                    </option>`).join('');

                // Manual category: Admin types the doctor amount (a rule's
                // rate is only pre-filled as a suggestion).
                const amountBox = l.is_manual ? `
                    <div class="input-group input-group-sm mt-1">
                        <span class="input-group-text">Doctor amount</span>
                        <input type="number" min="0" step="0.01" max="${l.amount}" class="form-control line-amount"
                               data-line="${l.id}" value="${l.doctor_id ? l.payment_value : ''}">
                    </div>` : '';

                action = `
                    <td data-label="New Doctor">
                        <select class="form-select form-select-sm line-doctor" data-line="${l.id}">
                            <option value="">-- Select doctor --</option>${opts}
                        </select>
                        ${amountBox}
                        ${l.is_manual ? '<div class="small text-muted mt-1">Manual item -- amount typed by Admin</div>' : ''}
                    </td>
                    <td><button type="button" class="btn btn-sm btn-success save-line" data-line="${l.id}"><i class="ri-save-line"></i> Save</button></td>`;
            } else {
                action = `<td data-label="New Doctor" colspan="2" class="text-muted small">${esc(l.blocked_reason)}</td>`;
            }

            return `
                <tr class="${l.is_package_component ? 'text-muted' : ''}">
                    <td data-label="Test">${esc(l.item_description)}<br><small class="text-muted">${esc(l.item_code_sub)}</small></td>
                    <td data-label="Amount" class="text-end">${money(l.amount)}</td>
                    <td data-label="Current Doctor">${l.doctor_name ? esc(l.doctor_name) : '<span class="badge bg-danger-subtle text-danger">No doctor</span>'}</td>
                    <td data-label="Doctor Payable" class="text-end">${money(l.payment_value)}${l.waived ? ' <span class="badge bg-secondary">Waived</span>' : ''}</td>
                    <td data-label="Payable Status">${statusBadge(l.payable_status)}</td>
                    ${action}
                </tr>`;
        }).join('');

        $('invoiceCard').style.display = 'block';
    }

    async function saveLine(lineId) {

        const line = currentInvoice.lines.find((l) => l.id === lineId);
        const select = document.querySelector(`.line-doctor[data-line="${lineId}"]`);
        const doctorId = parseInt(select.value, 10);

        if (!doctorId) {
            Swal.fire({ icon: 'warning', title: 'Select a doctor first' });
            return;
        }

        const option = line.options.find((o) => o.doctor_id === doctorId);

        let newAmount = option.payment_value;

        if (line.is_manual) {
            const raw = document.querySelector(`.line-amount[data-line="${lineId}"]`).value.trim();
            newAmount = parseFloat(raw);

            if (raw === '' || isNaN(newAmount) || newAmount < 0) {
                Swal.fire({ icon: 'warning', title: 'Enter the doctor amount' });
                return;
            }
            if (newAmount > line.amount) {
                Swal.fire({ icon: 'warning', title: 'Amount too high', text: `Doctor amount cannot be more than the billed amount (${money(line.amount)}).` });
                return;
            }
        }

        const confirm = await Swal.fire({
            icon: 'question',
            title: !line.doctor_id ? 'Add doctor?' : (line.doctor_id === doctorId ? 'Change doctor amount?' : 'Change doctor?'),
            html: `
                <div class="text-start small">
                    <div><b>Test:</b> ${esc(line.item_description)}</div>
                    <div><b>Doctor:</b> ${esc(line.doctor_name || 'None')} &rarr; ${esc(option.doctor_name)}</div>
                    <div><b>Doctor payable:</b> ${money(line.payment_value)} &rarr; ${money(newAmount)}</div>
                    <div class="mt-2 text-muted">The patient's amount does not change. The payable will be settled by
                    <b>${esc(currentInvoice.settled_by || 'the user who collects the final payment')}</b>.</div>
                </div>`,
            input: 'textarea',
            inputLabel: 'Reason (required)',
            inputPlaceholder: 'e.g. Doctor not selected at billing',
            inputValidator: (v) => (!v || v.trim().length < 5) ? 'Enter a reason (at least 5 characters).' : undefined,
            showCancelButton: true,
            confirmButtonText: 'Save',
        });

        if (!confirm.isConfirmed) return;

        Swal.fire({ title: 'Saving...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });

        try {
            const res = await fetch('/invoice-doctor-correction/update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({
                    invoice_detail_id: lineId,
                    doctor_id: doctorId,
                    payment_value: line.is_manual ? newAmount : null,
                    reason: confirm.value.trim(),
                }),
            });
            const data = await res.json();

            if (!res.ok || !data.status) {
                Swal.fire({ icon: 'error', title: 'Not saved', text: data.message || 'Could not save.' });
                return;
            }

            renderInvoice(data.invoice);
            Swal.fire({ icon: 'success', title: 'Saved', text: data.message });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Not saved', text: e.message });
        }
    }

    // Manual line: picking a doctor pre-fills that doctor's rule rate as a
    // suggestion (or the line's current amount for its current doctor).
    document.addEventListener('change', (e) => {
        const select = e.target.closest('.line-doctor');
        if (!select || !currentInvoice) return;

        const line = currentInvoice.lines.find((l) => l.id === parseInt(select.dataset.line, 10));
        const box = document.querySelector(`.line-amount[data-line="${select.dataset.line}"]`);
        if (!line || !line.is_manual || !box) return;

        const doctorId = parseInt(select.value, 10);
        const option = line.options.find((o) => o.doctor_id === doctorId);

        if (doctorId && doctorId === line.doctor_id) {
            box.value = line.payment_value;
        } else if (option && option.payment_value !== null) {
            box.value = option.payment_value;
        }
    });

    $('searchBtn').addEventListener('click', () => search());
    $('searchInput').addEventListener('keydown', (e) => { if (e.key === 'Enter') search(); });

    document.addEventListener('click', (e) => {
        const open = e.target.closest('.open-invoice');
        if (open) {
            $('searchInput').value = open.dataset.no;
            search(open.dataset.no);
            return;
        }
        const save = e.target.closest('.save-line');
        if (save) saveLine(parseInt(save.dataset.line, 10));
    });

})();
