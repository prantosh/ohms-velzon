"use strict";

// Complete-but-not-yet-confirmed reports open read-only ("Show Result")
// -- editing is locked the same as a confirmed report. Only meaningful for
// Non-Pathology today (Pathology's per-report Complete/Confirm state is
// tracked per card, not for the whole invoice).
let isReadOnlyView = false;

function inputsLocked() {
    return isReadOnlyView;
}

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
// loading indicator is showing stays stuck forever. This forces the Accept
// header and turns a non-JSON response into a clear, catchable error instead.
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
| RICH-TEXT EDITOR (Non-Pathology cards' 3 fields, Pathology report content)
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

function renderInvoiceInfo(invoice) {

    document.querySelector('#info-invoice_no').innerText = invoice.invoice_no ?? '';
    document.querySelector('#info-invoice_date').innerText = invoice.invoice_date ?? '';
    document.querySelector('#info-patient_name').innerText = invoice.patient_name ?? '';
    document.querySelector('#info-patient_age_gender').innerText =
        `${invoice.patient_age ?? ''} / ${invoice.patient_gender ?? ''}`;
    document.querySelector('#info-referred_doctor').innerText = invoice.referred_doctor ?? '';
    document.querySelector('#info-status').innerText = invoice.status ?? '';

    document.querySelector('#invoiceInfoWrap').dataset.invoiceId = invoice.id;
    document.querySelector('#invoiceInfoWrap').dataset.invoiceNo = invoice.invoice_no;

    document.querySelector('#invoiceInfoWrap').style.display = 'block';
}

async function searchInvoice() {

    let invoiceNo = document.querySelector('#invoiceNoInput').value.trim();

    document.querySelector('#invoiceInfoWrap').style.display = 'none';
    document.querySelector('#invoiceNotFoundMsg').style.display = 'none';
    document.querySelector('#noQualifyingMsg').style.display = 'none';

    destroyAllNonPathEditors();
    document.querySelector('#nonPathologyReportsWrap').innerHTML = '';
    document.querySelector('#nonPathologyReportsWrap').style.display = 'none';

    destroyAllPathologyEditors();
    document.querySelector('#pathologyReportsWrap').innerHTML = '';
    document.querySelector('#pathologyReportsWrap').style.display = 'none';
    document.querySelector('#pathologyWhatsappBanner').classList.add('d-none');
    pathologyState = null;

    if (!invoiceNo) {
        return;
    }

    if (typeof dashCategory !== 'undefined' && dashCategory === 'NON_PATHOLOGY') {
        await loadNonPathologyReports(invoiceNo);
    } else {
        await loadPathologyReports(invoiceNo);
    }
}

/*
|--------------------------------------------------------------------------
| PATHOLOGY -- narrative, template-driven reports. Replaces the old
| structured analyte grid entirely (see PathologyReportController's class
| doc-comment). A report can cover one billed line or several bundled
| together by the chosen template; organized into tabs by test group so
| staff can find the right template quickly.
|--------------------------------------------------------------------------
*/

let pathologyEditors = new Map();

// The last search() response -- .invoice (for building the print-group/
// send-whatsapp URLs) and .groups (test_group_code + unclaimed_items per
// group, used alongside the live DOM to work out group/invoice completion;
// see pathologyGroupProgress()). .pathology_whatsapp is the one piece of
// this that's real server-side state (already-sent, not derivable from the
// DOM) and gets patched in place after a send instead of a full reload.
let pathologyState = null;

function destroyAllPathologyEditors() {
    pathologyEditors.forEach(editor => editor.destroy());
    pathologyEditors.clear();
}

async function loadPathologyReports(invoiceNo) {

    const { result } = await fetchJson('/pathology-report/search', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken()
        },
        body: JSON.stringify({ invoice_no: invoiceNo })
    });

    if (!result.status) {
        document.querySelector('#invoiceNotFoundMsg').style.display = 'block';
        return;
    }

    renderInvoiceInfo(result.invoice);

    pathologyState = result;

    let wrap = document.querySelector('#pathologyReportsWrap');
    wrap.style.display = 'block';

    if (result.legacy_confirmed) {
        renderPathologyLegacyCard(wrap, result.invoice.id);
        return;
    }

    if (!result.groups.length) {
        document.querySelector('#noQualifyingMsg').style.display = 'block';
        return;
    }

    let tabsHtml = '<ul class="nav nav-pills mb-3" id="pathologyGroupTabs">';

    result.groups.forEach((g, idx) => {
        tabsHtml += `
        <li class="nav-item">
            <button type="button" class="nav-link ${idx === 0 ? 'active' : ''}" data-idx="${idx}">
                ${escapeHtml(g.test_group_name)}
            </button>
        </li>
        `;
    });

    tabsHtml += '</ul><div id="pathologyGroupPanes"></div>';

    wrap.innerHTML = tabsHtml;

    let panesContainer = wrap.querySelector('#pathologyGroupPanes');
    let paneTemplate = document.getElementById('pathologyGroupPaneTemplate');

    let cardReadyPromises = [];

    result.groups.forEach((g, idx) => {

        let frag = paneTemplate.content.cloneNode(true);
        let root = frag.querySelector('.pathology-group-pane');

        root.style.display = idx === 0 ? '' : 'none';
        root.dataset.idx = idx;
        root.querySelector('.pathology-group-title').innerText = g.test_group_name;

        panesContainer.appendChild(frag);

        let findingsWrap = root.querySelector('.pathology-group-findings');

        g.findings.forEach(f => cardReadyPromises.push(renderPathologyFindingCard(findingsWrap, f, invoiceNo)));

        root.querySelector('.pathology-start-picker').groupData = g;
        refreshPathologyStartPicker(root, g);
    });

    // Every card's CKEditor (and, with it, lockPathologyCard()'s
    // confirmed-badge display) must actually exist before computing
    // group/invoice completion -- see renderPathologyFindingCard()'s
    // comment on the returned promise.
    await Promise.all(cardReadyPromises);

    refreshPathologyGroupButtons();
    renderPathologyWhatsappBanner();
}

