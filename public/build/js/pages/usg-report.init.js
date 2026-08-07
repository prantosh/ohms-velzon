"use strict";

/*
|--------------------------------------------------------------------------
| USG REPORT
|--------------------------------------------------------------------------
| Narrative report entry (Clinical History / Findings / Impression) for
| USG studies -- one independently completable/confirmable/printable card
| per billed USG line, unlike Pathology's single tabular per-invoice
| report (see TestResultEntryController / test-result-entry.init.js,
| untouched by this file).
*/

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

function escapeHtml(value) {

    return (value ?? '').toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/*
|--------------------------------------------------------------------------
| DASHBOARD
|--------------------------------------------------------------------------
*/

let dashRange = '3';
let dashSearch = '';
let dashPage = 1;
let dashLastPage = 1;

function resultStatusBadgeHtml(row) {

    let cls = {
        'Pending': 'result-status-Pending',
        'Partial': 'result-status-Partial',
        'Complete': 'result-status-Complete'
    }[row.result_status] ?? 'result-status-Pending';

    return `<span class="badge ${cls}">${escapeHtml(row.result_status)} (${row.confirmed_studies}/${row.total_studies})</span>`;
}

async function loadDashboard(page = 1) {

    dashPage = page;

    let params = new URLSearchParams({
        range: dashRange,
        page: dashPage
    });

    if (dashSearch) {
        params.set('search', dashSearch);
    }

    const response = await fetch(`/usg-report/list?${params.toString()}`);
    const result = await response.json();

    let tbody = document.getElementById('dashTableBody');
    tbody.innerHTML = '';

    if (!result.status || !result.data.length) {

        document.getElementById('dashNoDataWrap').style.display = 'block';
        document.getElementById('dashPaginationInfo').innerText = '';
        dashLastPage = 1;
        return;
    }

    document.getElementById('dashNoDataWrap').style.display = 'none';

    result.data.forEach(function (row) {

        tbody.innerHTML += `
        <tr>
            <td class="fw-semibold">${escapeHtml(row.invoice_no)}</td>
            <td>${escapeHtml(row.invoice_date ?? '')}</td>
            <td>${escapeHtml(row.patient_name ?? '')}</td>
            <td>${escapeHtml(row.patient_mobile_no ?? '')}</td>
            <td class="text-center">${row.total_studies}</td>
            <td class="text-center">${resultStatusBadgeHtml(row)}</td>
            <td class="text-center text-nowrap">
                <button type="button" class="btn btn-sm btn-primary enter-report-btn" data-invoice-no="${escapeHtml(row.invoice_no)}">
                    <i class="ri-edit-2-line"></i>
                    Open
                </button>
            </td>
        </tr>
        `;
    });

    dashLastPage = result.pagination.last_page;

    document.getElementById('dashPaginationInfo').innerText =
        `Page ${result.pagination.current_page} of ${result.pagination.last_page} (${result.pagination.total} total)`;
}

document.querySelectorAll('#rangeButtons [data-range]').forEach(function (btn) {

    btn.addEventListener('click', function () {

        document.querySelectorAll('#rangeButtons [data-range]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        dashRange = btn.dataset.range;
        loadDashboard(1);
    });
});

let dashSearchTimer = null;

document.getElementById('dashSearchInput').addEventListener('input', function () {

    let value = this.value;

    clearTimeout(dashSearchTimer);

    dashSearchTimer = setTimeout(function () {
        dashSearch = value.trim();
        loadDashboard(1);
    }, 400);
});

document.getElementById('dashPrevPage').addEventListener('click', function () {
    if (dashPage > 1) loadDashboard(dashPage - 1);
});

document.getElementById('dashNextPage').addEventListener('click', function () {
    if (dashPage < dashLastPage) loadDashboard(dashPage + 1);
});

/*
|--------------------------------------------------------------------------
| ENTRY MODAL -- search an invoice, render one card per USG line
|--------------------------------------------------------------------------
*/

async function searchInvoice(invoiceNo) {

    document.getElementById('invoiceNotFoundMsg').style.display = 'none';
    document.getElementById('invoiceInfoWrap').style.display = 'none';
    document.getElementById('noStudiesMsg').style.display = 'none';
    document.getElementById('studiesWrap').innerHTML = '';

    const response = await fetch('/usg-report/search', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken()
        },
        body: JSON.stringify({ invoice_no: invoiceNo })
    });

    const result = await response.json();

    if (!result.status) {

        document.getElementById('invoiceNotFoundMsg').style.display = 'block';
        return;
    }

    let inv = result.invoice;

    document.getElementById('info-invoice_no').innerText = inv.invoice_no ?? '';
    document.getElementById('info-invoice_date').innerText = inv.invoice_date ?? '';
    document.getElementById('info-patient_name').innerText = inv.patient_name ?? '';
    document.getElementById('info-patient_age_gender').innerText =
        (inv.patient_age ?? '') + ' / ' + (inv.patient_gender ?? '');

    document.getElementById('invoiceInfoWrap').style.display = 'block';

    if (!result.lines.length) {

        document.getElementById('noStudiesMsg').style.display = 'block';
        return;
    }

    let wrap = document.getElementById('studiesWrap');
    let template = document.getElementById('usgStudyCardTemplate');

    result.lines.forEach(function (line) {

        let card = template.content.cloneNode(true);
        let root = card.querySelector('.usg-study-card');

        root.dataset.invoiceDetailId = line.invoice_detail_id;
        root.dataset.findingId = line.finding_id ?? '';
        root.dataset.itemCodeSub = line.item_code_sub ?? '';

        root.querySelector('.study-item-description').innerText = line.item_description ?? '';
        root.querySelector('.study-item-code-sub').innerText = line.item_code_sub ? `(${line.item_code_sub})` : '';
        root.querySelector('.study-doctor-name').innerText = line.doctor_name ?? '-';

        root.querySelector('.study-clinical-history').value = line.clinical_history ?? '';
        root.querySelector('.study-findings').value = line.findings ?? '';
        root.querySelector('.study-impression').value = line.impression ?? '';

        if (line.confirmed_at) {

            lockStudyCard(root, line.finding_id);

        } else if (line.item_code_sub) {

            loadTemplatesForCard(root, line.item_code_sub);
        }

        wrap.appendChild(card);
    });
}

