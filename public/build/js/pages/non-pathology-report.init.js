"use strict";

/*
|--------------------------------------------------------------------------
| REST TEST RESULT ENTRY (Non-Pathology narrative reports)
|--------------------------------------------------------------------------
| Narrative report entry (Clinical History / Findings / Impression) for
| every Non-Pathology category with no structured parameter grid -- X-Ray,
| Dental, Vaccination, ECG, Misc, etc. -- one independently completable/
| confirmable/printable card per billed line, mirroring usg-report.init.js's
| design exactly (Pathology and USG each already have their own separate
| module/page; this is the "everything else" one).
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

// Without an explicit Accept header, fetch() doesn't tell Laravel this is an
// AJAX call -- a validation failure then 302-redirects to a normal HTML page
// instead of returning JSON, response.json() throws, and (uncaught) whatever
// loading indicator/disabled button is showing stays stuck forever. This
// forces the Accept header and turns a non-JSON response into a clear,
// catchable error instead.
async function fetchJson(url, options = {}) {
    const headers = Object.assign({ 'Accept': 'application/json' }, options.headers || {});
    const response = await fetch(url, Object.assign({}, options, { headers }));

    let result;
    try {
        result = await response.json();
    } catch (e) {
        throw new Error(`Unexpected server response (HTTP ${response.status}). Please try again.`);
    }

    return { response, result };
}

/*
|--------------------------------------------------------------------------
| RICH-TEXT EDITOR -- Clinical History / Findings / Impression
|--------------------------------------------------------------------------
*/

const {
    ClassicEditor, Essentials, Paragraph, Bold, Italic, Underline, Alignment, FontSize, FontColor, Heading, List, Undo,
    Table, TableToolbar, TableProperties, TableCellProperties, Indent, IndentBlock
} = CKEDITOR;

const RICH_EDITOR_CONFIG = {
    licenseKey: 'GPL',
    plugins: [
        Essentials, Paragraph, Bold, Italic, Underline, Alignment, FontSize, FontColor, Heading, List, Undo,
        Table, TableToolbar, TableProperties, TableCellProperties, Indent, IndentBlock
    ],
    toolbar: [
        'heading', '|',
        'bold', 'italic', 'underline', '|',
        'alignment', '|',
        'fontSize', 'fontColor', '|',
        'bulletedList', 'numberedList', '|',
        'outdent', 'indent', '|',
        'insertTable', '|',
        'undo', 'redo'
    ],
    table: {
        contentToolbar: [
            'tableColumn', 'tableRow', 'mergeTableCells',
            'tableProperties', 'tableCellProperties'
        ]
    }
};

// CKEditor5 only auto-binds Tab to indent inside a list -- for plain
// paragraphs/headings Tab just moves focus out of the editor by default.
// This makes Tab/Shift+Tab indent/outdent the current block everywhere,
// falling through to normal focus navigation when indent isn't applicable
// (e.g. already at the base level).
function bindTabIndent(editor) {

    editor.keystrokes.set('Tab', (data, cancel) => {
        if (editor.commands.get('indent').isEnabled) {
            editor.execute('indent');
            cancel();
        }
    }, { priority: 'high' });

    editor.keystrokes.set('Shift+Tab', (data, cancel) => {
        if (editor.commands.get('outdent').isEnabled) {
            editor.execute('outdent');
            cancel();
        }
    }, { priority: 'high' });

    return editor;
}

// Records saved before rich-text editing existed are plain text with literal
// newlines -- each line becomes its own paragraph so it displays the same
// way it would have as plain text. Already-HTML content passes through
// untouched.
function toEditorHtml(text) {
    if (!text) return '';
    if (text.includes('<')) return text;
    return text.split('\n').map(line => `<p>${escapeHtml(line)}</p>`).join('');
}

let nonPathEditors = new Map();