/*
|--------------------------------------------------------------------------
| INVOICE/GROUP COMPLETION -- worked out from the live DOM (confirmed-badge
| visibility, dataset.invoiceDetailIds) plus each group's still-mutable
| g.unclaimed_items, NOT from the original search() findings arrays alone --
| a report added/confirmed in THIS editing session (via the start-picker)
| only ever exists in the DOM, since renderPathologyFindingCard() never
| pushes it back into pathologyState.groups[i].findings.
|--------------------------------------------------------------------------
*/

function pathologyGroupProgress(pane, g) {

    let claimedTotal = 0;
    let confirmedTotal = 0;

    pane.querySelectorAll('.pathology-finding-card').forEach(card => {

        let n = (card.dataset.invoiceDetailIds || '').split(',').filter(Boolean).length;

        claimedTotal += n;

        if (card.querySelector('.pathology-confirmed-badge').style.display !== 'none') {
            confirmedTotal += n;
        }
    });

    let total = claimedTotal + g.unclaimed_items.length;

    return {
        total: total,
        confirmed: confirmedTotal,
        complete: total > 0 && confirmedTotal === total && g.unclaimed_items.length === 0
    };
}

function refreshPathologyGroupButtons() {

    // Whole-invoice state -- "Print All Reports" lives in every pane (so
    // it's on hand wherever staff happen to be looking) but reflects the
    // WHOLE invoice, not just the pane it's sitting in.
    let invoiceProgress = pathologyInvoiceProgress();

    document.querySelectorAll('.pathology-group-pane').forEach(pane => {

        let g = pathologyState.groups[parseInt(pane.dataset.idx, 10)];
        let printBtn = pane.querySelector('.pathology-print-group-btn');
        let printAllBtn = pane.querySelector('.pathology-print-invoice-btn');
        let sendWhatsappBtn = pane.querySelector('.pathology-send-whatsapp-btn');
        let confirmBtn = pane.querySelector('.pathology-confirm-group-btn');
        let progress = pathologyGroupProgress(pane, g);

        if (progress.complete) {

            let params = new URLSearchParams({
                invoice_no: pathologyState.invoice.invoice_no,
                test_group_code: g.test_group_code ?? ''
            });

            printBtn.href = `/pathology-report/print-group?${params.toString()}`;
            printBtn.style.display = 'inline-block';

        } else {

            printBtn.style.display = 'none';
        }

        if (invoiceProgress.complete) {

            let params = new URLSearchParams({ invoice_no: pathologyState.invoice.invoice_no });
            printAllBtn.href = `/pathology-report/print-invoice?${params.toString()}`;
            printAllBtn.style.display = 'inline-block';
            sendWhatsappBtn.style.display = 'inline-block';

        } else {

            printAllBtn.style.display = 'none';
            sendWhatsappBtn.style.display = 'none';
        }

        // Confirm is one button for the WHOLE group -- only makes sense
        // once every item in the group has a started report (no
        // unclaimed_items left) and there's at least one not yet
        // confirmed; already-complete groups show Print instead.
        let readyToConfirm = g.unclaimed_items.length === 0 && !progress.complete && progress.total > 0;

        confirmBtn.style.display = readyToConfirm ? 'inline-block' : 'none';
    });
}