/*
|--------------------------------------------------------------------------
| TEMPLATE PICKER -- lets staff pre-fill Clinical History/Findings/
| Impression from a reusable template (managed at /usg-report-template),
| then edit freely for the specific patient. See UsgReportTemplateController::forStudy().
|--------------------------------------------------------------------------
*/

async function loadTemplatesForCard(root, itemCodeSub) {

    const response = await fetch(`/usg-report-template/for-study/${itemCodeSub}`);
    const result = await response.json();

    if (!result.status || !result.data.length) {
        return;
    }

    let picker = root.querySelector('.study-template-picker');

    // Cache each template's content on the picker element itself so
    // selecting one doesn't need a second round-trip.
    picker.usgTemplates = {};

    result.data.forEach(function (tpl) {

        picker.usgTemplates[tpl.id] = tpl;

        let option = document.createElement('option');
        option.value = tpl.id;
        option.textContent = tpl.title;
        picker.appendChild(option);
    });
}

document.addEventListener('change', async function (e) {

    let picker = e.target.closest('.study-template-picker');

    if (!picker || !picker.value) {
        return;
    }

    let tpl = (picker.usgTemplates || {})[picker.value];

    if (!tpl) {
        picker.value = '';
        return;
    }

    let root = picker.closest('.usg-study-card');

    let historyField = root.querySelector('.study-clinical-history');
    let findingsField = root.querySelector('.study-findings');
    let impressionField = root.querySelector('.study-impression');

    let hasExistingContent =
        historyField.value.trim() ||
        findingsField.value.trim() ||
        impressionField.value.trim();

    if (hasExistingContent) {

        let confirmResult = await Swal.fire({
            icon: 'warning',
            title: 'Replace current text?',
            text: 'This will replace your current Clinical History / Findings / Impression with the selected template.',
            showCancelButton: true,
            confirmButtonText: 'Yes, Replace'
        });

        if (!confirmResult.isConfirmed) {
            picker.value = '';
            return;
        }
    }

    historyField.value = tpl.clinical_history ?? '';
    findingsField.value = tpl.findings ?? '';
    impressionField.value = tpl.impression ?? '';

    picker.value = '';
});

