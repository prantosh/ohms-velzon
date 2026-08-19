"use strict";

/*
|--------------------------------------------------------------------------
| CARDIOLOGY (ECHO) REPORT
|--------------------------------------------------------------------------
| Structured 16-field echo report entry -- one independently completable/
| confirmable/printable card per billed Cardiology (Echo) line, mirroring
| usg-report.init.js's dashboard/entry-modal/lifecycle pattern but with a
| field list discovered from the DOM ([data-cardio-field]) instead of 3
| hardcoded fields, since hand-listing 16 keys everywhere would be unwieldy.
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
| RICH-TEXT EDITORS -- one per [data-cardio-field] textarea (16 of them)
|--------------------------------------------------------------------------
| One card per billed Cardiology (Echo) line, each with its own independent
| set of CKEditor instances (cards are cloned dynamically from
| cardioStudyCardTemplate and can come and go as the user searches different
| invoices), tracked on the card's own root DOM node so instance lifetime
| follows the card.
*/

const {
    ClassicEditor, Essentials, Paragraph, Bold, Italic, Underline, Alignment, FontSize, List, Undo,
    Table, TableToolbar, TableProperties, TableCellProperties
} = CKEDITOR;

const CARDIO_EDITOR_CONFIG = {
    licenseKey: 'GPL',
    plugins: [
        Essentials, Paragraph, Bold, Italic, Underline, Alignment, FontSize, List, Undo,
        Table, TableToolbar, TableProperties, TableCellProperties
    ],
    toolbar: [
        'bold', 'italic', 'underline', '|',
        'alignment', '|',
        'fontSize', '|',
        'bulletedList', 'numberedList', '|',
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

const CARDIO_LOCK_ID = 'cardio-confirmed';

// Records saved before rich-text editing existed are plain text with literal
// newlines. Each line becomes its own paragraph (not just a <br> within one
// shared paragraph) so alignment/formatting can target one line without
// dragging the rest of the text along with it. Already-HTML content passes
// through untouched. Mirrors usgToEditorHtml() exactly.
function cardioToEditorHtml(text) {
    if (!text) return '';
    if (text.includes('<')) return text;
    return text.split('\n').map(line => `<p>${escapeHtml(line)}</p>`).join('');
}

function cardioFieldKeys(root) {
    return Array.from(root.querySelectorAll('[data-cardio-field]')).map(el => el.dataset.cardioField);
}

async function createCardEditors(root) {

    root.cardioEditors = {};

    for (const key of cardioFieldKeys(root)) {
        root.cardioEditors[key] = await ClassicEditor.create(
            root.querySelector(`[data-cardio-field="${key}"]`),
            CARDIO_EDITOR_CONFIG
        );
    }
}

async function destroyAllCardEditors() {

    let cards = document.querySelectorAll('#studiesWrap .cardio-study-card');

    for (const root of cards) {

        if (root.cardioEditors) {
            await Promise.all(Object.values(root.cardioEditors).map(ed => ed.destroy()));
        }
    }
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

    const { result } = await fetchJson(`/cardiology-report/list?${params.toString()}`);

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
            <td>${escapeHtml(row.test_description ?? '')}</td>
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
| ENTRY MODAL -- search an invoice, render one card per Cardiology line
|--------------------------------------------------------------------------
*/

async function searchInvoice(invoiceNo) {

    document.getElementById('invoiceNotFoundMsg').style.display = 'none';
    document.getElementById('invoiceInfoWrap').style.display = 'none';
    document.getElementById('noStudiesMsg').style.display = 'none';

    // Destroy any editors from a previous search before wiping the cards
    // out from under them, or the old CKEditor instances leak.
    await destroyAllCardEditors();
    document.getElementById('studiesWrap').innerHTML = '';

    const { result } = await fetchJson('/cardiology-report/search', {
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

        document.getElementById('noStudiesMsg').style.display = 'block';
        return;
    }

    let wrap = document.getElementById('studiesWrap');
    let template = document.getElementById('cardioStudyCardTemplate');

    result.lines.forEach(async function (line) {

        let card = template.content.cloneNode(true);
        let root = card.querySelector('.cardio-study-card');

        root.dataset.invoiceDetailId = line.invoice_detail_id;
        root.dataset.findingId = line.finding_id ?? '';
        root.dataset.itemCodeSub = line.item_code_sub ?? '';

        root.querySelector('.study-item-description').innerText = line.item_description ?? '';
        root.querySelector('.study-item-code-sub').innerText = line.item_code_sub ? `(${line.item_code_sub})` : '';
        root.querySelector('.study-doctor-name').innerText = line.doctor_name ?? '-';

        wrap.appendChild(card);

        // CKEditor must be created against an attached DOM node.
        await createCardEditors(root);

        cardioFieldKeys(root).forEach(key => {
            root.cardioEditors[key].setData(cardioToEditorHtml(line[key]));
        });

        if (line.confirmed_at) {

            lockStudyCard(root, line.finding_id);

        } else if (line.item_code_sub) {

            loadTemplatesForCard(root, line.item_code_sub);
        }
    });
}

/*
|--------------------------------------------------------------------------
| TEMPLATE PICKER -- lets staff pre-fill all 16 fields from a reusable
| template (managed at /cardiology-report-template), then edit freely for
| the specific patient. See CardiologyReportTemplateController::forStudy().
|--------------------------------------------------------------------------
*/

async function loadTemplatesForCard(root, itemCodeSub) {

    const { result } = await fetchJson(`/cardiology-report-template/for-study/${itemCodeSub}`);

    if (!result.status || !result.data.length) {
        return;
    }

    let picker = root.querySelector('.study-template-picker');

    // Cache each template's content on the picker element itself so
    // selecting one doesn't need a second round-trip.
    picker.cardioTemplates = {};

    result.data.forEach(function (tpl) {

        picker.cardioTemplates[tpl.id] = tpl;

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

    let root = picker.closest('.cardio-study-card');

    if (!root || !root.cardioEditors) {
        return;
    }

    let tpl = (picker.cardioTemplates || {})[picker.value];

    if (!tpl) {
        picker.value = '';
        return;
    }

    // A CKEditor with no real content still returns a non-empty string
    // (e.g. "<p>&nbsp;</p>"), so strip tags before checking for anything
    // the user would actually notice being overwritten.
    let hasExistingContent = cardioFieldKeys(root).some(
        field => root.cardioEditors[field].getData().replace(/<[^>]*>/g, '').trim()
    );

    if (hasExistingContent) {

        let confirmResult = await Swal.fire({
            icon: 'warning',
            title: 'Replace current text?',
            text: 'This will replace all current field content with the selected template.',
            showCancelButton: true,
            confirmButtonText: 'Yes, Replace'
        });

        if (!confirmResult.isConfirmed) {
            picker.value = '';
            return;
        }
    }

    cardioFieldKeys(root).forEach(key => {
        root.cardioEditors[key].setData(cardioToEditorHtml(tpl[key]));
    });

    picker.value = '';
});

function lockStudyCard(root, findingId) {

    if (root.cardioEditors) {
        Object.values(root.cardioEditors).forEach(ed => ed.enableReadOnlyMode(CARDIO_LOCK_ID));
    }

    root.querySelector('.study-template-picker').style.display = 'none';
    root.querySelector('.study-confirmed-badge').style.display = 'inline-block';
    root.querySelector('.study-save-btn').style.display = 'none';
    root.querySelector('.study-preview-btn').style.display = 'none';
    root.querySelector('.study-confirm-btn').style.display = 'none';

    let printBtn = root.querySelector('.study-print-btn');
    printBtn.href = `/cardiology-report/print/${findingId}`;
    printBtn.style.display = 'inline-block';

    root.querySelector('.study-whatsapp-btn').style.display = 'inline-block';
}

document.getElementById('cardioReportModal').addEventListener('shown.bs.modal', function () {

    let invoiceNo = document.getElementById('invoiceNoInput').value;

    if (invoiceNo) {
        searchInvoice(invoiceNo);
    }
});

document.getElementById('cardioReportModal').addEventListener('hidden.bs.modal', function () {
    loadDashboard(dashPage);
});

// Bootstrap doesn't natively stack modals -- bump the preview modal and its
// backdrop above the entry modal it's opened on top of, and revoke the blob
// URL on close so repeated previews don't leak memory.
document.getElementById('cardioPreviewModal').addEventListener('shown.bs.modal', function () {

    let backdrops = document.querySelectorAll('.modal-backdrop');
    let topBackdrop = backdrops[backdrops.length - 1];

    if (topBackdrop) {
        topBackdrop.style.zIndex = 1070;
    }

    this.style.zIndex = 1075;
});

document.getElementById('cardioPreviewModal').addEventListener('hidden.bs.modal', function () {

    let frame = document.getElementById('cardioPreviewFrame');

    if (frame.src) {
        URL.revokeObjectURL(frame.src);
        frame.src = '';
    }
});

document.addEventListener('click', function (e) {

    let enterBtn = e.target.closest('.enter-report-btn');
    if (enterBtn) {

        document.getElementById('invoiceNoInput').value = enterBtn.dataset.invoiceNo;

        let modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('cardioReportModal'));
        modal.show();

        return;
    }
});

/*
|--------------------------------------------------------------------------
| SAVE / CONFIRM (delegated -- study cards are added dynamically)
|--------------------------------------------------------------------------
*/

function cardPayload(root) {

    let payload = { invoice_detail_id: root.dataset.invoiceDetailId };

    cardioFieldKeys(root).forEach(key => {
        payload[key] = root.cardioEditors[key].getData();
    });

    return payload;
}

document.addEventListener('click', async function (e) {

    let saveBtn = e.target.closest('.study-save-btn');
    if (saveBtn) {

        let root = saveBtn.closest('.cardio-study-card');

        if (!root.cardioEditors) {
            return;
        }

        saveBtn.disabled = true;

        try {

            const { result } = await fetchJson('/cardiology-report/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken()
                },
                body: JSON.stringify(cardPayload(root))
            });

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

        } catch (err) {

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: err.message || 'Unable to save report.'
            });

        } finally {

            saveBtn.disabled = false;
        }

        return;
    }

    let previewBtn = e.target.closest('.study-preview-btn');
    if (previewBtn) {

        let root = previewBtn.closest('.cardio-study-card');

        if (!root.cardioEditors) {
            return;
        }

        previewBtn.disabled = true;

        try {

            const response = await fetch('/cardiology-report/preview', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'Accept': 'application/pdf, application/json'
                },
                body: JSON.stringify(cardPayload(root))
            });

            if (!response.ok || (response.headers.get('Content-Type') || '').includes('application/json')) {

                let message = `Unable to generate preview (HTTP ${response.status}).`;

                try {
                    const result = await response.json();
                    message = result.message ?? (result.errors ? Object.values(result.errors).flat().join(', ') : message);
                } catch (e) {
                    // Non-JSON error body (e.g. a redirected HTML page) -- fall
                    // back to the generic message above instead of throwing.
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: message
                });

                return;
            }

            const blob = await response.blob();
            const blobUrl = URL.createObjectURL(blob);

            // An iframe (same top-level browsing context) rather than a new
            // tab/window -- Chromium blocks navigating a *new* top-level
            // context to a blob: URL outright, even same-origin/opener.
            document.getElementById('cardioPreviewFrame').src = blobUrl;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('cardioPreviewModal')).show();

        } finally {

            previewBtn.disabled = false;
        }

        return;
    }

    let confirmBtn = e.target.closest('.study-confirm-btn');
    if (confirmBtn) {

        let root = confirmBtn.closest('.cardio-study-card');

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
            title: 'Confirm this Cardiology report?',
            text: 'Once confirmed, it can no longer be edited.',
            showCancelButton: true,
            confirmButtonText: 'Yes, Confirm & Lock'
        });

        if (!confirmResult.isConfirmed) {
            return;
        }

        confirmBtn.disabled = true;

        try {

            const { result } = await fetchJson('/cardiology-report/confirm', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken()
                },
                body: JSON.stringify({ invoice_detail_id: root.dataset.invoiceDetailId })
            });

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

        } catch (err) {

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: err.message || 'Unable to confirm report.'
            });

        } finally {

            confirmBtn.disabled = false;
        }

        return;
    }

    let whatsappBtn = e.target.closest('.study-whatsapp-btn');
    if (whatsappBtn) {

        let root = whatsappBtn.closest('.cardio-study-card');
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

        try {

            const { result } = await fetchJson(`/cardiology-report/send-whatsapp/${findingId}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken() }
            });

            Swal.fire({
                icon: result.status ? 'success' : 'error',
                title: result.status ? 'Sent' : 'Error',
                text: result.message
            });

        } catch (err) {

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: err.message || 'Unable to send WhatsApp message.'
            });

        } finally {

            whatsappBtn.disabled = false;
        }
    }
});

loadDashboard(1);