// Same {total, confirmed, complete} shape as pathologyGroupProgress(), but
// summed across every group pane -- shared by the banner AND the "Print
// All Reports" button, which both need to know the WHOLE invoice's state
// (not just one group's).
function pathologyInvoiceProgress() {

    let total = 0;
    let confirmed = 0;

    document.querySelectorAll('.pathology-group-pane').forEach(pane => {
        let g = pathologyState.groups[parseInt(pane.dataset.idx, 10)];
        let progress = pathologyGroupProgress(pane, g);
        total += progress.total;
        confirmed += progress.confirmed;
    });

    return { total, confirmed, complete: total > 0 && confirmed === total };
}

function renderPathologyWhatsappBanner() {

    let banner = document.querySelector('#pathologyWhatsappBanner');
    let textEl = document.querySelector('#pathologyWhatsappBanner-text');
    let sendBtn = document.querySelector('#pathologyWhatsappBanner-sendBtn');

    if (!pathologyState || !pathologyState.groups.length) {
        banner.classList.add('d-none');
        return;
    }

    let { total, confirmed, complete } = pathologyInvoiceProgress();
    let wa = pathologyState.pathology_whatsapp || { sent: false, sent_at: null };

    banner.classList.remove('d-none', 'alert-success', 'alert-warning', 'alert-secondary');
    banner.classList.add('d-flex');

    if (wa.sent) {

        banner.classList.add('alert-success');
        textEl.innerHTML = `<i class="ri-whatsapp-line"></i> Combined report sent to the patient via WhatsApp on ${escapeHtml(wa.sent_at)}.`;
        sendBtn.style.display = 'none';

    } else if (complete) {

        banner.classList.add('alert-warning');
        textEl.innerText = `All ${total} Pathology test(s) on this invoice are confirmed -- WhatsApp not sent yet.`;
        sendBtn.style.display = 'inline-block';

    } else {

        banner.classList.add('alert-secondary');
        textEl.innerText = `${confirmed} of ${total} Pathology test(s) confirmed. The combined report is sent via WhatsApp automatically once every test is confirmed.`;
        sendBtn.style.display = 'none';
    }
}