function destroyAllNonPathEditors() {
    nonPathEditors.forEach(fields => {
        fields.clinical_history.destroy();
        fields.findings.destroy();
        fields.impression.destroy();
    });
    nonPathEditors.clear();
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

    return `<span class="badge ${cls}">${escapeHtml(row.result_status)} (${row.confirmed_tests}/${row.total_tests})</span>`;
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

    const { result } = await fetchJson(`/non-pathology-report/list?${params.toString()}`);

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
            <td>${escapeHtml(row.test_category ?? '')}</td>
            <td>${escapeHtml(row.test_description ?? '')}</td>
            <td class="text-center">${row.total_tests}</td>
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
| ENTRY MODAL -- search an invoice, render one card per qualifying line
|--------------------------------------------------------------------------
*/

async function searchInvoice(invoiceNo) {

    document.getElementById('invoiceNotFoundMsg').style.display = 'none';
    document.getElementById('invoiceInfoWrap').style.display = 'none';
    document.getElementById('noQualifyingMsg').style.display = 'none';

    destroyAllNonPathEditors();
    document.getElementById('nonPathologyReportsWrap').innerHTML = '';

    const { result } = await fetchJson('/non-pathology-report/search', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken()
        },
        body: JSON.stringify({ invoice_no: invoiceNo })
    });

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

        document.getElementById('noQualifyingMsg').style.display = 'block';
        return;
    }

    let wrap = document.getElementById('nonPathologyReportsWrap');
    let template = document.getElementById('nonPathReportCardTemplate');

    for (const line of result.lines) {

        let frag = template.content.cloneNode(true);
        let root = frag.querySelector('.nonpath-report-card');

        root.dataset.invoiceDetailId = line.invoice_detail_id;
        root.dataset.findingId = line.finding_id ?? '';

        root.querySelector('.nonpath-item-description').innerText = line.item_description ?? '';
        root.querySelector('.nonpath-item-code-sub').innerText = line.item_code_sub ? `(${line.item_code_sub})` : '';
        root.querySelector('.nonpath-doctor-name').innerText = line.doctor_name ?? '-';

        wrap.appendChild(frag);

        let clinicalHistoryEditor = bindTabIndent(await ClassicEditor.create(root.querySelector('.nonpath-clinical-history'), RICH_EDITOR_CONFIG));
        let findingsEditor = bindTabIndent(await ClassicEditor.create(root.querySelector('.nonpath-findings'), RICH_EDITOR_CONFIG));
        let impressionEditor = bindTabIndent(await ClassicEditor.create(root.querySelector('.nonpath-impression'), RICH_EDITOR_CONFIG));

        nonPathEditors.set(root, {
            clinical_history: clinicalHistoryEditor,
            findings: findingsEditor,
            impression: impressionEditor
        });

        clinicalHistoryEditor.setData(toEditorHtml(line.clinical_history ?? ''));
        findingsEditor.setData(toEditorHtml(line.findings ?? ''));
        impressionEditor.setData(toEditorHtml(line.impression ?? ''));

        if (line.confirmed_at) {
            lockNonPathCard(root, line.finding_id);
        } else if (line.item_code_sub) {
            loadNonPathTemplatesForPicker(root.querySelector('.nonpath-template-picker'), line.item_code_sub);
        }
    }
}

function lockNonPathCard(root, findingId) {

    let editors = nonPathEditors.get(root);

    if (editors) {
        editors.clinical_history.enableReadOnlyMode('nonpath-locked');
        editors.findings.enableReadOnlyMode('nonpath-locked');
        editors.impression.enableReadOnlyMode('nonpath-locked');
    }

    root.querySelector('.nonpath-template-picker-wrap').style.display = 'none';
    root.querySelector('.nonpath-confirmed-badge').style.display = 'inline-block';
    root.querySelector('.nonpath-save-btn').style.display = 'none';
    root.querySelector('.nonpath-confirm-btn').style.display = 'none';

    if (!findingId) return;

    let printBtn = root.querySelector('.nonpath-print-btn');
    printBtn.href = `/non-pathology-report/print/${findingId}`;
    printBtn.style.display = 'inline-block';

    root.querySelector('.nonpath-whatsapp-btn').style.display = 'inline-block';
}

/*
|--------------------------------------------------------------------------
| TEMPLATE PICKER
|--------------------------------------------------------------------------
*/

function loadNonPathTemplatesForPicker(picker, itemCodeSub) {

    fetch(`/non-pathology-report-template/for-test/${itemCodeSub}`)
        .then(r => r.json())
        .then(result => {

            if (!result.status || !result.data.length) return;

            picker.nonPathTemplates = {};

            result.data.forEach(tpl => {

                picker.nonPathTemplates[tpl.id] = tpl;

                let option = document.createElement('option');
                option.value = tpl.id;
                option.textContent = tpl.title;
                picker.appendChild(option);
            });
        })
        .catch(() => {});
}