function lockStudyCard(root, findingId) {

    root.querySelectorAll('textarea').forEach(t => t.disabled = true);
    root.querySelector('.study-template-picker').style.display = 'none';
    root.querySelector('.study-confirmed-badge').style.display = 'inline-block';
    root.querySelector('.study-save-btn').style.display = 'none';
    root.querySelector('.study-confirm-btn').style.display = 'none';

    let printBtn = root.querySelector('.study-print-btn');
    printBtn.href = `/usg-report/print/${findingId}`;
    printBtn.style.display = 'inline-block';

    root.querySelector('.study-whatsapp-btn').style.display = 'inline-block';
}

document.getElementById('usgReportModal').addEventListener('shown.bs.modal', function () {

    let invoiceNo = document.getElementById('invoiceNoInput').value;

    if (invoiceNo) {
        searchInvoice(invoiceNo);
    }
});

document.getElementById('usgReportModal').addEventListener('hidden.bs.modal', function () {
    loadDashboard(dashPage);
});

document.addEventListener('click', function (e) {

    let enterBtn = e.target.closest('.enter-report-btn');
    if (enterBtn) {

        document.getElementById('invoiceNoInput').value = enterBtn.dataset.invoiceNo;

        let modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('usgReportModal'));
        modal.show();

        return;
    }
});

/*
|--------------------------------------------------------------------------
| SAVE / CONFIRM (delegated -- study cards are added dynamically)
|--------------------------------------------------------------------------
*/

document.addEventListener('click', async function (e) {

    let saveBtn = e.target.closest('.study-save-btn');
    if (saveBtn) {

        let root = saveBtn.closest('.usg-study-card');

        saveBtn.disabled = true;

        const response = await fetch('/usg-report/save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken()
            },
            body: JSON.stringify({
                invoice_detail_id: root.dataset.invoiceDetailId,
                clinical_history: root.querySelector('.study-clinical-history').value,
                findings: root.querySelector('.study-findings').value,
                impression: root.querySelector('.study-impression').value
            })
        });

        const result = await response.json();

        saveBtn.disabled = false;

        if (result.status && result.data && result.data.id) {
            root.dataset.findingId = result.data.id;
        }

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Saved' : 'Error',
            text: result.message ?? (result.errors ? Object.values(result.errors).flat().join(', ') : ''),
            timer: result.status ? 1200 : undefined,
            showConfirmButton: !result.status
        });

        return;
    }

    let confirmBtn = e.target.closest('.study-confirm-btn');
    if (confirmBtn) {

        let root = confirmBtn.closest('.usg-study-card');

        if (!root.dataset.findingId) {

            Swal.fire({
                icon: 'warning',
                title: 'Save First',
                text: 'Please save the report before confirming it.'
            });

            return;
        }

        let confirmResult = await Swal.fire({
            icon: 'warning',
            title: 'Confirm this USG report?',
            text: 'Once confirmed, it can no longer be edited.',
            showCancelButton: true,
            confirmButtonText: 'Yes, Confirm & Lock'
        });

        if (!confirmResult.isConfirmed) {
            return;
        }

        confirmBtn.disabled = true;

        const response = await fetch('/usg-report/confirm', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken()
            },
            body: JSON.stringify({ invoice_detail_id: root.dataset.invoiceDetailId })
        });

        const result = await response.json();

        confirmBtn.disabled = false;

        if (result.status) {

            lockStudyCard(root, root.dataset.findingId);

            Swal.fire({
                icon: 'success',
                title: 'Confirmed',
                text: result.message
            });

        } else {

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: result.message
            });
        }

        return;
    }

    let whatsappBtn = e.target.closest('.study-whatsapp-btn');
    if (whatsappBtn) {

        let root = whatsappBtn.closest('.usg-study-card');
        let findingId = root.dataset.findingId;

        whatsappBtn.disabled = true;

        Swal.fire({
            title: 'Please wait...',
            text: 'We are sending WhatsApp message',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        const response = await fetch(`/usg-report/send-whatsapp/${findingId}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() }
        });

        const result = await response.json();

        whatsappBtn.disabled = false;

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Sent' : 'Error',
            text: result.message
        });
    }
});

loadDashboard(1);