function renderPathologyLegacyCard(wrap, invoiceId) {

    let template = document.getElementById('pathologyLegacyCardTemplate');
    let frag = template.content.cloneNode(true);

    let printBtn = frag.querySelector('.pathology-legacy-print-btn');
    let whatsappBtn = frag.querySelector('.pathology-legacy-whatsapp-btn');

    printBtn.addEventListener('click', function () {
        window.open(`/test-result-entry/print/${invoiceId}`, '_blank');
    });

    whatsappBtn.addEventListener('click', async function () {

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

        const { result } = await fetchJson(`/test-result-entry/send-whatsapp/${invoiceId}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() }
        });

        whatsappBtn.disabled = false;

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Sent' : 'Error',
            text: result.message
        });
    });

    wrap.appendChild(frag);
}

function refreshPathologyStartPicker(pane, g) {

    let picker = pane.querySelector('.pathology-start-picker');
    let startWrap = pane.querySelector('.pathology-start-wrap');

    if (!g.unclaimed_items.length) {
        startWrap.style.display = 'none';
        return;
    }

    startWrap.style.display = '';
    picker.innerHTML = '<option value="">-- Select a Template / Item --</option>';

    g.available_templates.forEach(t => {

        let itemNames = t.item_code_subs.map(code => {
            let item = g.unclaimed_items.find(i => i.item_code_sub === code);
            return item ? item.item_description : code;
        }).join(', ');

        let option = document.createElement('option');
        option.value = 'template:' + t.id;
        option.textContent = `Template: ${t.title} (${itemNames})`;
        picker.appendChild(option);
    });

    g.unclaimed_items.forEach(item => {

        let option = document.createElement('option');
        option.value = 'blank:' + item.invoice_detail_id;
        option.textContent = `Blank Report: ${item.item_description}`;
        picker.appendChild(option);
    });
}

function renderPathologyFindingCard(container, finding, invoiceNo) {

    let template = document.getElementById('pathologyFindingCardTemplate');
    let frag = template.content.cloneNode(true);
    let cardEl = frag.querySelector('.pathology-finding-card');

    cardEl.dataset.findingId = finding.id ?? '';
    cardEl.dataset.invoiceNo = invoiceNo;
    cardEl.dataset.invoiceDetailIds = (finding.items || []).map(i => i.invoice_detail_id).join(',');

    if (finding.template_id) {
        cardEl.dataset.templateId = finding.template_id;
    }

    let itemDesc = (finding.items || []).map(i => i.item_description).join(', ');

    cardEl.querySelector('.pathology-item-description').innerText = itemDesc;
    cardEl.querySelector('.pathology-template-title').innerText =
        finding.template_title ? `(Template: ${finding.template_title})` : '';

    container.appendChild(frag);

    let textarea = cardEl.querySelector('.pathology-content');

    // Returned so loadPathologyReports() can wait for every card's editor
    // (and, with it, lockPathologyCard()'s confirmed-badge display) before
    // computing group/invoice completion -- pathologyGroupProgress() reads
    // that badge's visibility directly, and CKEditor's own creation is
    // async, so refreshing right after the render loop (before any editor
    // had actually resolved) always saw every card as still-unconfirmed.
    return ClassicEditor.create(textarea, RICH_EDITOR_CONFIG).then(function (editor) {

        bindTabIndent(editor);
        pathologyEditors.set(cardEl, editor);
        editor.setData(toEditorHtml(finding.content || ''));

        if (finding.confirmed_at) {
            lockPathologyCard(cardEl, finding.id);
        }
    });
}

function lockPathologyCard(cardEl, findingId) {

    let editor = pathologyEditors.get(cardEl);

    if (editor) editor.enableReadOnlyMode('pathology-locked');

    cardEl.querySelector('.pathology-confirmed-badge').style.display = 'inline-block';
    cardEl.querySelector('.pathology-save-btn').style.display = 'none';

    let printBtn = cardEl.querySelector('.pathology-print-btn');
    printBtn.href = `/pathology-report/print/${findingId}`;
    printBtn.style.display = 'inline-block';
}

document.addEventListener('click', function (e) {

    let tabBtn = e.target.closest('#pathologyGroupTabs .nav-link');

    if (tabBtn) {

        document.querySelectorAll('#pathologyGroupTabs .nav-link').forEach(t => t.classList.remove('active'));
        tabBtn.classList.add('active');

        document.querySelectorAll('.pathology-group-pane').forEach(pane => {
            pane.style.display = pane.dataset.idx === tabBtn.dataset.idx ? '' : 'none';
        });
    }
});

document.addEventListener('change', async function (e) {

    let picker = e.target.closest('.pathology-start-picker');

    if (!picker || !picker.value) return;

    let g = picker.groupData;
    let pane = picker.closest('.pathology-group-pane');
    let findingsWrap = pane.querySelector('.pathology-group-findings');
    let invoiceNo = document.querySelector('#invoiceInfoWrap').dataset.invoiceNo;
    let cardReady = null;

    let [kind, idValue] = picker.value.split(':');

    if (kind === 'template') {

        let tpl = g.available_templates.find(t => String(t.id) === idValue);

        if (!tpl) {
            picker.value = '';
            return;
        }

        let items = tpl.item_code_subs
            .map(code => g.unclaimed_items.find(i => i.item_code_sub === code))
            .filter(Boolean);

        cardReady = renderPathologyFindingCard(findingsWrap, {
            id: null,
            content: tpl.content,
            template_id: tpl.id,
            template_title: tpl.title,
            confirmed_at: null,
            items: items
        }, invoiceNo);

        g.unclaimed_items = g.unclaimed_items.filter(i => !tpl.item_code_subs.includes(i.item_code_sub));

    } else if (kind === 'blank') {

        let item = g.unclaimed_items.find(i => String(i.invoice_detail_id) === idValue);

        if (!item) {
            picker.value = '';
            return;
        }

        cardReady = renderPathologyFindingCard(findingsWrap, {
            id: null,
            content: '',
            template_id: null,
            template_title: null,
            confirmed_at: null,
            items: [item]
        }, invoiceNo);

        g.unclaimed_items = g.unclaimed_items.filter(i => i !== item);
    }

    g.available_templates = g.available_templates.filter(t =>
        t.item_code_subs.every(code => g.unclaimed_items.some(i => i.item_code_sub === code))
    );

    refreshPathologyStartPicker(pane, g);

    // Wait for the new card's editor before recomputing this group's
    // buttons -- claiming the LAST unclaimed item should reveal Confirm
    // Group right away, but pathologyGroupProgress() reads the confirmed
    // badge's rendered display, which only exists once the promise below
    // resolves (same race lockPathologyCard() had on initial load).
    if (cardReady) {
        await cardReady;
    }

    refreshPathologyGroupButtons();
});

// Shared by the per-card Save button AND confirmPathologyGroup() below --
// confirming a group auto-saves every one of its cards first, so nobody
// has to remember to click Save on each item before the one group-level
// Confirm. Returns the fetch result unchanged; callers decide how to
// report it (a single Swal for one card, a rolled-up one for a group).
async function savePathologyCard(card) {

    let editor = pathologyEditors.get(card);
    let content = editor ? editor.getData() : '';

    let payload = { content: content };

    if (card.dataset.findingId) {

        payload.finding_id = card.dataset.findingId;

    } else {

        payload.invoice_no = card.dataset.invoiceNo;
        payload.invoice_detail_ids = card.dataset.invoiceDetailIds.split(',').filter(Boolean);

        if (card.dataset.templateId) {
            payload.template_id = card.dataset.templateId;
        }
    }

    const { result } = await fetchJson('/pathology-report/save', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken()
        },
        body: JSON.stringify(payload)
    });

    if (result.status && result.data && result.data.id) {
        card.dataset.findingId = result.data.id;
    }

    return result;
}

// Confirming is done ONCE PER TEST GROUP (e.g. "Haematology"), not per
// individual report -- every report in the group is saved (see
// savePathologyCard() above) and locked together in one action, matching
// how the group is also printed and WhatsApp'd as one combined document.
async function confirmPathologyGroup(groupBtn) {

    let pane = groupBtn.closest('.pathology-group-pane');
    let g = pathologyState.groups[parseInt(pane.dataset.idx, 10)];

    if (g.unclaimed_items.length > 0) {

        Swal.fire({
            icon: 'warning',
            title: 'Not Ready',
            text: 'Start a report for every test in this group before confirming.'
        });

        return;
    }

    let cards = Array.from(pane.querySelectorAll('.pathology-finding-card'))
        .filter(card => card.querySelector('.pathology-confirmed-badge').style.display === 'none');

    if (!cards.length) {
        return;
    }

    let confirmResult = await Swal.fire({
        icon: 'warning',
        title: 'Confirm this group?',
        text: `This locks all ${cards.length} report(s) in ${g.test_group_name} -- they can no longer be edited.`,
        showCancelButton: true,
        confirmButtonText: 'Yes, Confirm & Lock'
    });

    if (!confirmResult.isConfirmed) {
        return;
    }

    groupBtn.disabled = true;

    const saveResults = await Promise.all(cards.map(savePathologyCard));

    let failedSave = saveResults.find(r => !r.status);

    if (failedSave) {

        groupBtn.disabled = false;

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: failedSave.message ?? (failedSave.errors ? Object.values(failedSave.errors).flat().join(', ') : 'Unable to save one of this group\'s reports.')
        });

        return;
    }

    const { result } = await fetchJson('/pathology-report/confirm', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken()
        },
        body: JSON.stringify({
            invoice_no: pathologyState.invoice.invoice_no,
            test_group_code: g.test_group_code ?? ''
        })
    });

    groupBtn.disabled = false;

    if (result.status) {

        cards.forEach(card => lockPathologyCard(card, card.dataset.findingId));
        refreshPathologyGroupButtons();

        // The invoice's combined WhatsApp send only fires once every
        // Pathology report is confirmed (see PathologyReportController
        // ::confirm()) -- reflect whatever it actually did (or didn't
        // do yet) in the banner, rather than a generic "Confirmed".
        const waStatus = result.data ? result.data.whatsapp_status : null;

        if (waStatus === 'sent') {
            pathologyState.pathology_whatsapp = { sent: true, sent_at: result.data.whatsapp_sent_at };
        }

        renderPathologyWhatsappBanner();

        const waMessages = {
            sent: 'All reports confirmed -- combined report sent to the patient via WhatsApp.',
            already_sent: 'Confirmed. (WhatsApp for this invoice was already sent earlier.)',
            skipped: 'Confirmed. Automatic WhatsApp sending is currently switched off.',
            failed: 'Confirmed, but sending the WhatsApp message failed -- use Send Now once ready.',
            pending_other_reports: 'Confirmed. WhatsApp will be sent once every Pathology test on this invoice is confirmed.'
        };

        Swal.fire({
            icon: 'success',
            title: 'Confirmed',
            text: waMessages[waStatus] || result.message
        });

    } else {

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: result.message
        });
    }
}

document.addEventListener('click', async function (e) {

    let saveBtn = e.target.closest('.pathology-save-btn');

    if (saveBtn) {

        let card = saveBtn.closest('.pathology-finding-card');

        saveBtn.disabled = true;
        const result = await savePathologyCard(card);
        saveBtn.disabled = false;

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Saved' : 'Error',
            text: result.message ?? (result.errors ? Object.values(result.errors).flat().join(', ') : ''),
            timer: result.status ? 1200 : undefined,
            showConfirmButton: !result.status
        });

        return;
    }

    let confirmGroupBtn = e.target.closest('.pathology-confirm-group-btn');

    if (confirmGroupBtn) {
        await confirmPathologyGroup(confirmGroupBtn);
    }
});

// Shared by the WhatsApp banner's "Send Now" button AND the per-pane
// "Send WhatsApp" button beside Print All Reports -- both trigger the same
// manual send (pathology-report.send-whatsapp), which always re-sends the
// current combined document when called explicitly, whether or not the
// automatic send already went out (a deliberate resend capability, not
// blocked the way the automatic-on-confirm path is).
async function sendPathologyWhatsappNow(triggerBtn) {

    triggerBtn.disabled = true;

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

    const { result } = await fetchJson('/pathology-report/send-whatsapp', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken()
        },
        body: JSON.stringify({ invoice_no: pathologyState.invoice.invoice_no })
    });

    triggerBtn.disabled = false;

    if (result.status) {
        pathologyState.pathology_whatsapp = { sent: true, sent_at: result.data ? result.data.sent_at : null };
        renderPathologyWhatsappBanner();
    }

    Swal.fire({
        icon: result.status ? 'success' : 'error',
        title: result.status ? 'Sent' : 'Error',
        text: result.message
    });
}

document.getElementById('pathologyWhatsappBanner-sendBtn').addEventListener('click', function () {
    sendPathologyWhatsappNow(this);
});

document.addEventListener('click', function (e) {

    let btn = e.target.closest('.pathology-send-whatsapp-btn');

    if (btn) {
        sendPathologyWhatsappNow(btn);
    }
});

/*
|--------------------------------------------------------------------------
| NON-PATHOLOGY NARRATIVE REPORTS -- one independently completable/
| confirmable/printable Clinical History/Findings/Impression card per
| billed line, for every Non-Pathology category with no parameter grid.
| Mirrors usg-report.init.js's card handling (USG solved this same problem
| for itself already and keeps its own separate module).
|--------------------------------------------------------------------------
*/

let nonPathEditors = new Map();

function destroyAllNonPathEditors() {
    nonPathEditors.forEach(fields => {
        fields.clinical_history.destroy();
        fields.findings.destroy();
        fields.impression.destroy();
    });
    nonPathEditors.clear();
}

async function loadNonPathologyReports(invoiceNo) {

    const { result } = await fetchJson('/non-pathology-report/search', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken()
        },
        body: JSON.stringify({ invoice_no: invoiceNo })
    });

    if (!result.status) {
        document.querySelector('#invoiceNotFoundMsg').style.display = 'block';
        return;
    }

    renderInvoiceInfo(result.invoice);

    let wrap = document.querySelector('#nonPathologyReportsWrap');
    wrap.style.display = 'block';

    if (!result.lines.length) {

        document.querySelector('#noQualifyingMsg').style.display = 'block';
        return;
    }

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

        if (line.confirmed_at || isReadOnlyView) {
            lockNonPathCard(root, line.finding_id);
        }

        if (line.item_code_sub) {
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