document.addEventListener('change', async function (e) {

    let picker = e.target.closest('.nonpath-template-picker');

    if (!picker || !picker.value) return;

    let template = (picker.nonPathTemplates || {})[picker.value];

    if (!template) {
        picker.value = '';
        return;
    }

    let root = picker.closest('.nonpath-report-card');
    let editors = nonPathEditors.get(root);

    if (!editors) {
        picker.value = '';
        return;
    }

    let hasExisting = editors.clinical_history.getData().trim()
        || editors.findings.getData().trim()
        || editors.impression.getData().trim();

    if (hasExisting) {

        let confirmResult = await Swal.fire({
            icon: 'warning',
            title: 'Replace current content?',
            text: 'This will replace the current Clinical History, Findings, and Impression with the selected template.',
            showCancelButton: true,
            confirmButtonText: 'Yes, Replace'
        });

        if (!confirmResult.isConfirmed) {
            picker.value = '';
            return;
        }
    }

    editors.clinical_history.setData(toEditorHtml(template.clinical_history ?? ''));
    editors.findings.setData(toEditorHtml(template.findings ?? ''));
    editors.impression.setData(toEditorHtml(template.impression ?? ''));

    picker.value = '';
});

/*
|--------------------------------------------------------------------------
| MODAL OPEN / CLOSE
|--------------------------------------------------------------------------
*/

document.getElementById('nonPathReportModal').addEventListener('shown.bs.modal', function () {

    let invoiceNo = document.getElementById('invoiceNoInput').value;

    if (invoiceNo) {
        searchInvoice(invoiceNo);
    }
});

document.getElementById('nonPathReportModal').addEventListener('hidden.bs.modal', function () {
    loadDashboard(dashPage);
});

document.addEventListener('click', function (e) {

    let enterBtn = e.target.closest('.enter-report-btn');
    if (enterBtn) {

        document.getElementById('invoiceNoInput').value = enterBtn.dataset.invoiceNo;

        let modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('nonPathReportModal'));
        modal.show();

        return;
    }
});

// Deep link from the Test Report Dashboard's Print/WhatsApp actions
// (test-report-dashboard.init.js) on a Non-Pathology invoice -- setting the
// input then showing the modal is enough, since 'shown.bs.modal' above
// already runs searchInvoice() off whatever is in invoiceNoInput.
let deepLinkInvoiceNo = new URLSearchParams(location.search).get('open');

if (deepLinkInvoiceNo) {

    document.getElementById('invoiceNoInput').value = deepLinkInvoiceNo;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('nonPathReportModal')).show();
}

/*
|--------------------------------------------------------------------------
| SAVE / CONFIRM / WHATSAPP (delegated -- cards are added dynamically)
|--------------------------------------------------------------------------
*/

document.addEventListener('click', async function (e) {

    let saveBtn = e.target.closest('.nonpath-save-btn');
    if (saveBtn) {

        let root = saveBtn.closest('.nonpath-report-card');
        let editors = nonPathEditors.get(root);

        saveBtn.disabled = true;

        const { result } = await fetchJson('/non-pathology-report/save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken()
            },
            body: JSON.stringify({
                invoice_detail_id: root.dataset.invoiceDetailId,
                clinical_history: editors.clinical_history.getData(),
                findings: editors.findings.getData(),
                impression: editors.impression.getData()
            })
        });

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

    let confirmBtn = e.target.closest('.nonpath-confirm-btn');
    if (confirmBtn) {

        let root = confirmBtn.closest('.nonpath-report-card');

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
            title: 'Confirm this report?',
            text: 'Once confirmed, it can no longer be edited.',
            showCancelButton: true,
            confirmButtonText: 'Yes, Confirm & Lock'
        });

        if (!confirmResult.isConfirmed) {
            return;
        }

        confirmBtn.disabled = true;

        const { result } = await fetchJson('/non-pathology-report/confirm', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken()
            },
            body: JSON.stringify({ invoice_detail_id: root.dataset.invoiceDetailId })
        });

        confirmBtn.disabled = false;

        if (result.status) {

            lockNonPathCard(root, root.dataset.findingId);

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

    let whatsappBtn = e.target.closest('.nonpath-whatsapp-btn');
    if (whatsappBtn) {

        let root = whatsappBtn.closest('.nonpath-report-card');
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

        const { result } = await fetchJson(`/non-pathology-report/send-whatsapp/${findingId}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() }
        });

        whatsappBtn.disabled = false;

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Sent' : 'Error',
            text: result.message
        });
    }
});

loadDashboard(1);
