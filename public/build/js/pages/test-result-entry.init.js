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
    Table, TableToolbar, TableProperties, TableCellProperties, Indent, IndentBlock, GeneralHtmlSupport, RestrictedEditingMode
} = CKEDITOR;

const RICH_EDITOR_CONFIG = {
    licenseKey: 'GPL',
    plugins: [
        Essentials, Paragraph, Bold, Italic, Underline, Alignment, FontSize, FontColor, Heading, List, Undo,
        Table, TableToolbar, TableProperties, TableCellProperties, Indent, IndentBlock, GeneralHtmlSupport
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
    },
    // Without this, CKEditor silently strips any attribute/element it
    // doesn't itself know about on every setData()/getData() round-trip --
    // including the data-key-param-block/data-key-param markers
    // mergeKeyParametersIntoContent() relies on to find its own block
    // again on a later save, which is what caused it to duplicate the
    // block instead of updating it in place.
    htmlSupport: {
        allow: [
            {
                name: 'div',
                attributes: { 'data-key-param-block': true },
                styles: true,
            },
            {
                name: /^(table|tbody|tr|td)$/,
                attributes: { 'data-key-param': true },
            },
            {
                // Template-anchored result markers (see the pathology
                // template editor's "Insert Result Field" button) --
                // inserted into template content, so must also survive the
                // round-trip on THIS editor when that content is loaded in.
                name: 'span',
                attributes: { 'data-key-param-result': true },
            },
        ]
    }
};

// A raw contenteditable="false" attribute (tried first, see git history)
// only survives the data round-trip via GeneralHtmlSupport -- it does
// NOT stop CKEditor's own typing commands, which operate on the model
// and don't consult arbitrary view attributes on already schema-known
// elements (span/td/table/p). RestrictedEditingMode is CKEditor5's own
// built-in feature for exactly this "template with fillable blanks" shape:
// once active, EVERY command not in allowedCommands is blocked outside an
// explicit exception region -- enforced at the command level, the same
// layer typing itself goes through, not a DOM attribute CKEditor's input
// pipeline has no reason to check. applyRestrictedEditingExceptions()
// marks exception regions by wrapping them in the plugin's own
// <span class="restricted-editing-exception"> convention, which it
// converts to/from a model marker on its own -- no custom attribute needed.
// Only used for templates applyRestrictedEditingExceptions() can find a
// safe island in (see its doc comment); free-text templates and Blank
// Report use the plain RICH_EDITOR_CONFIG, unrestricted, as before.
const LOCKED_EDITOR_CONFIG = Object.assign({}, RICH_EDITOR_CONFIG, {
    plugins: RICH_EDITOR_CONFIG.plugins.concat([RestrictedEditingMode]),
    restrictedEditing: {
        allowedCommands: ['input', 'delete', 'deleteForward', 'forwardDelete', 'enter', 'shiftEnter', 'undo', 'redo'],
    },
});

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

    destroyAllPathologyEditors();
    document.querySelector('#pathologyReportsWrap').innerHTML = '';
    document.querySelector('#pathologyReportsWrap').style.display = 'none';
    document.querySelector('#pathologyWhatsappBanner').classList.add('d-none');
    pathologyState = null;

    if (!invoiceNo) {
        return;
    }

    await loadPathologyReports(invoiceNo);
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

    refreshPathologyDeleteButtons();
}

// "Delete" on a saved/confirmed report: Admin only, any time -- even after
// the combined invoice WhatsApp has already gone out (the server leaves a
// correction-notice trail in that case so the replacement report carries a
// "please ignore the previous report" footnote). Unsaved cards have
// nothing to delete yet.
function refreshPathologyDeleteButtons() {

    if (!pathologyState) return;

    let allowed = !!pathologyState.can_delete_reports;

    document.querySelectorAll('.pathology-finding-card').forEach(card => {

        let btn = card.querySelector('.pathology-delete-btn');

        if (btn) btn.style.display = (allowed && card.dataset.findingId) ? 'inline-block' : 'none';
    });
}

async function deletePathologyFinding(deleteBtn) {

    let card = deleteBtn.closest('.pathology-finding-card');
    let findingId = card.dataset.findingId;

    if (!findingId) return;

    let isConfirmed = card.querySelector('.pathology-confirmed-badge').style.display !== 'none';
    let what = card.querySelector('.pathology-item-description').innerText;
    let alreadySent = isConfirmed && !!(pathologyState.pathology_whatsapp || {}).sent;

    let ask = await Swal.fire({
        icon: 'warning',
        title: 'Delete this report?',
        html: `<b>${escapeHtml(what)}</b><br>${isConfirmed ? 'This report is <b>confirmed</b>. ' : ''}It will be removed and its tests become open again, so a new report can be written.`
            + (alreadySent ? '<br><br>This invoice\'s report has already been sent to the patient via WhatsApp. The next report written for this test will automatically note that the earlier one should be ignored.' : ''),
        showCancelButton: true,
        confirmButtonColor: '#d63939',
        confirmButtonText: 'Yes, delete it'
    });

    if (!ask.isConfirmed) return;

    deleteBtn.disabled = true;

    let outcome;

    try {

        outcome = await fetchJson(`/pathology-report/delete/${findingId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken()
            },
            // which invoice's screen this is -- lets the server tell a normal
            // delete from a stray link shown on the wrong invoice
            body: JSON.stringify({ invoice_no: card.dataset.invoiceNo })
        });

    } catch (err) {

        deleteBtn.disabled = false;
        Swal.fire({ icon: 'error', title: 'Error', text: err.message });
        return;
    }

    deleteBtn.disabled = false;

    let result = outcome.result;

    if (!result.status) {

        Swal.fire({ icon: 'error', title: 'Cannot delete', text: result.message || 'Unable to delete this report.' });
        // Whatever changed under us (e.g. the invoice just became fully
        // confirmed) -- show the real current state.
        await searchInvoice();
        return;
    }

    await Swal.fire({ icon: 'success', title: 'Deleted', text: result.message, timer: 1400, showConfirmButton: false });

    await searchInvoice();
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

    // Tracks every item_code_sub already covered by a real (fully-matching)
    // template, so Blank Report below can be offered only where no real
    // template exists at all -- once one does, staff must use it (locked
    // structure, admin-controlled wording) rather than starting free-form.
    let coveredItemCodes = new Set();

    g.available_templates.forEach(t => {

        let remainingItemCodes = t.item_code_subs.filter(code =>
            g.unclaimed_items.some(i => i.item_code_sub === code)
        );

        if (!remainingItemCodes.length) return;

        // Offer a template only while EVERY test it covers is still open
        // on this invoice -- a panel that covers tests the patient never
        // ordered (or that are already reported) is just noise; the Blank
        // Report option below always covers any open item.
        if (remainingItemCodes.length !== t.item_code_subs.length) return;

        let itemNames = remainingItemCodes.map(code => {
            let item = g.unclaimed_items.find(i => i.item_code_sub === code);
            return item ? item.item_description : code;
        }).join(', ');

        // a template that covers a whole open package: it reports all its items in one go
        let coversPackage = remainingItemCodes.some(code =>
            (g.unclaimed_items.find(i => i.item_code_sub === code) || {}).components
        );

        let option = document.createElement('option');
        option.value = 'template:' + t.id;
        option.textContent = coversPackage
            ? `All items in one report -- Template: ${t.title} (${itemNames})`
            : `Template: ${t.title} (${itemNames})`;
        picker.appendChild(option);

        t.item_code_subs.forEach(code => coveredItemCodes.add(code));

        // (There is deliberately no "single test from a multi-test template" choice:
        // it would print the other tests of the template too. A single test is
        // reported from that test's OWN template, or from a blank report.)
    });

    g.unclaimed_items.forEach(item => {

        // Blank Report is a fallback for the many tests that don't have a
        // template authored yet -- it drops off the list the moment a real
        // template covers this item.
        if (coveredItemCodes.has(item.item_code_sub)) return;

        let option = document.createElement('option');
        option.value = 'blank:' + item.invoice_detail_id;
        option.textContent = (item.components && item.components.length > 1)
            ? `All items in one report -- Blank Report: ${item.item_description}`
            : `Blank Report: ${item.item_description}`;
        picker.appendChild(option);
    });

    // A package still open as one line can also be reported in PARTS (e.g. FBS
    // today, PPBS tomorrow): offer its components / the templates that cover
    // only some of them. The rest stay open and are reported later, each part
    // printing as its own report; "Print All Reports" combines them at the end.
    g.unclaimed_items.forEach(item => {

        if (!item.components || item.components.length < 2) return;

        let compCodes = item.components.map(c => c.item_code_sub);

        // ALL items in one report built from each item's OWN template -- offered
        // when the package has no single template of its own and every item has
        // exactly one template (so nothing has to be guessed).
        let ownTemplates = item.components.map(c =>
            (g.split_templates || []).filter(t => t.item_code_subs.length === 1 && t.item_code_subs[0] === c.item_code_sub)
        );

        let packageTemplateExists = g.available_templates.some(t => t.item_code_subs.includes(item.item_code_sub));

        if (!packageTemplateExists && ownTemplates.every(list => list.length === 1)) {

            let option = document.createElement('option');
            option.value = `splitall:${item.invoice_detail_id}`;
            option.textContent = `All items in one report -- Templates: ${ownTemplates.map(list => list[0].title).join(' + ')}`;
            picker.appendChild(option);
        }

        (g.split_templates || []).forEach(t => {

            if (!t.item_code_subs.length || t.item_code_subs.length >= compCodes.length) return;
            if (!t.item_code_subs.every(code => compCodes.includes(code))) return;

            let names = t.item_code_subs.map(code => {
                let c = item.components.find(x => x.item_code_sub === code);
                return c ? c.item_description : code;
            }).join(', ');

            let option = document.createElement('option');
            option.value = `splittpl:${t.id}:${item.invoice_detail_id}`;
            option.textContent = (t.item_code_subs.length === 1 ? 'Single item' : 'Some items')
                + ` -- Template: ${t.title} (${names}) -- rest of ${item.item_description} stays open`;
            picker.appendChild(option);
        });

        item.components.forEach((c, idx) => {

            // Same fallback-only rule as the top-level Blank Report list:
            // skip it once this component has its own dedicated template
            // (ownTemplates[idx], computed above).
            if (ownTemplates[idx].length > 0) return;

            let option = document.createElement('option');
            option.value = `splitblank:${c.invoice_detail_id}:${item.invoice_detail_id}`;
            option.textContent = `Single item -- Blank Report: ${c.item_description} -- rest of ${item.item_description} stays open`;
            picker.appendChild(option);
        });
    });
}

// Every item_code_sub actually involved in this report -- a package
// reported as one card (e.g. "splitall" Lipid Profile) carries its real
// components in date_lines, not as its own top-level item_code_sub, so
// this expands the same way the per-item date-editing UI already does.
function pathologyFindingItemCodeSubs(finding) {
    return (finding.items || [])
        .flatMap(item => item.date_lines && item.date_lines.length ? item.date_lines : [item])
        .map(dl => dl.item_code_sub)
        .filter(Boolean);
}

// The small structured "Key Parameters" capture box -- only the parameters
// this specific report's own tests trigger (FBS triggers on FBS; LFT's
// sub-parameters trigger on LFT itself being billed, since LFT prints as
// one bundled narrative, not separate billed lines -- see
// KeyParameterService). Returns '' when nothing applies, so the container
// stays hidden instead of showing an empty box.
function buildKeyParametersHtml(finding) {

    let allParams = (pathologyState && pathologyState.key_parameters) || [];
    let history = (pathologyState && pathologyState.key_parameter_history) || {};
    let draft = finding.key_parameter_values || {};

    let itemCodeSubs = pathologyFindingItemCodeSubs(finding);

    let applicable = allParams.filter(p => (p.triggers || []).some(t => itemCodeSubs.includes(t)));

    if (!applicable.length) return '';

    let byGroup = {};
    applicable.forEach(p => {
        let key = p.group_label || 'Other';
        byGroup[key] = byGroup[key] || [];
        byGroup[key].push(p);
    });

    let groupsHtml = Object.keys(byGroup).map(groupLabel => {

        let fieldsHtml = byGroup[groupLabel].map(p => {

            let past = history[p.item_code_sub] || [];
            let pastText = past.length
                ? 'Past: ' + past.map(h => `${escapeHtml(h.result_value)} (${escapeHtml(h.test_date_fmt)})`).join(', ')
                : '';

            let val = draft[p.item_code_sub] || '';
            let uomText = p.uom ? ` (${escapeHtml(p.uom)})` : '';

            return `
            <div style="min-width:170px;" class="mb-1">
                <label class="small mb-0">${escapeHtml(p.parameter_name)}${uomText}</label>
                <input type="text" class="form-control form-control-sm pathology-key-param-input"
                       data-item-code-sub="${escapeHtml(p.item_code_sub)}"
                       data-param-label="${escapeHtml(p.parameter_name)}"
                       data-param-uom="${escapeHtml(p.uom || '')}"
                       data-param-range="${escapeHtml(p.reference_range || '')}"
                       value="${escapeHtml(val)}">
                ${pastText ? `<div class="small text-muted">${pastText}</div>` : ''}
            </div>
            `;
        }).join('');

        return `
        <div class="mb-2">
            <div class="text-muted small fw-semibold">${escapeHtml(groupLabel)}</div>
            <div class="d-flex flex-wrap gap-3">${fieldsHtml}</div>
        </div>
        `;
    }).join('');

    return `
    <div class="border rounded p-2 bg-light-subtle">
        <div class="fw-semibold small mb-2"><i class="ri-history-line"></i> Key Parameters</div>
        <div class="small text-muted mb-2">Saved on Save/Confirm below -- automatically added to the printed
            report too, so you don't need to also type these into the result table.</div>
        ${groupsHtml}
    </div>
    `;
}

// Finds (or creates) this report's own clearly-marked "Key Parameters"
// block within the CKEditor content and syncs it to whatever's currently
// in the Key Parameters inputs -- rather than guessing which existing row
// in a free-typed template belongs to which test (unsafe: templates are
// free-form HTML with no reliable per-row identity), this block is fully
// owned and only ever written by this code, found again later by its own
// data-key-param-block/data-key-param markers. Removes the whole block
// when every field is empty, so an untouched report's content is
// unaffected.
// Marks the one kind of spot staff are actually meant to type into --
// an untracked parameter's blank Result cell (the established
// background-color:#F7FAFC convention -- see the pathology template
// editor's own blank-cell detection) -- as a RestrictedEditingMode
// exception region, by wrapping its content in that plugin's own
// <span class="restricted-editing-exception"> convention (which it
// upcasts into a model marker on its own; no custom attribute needed).
// A tracked parameter's cell (data-key-param-result marker) is left alone
// -- NOT wrapped as an exception, so it stays locked like everything
// else -- that one is fed from the Key Parameters box, never typed
// directly. Mutates the already-parsed `root` in place; does nothing when
// no <table> is found at all, since a free-text template (no identifiable
// cell boundaries -- e.g. Sodium, Potassium, Uric Acid) or a from-scratch
// Blank Report never gets RestrictedEditingMode in the first place (see
// renderPathologyFindingCard()'s editor-config choice) and has no safe
// way to carve out an island regardless.
function applyRestrictedEditingExceptions(root) {

    let tables = Array.from(root.querySelectorAll('table'));
    if (!tables.length) return;

    tables.forEach(table => {

        Array.from(table.querySelectorAll('td')).forEach(td => {

            // Tracked-parameter cell -- leave outside any exception region.
            if (td.hasAttribute('data-key-param-result') || td.querySelector('[data-key-param-result]')) {
                return;
            }

            let style = td.getAttribute('style') || '';
            if (!/F7FAFC/i.test(style)) return;

            // Already wrapped from an earlier save -- re-wrapping would
            // nest exception spans and corrupt the marker CKEditor
            // maintains for it.
            if (td.querySelector('.restricted-editing-exception')) return;

            // span is inline, so it has to sit INSIDE any block-level <p>
            // CKEditor's table feature wraps cell content in, not wrap the
            // <p> itself -- falls back to the td directly if there isn't one.
            let container = td.querySelector('p') || td;

            let exception = td.ownerDocument.createElement('span');
            exception.className = 'restricted-editing-exception';

            while (container.firstChild) {
                exception.appendChild(container.firstChild);
            }
            container.appendChild(exception);
        });
    });
}

// Applies applyRestrictedEditingExceptions() to contentHtml as a string --
// used both on initial load (a finding opened straight from saved content)
// and inside mergeKeyParametersIntoContent() (content just round-tripped
// through this same logic on save).
function applyTemplateContentLock(contentHtml) {

    let parser = new DOMParser();
    let doc = parser.parseFromString(`<div id="root">${contentHtml || ''}</div>`, 'text/html');
    let root = doc.getElementById('root');

    applyRestrictedEditingExceptions(root);

    return root.innerHTML;
}

function mergeKeyParametersIntoContent(card, contentHtml) {

    let inputs = card.querySelectorAll('.pathology-key-param-input');
    // No tracked parameters on this card at all -- still apply the
    // structural lock (most templates have zero tracked parameters today),
    // just skip the marker fill/block logic below since there's nothing
    // for it to do.
    if (!inputs.length) return applyTemplateContentLock(contentHtml);

    let entries = [];
    let valueByCode = {};
    inputs.forEach(input => {
        let val = input.value.trim();
        valueByCode[input.dataset.itemCodeSub] = val;
        if (val) {
            entries.push({
                itemCodeSub: input.dataset.itemCodeSub,
                label: input.dataset.paramLabel || input.dataset.itemCodeSub,
                uom: input.dataset.paramUom || '',
                range: input.dataset.paramRange || '',
                value: val
            });
        }
    });

    let parser = new DOMParser();
    let doc = parser.parseFromString(`<div id="root">${contentHtml || ''}</div>`, 'text/html');
    let root = doc.getElementById('root');

    // Template-anchored result cells: when the template itself was authored
    // with a marker in its Result column (see the pathology template
    // editor's "Insert Result Field" button), write straight into that real
    // cell instead of the separate appended block below -- one entry field
    // for staff, no blank main-table cell. Only parameters with NO marker
    // in this content fall through to the block. Blanking an input clears
    // its cell back out rather than leaving a stale value behind.
    let markerHandled = {};
    root.querySelectorAll('[data-key-param-result]').forEach(marker => {
        let itemCodeSub = marker.getAttribute('data-key-param-result');
        marker.textContent = valueByCode[itemCodeSub] || '';
        markerHandled[itemCodeSub] = true;
    });
    entries = entries.filter(e => !markerHandled[e.itemCodeSub]);

    // Primary signal: the data-key-param-block marker. Fallback signal (in
    // case an editor round-trip ever drops a custom attribute despite the
    // GeneralHtmlSupport config that's meant to preserve it): any <div>
    // whose own first line of text is exactly "Key Parameters" -- a label
    // chosen specifically because it won't collide with real report
    // content. Using both means a block this code created earlier is
    // always found and updated in place, never duplicated, even if one
    // signal alone turns out not to survive every CKEditor version. Any
    // EXTRA matching blocks (e.g. already duplicated before this fallback
    // existed) are cleaned up here too -- merged into the first, rest
    // removed.
    // Only ROOT's own direct children -- querySelectorAll('div') would
    // also match the block's own inner heading div (its text is exactly
    // "Key Parameters" too), wrongly treating it as a second top-level
    // block and deleting it as a "duplicate" on every re-save.
    let candidates = Array.from(root.children).filter(el =>
        el.tagName === 'DIV' && (
            el.hasAttribute('data-key-param-block')
            || (el.textContent || '').trim().indexOf('Key Parameters') === 0
        )
    );

    let block = candidates.shift() || null;
    candidates.forEach(extra => extra.remove());

    if (!entries.length) {
        if (block) block.remove();
        applyRestrictedEditingExceptions(root);
        return root.innerHTML;
    }

    if (!block) {
        block = doc.createElement('div');
        block.style.marginTop = '12px';

        let heading = doc.createElement('div');
        heading.style.fontWeight = 'bold';
        heading.style.fontSize = '11px';
        heading.style.color = '#1F4E78';
        heading.style.marginBottom = '3px';
        heading.textContent = 'Key Parameters';
        block.appendChild(heading);

        let table = doc.createElement('table');
        table.style.width = '100%';
        table.style.borderCollapse = 'collapse';
        table.innerHTML = '<tbody></tbody>';
        block.appendChild(table);

        root.appendChild(block);
    }

    // Re-applied every time regardless of whether it was already there --
    // self-heals a block found only via the text fallback above.
    block.setAttribute('data-key-param-block', '1');

    let tbody = block.querySelector('tbody');

    // Every row, tagged or not -- an untagged one (same attribute-loss
    // risk as the block itself) is matched by its own first cell's text,
    // scoped to just these few rows this code owns, not the whole report,
    // so an exact match here is reliable.
    let rowFor = (itemCodeSub, label) => {

        let tr = tbody.querySelector(`tr[data-key-param="${itemCodeSub}"]`);
        if (tr) return tr;

        return Array.from(tbody.querySelectorAll('tr')).find(row => {
            let firstCellText = (row.querySelector('td')?.textContent || '').trim();
            return firstCellText.indexOf(label) === 0;
        }) || null;
    };

    // drop rows for fields that were cleared since the last save
    Array.from(tbody.querySelectorAll('tr')).forEach(tr => {
        let stillWanted = entries.some(e => tr === rowFor(e.itemCodeSub, e.label));
        if (!stillWanted) tr.remove();
    });

    entries.forEach(e => {

        let tr = rowFor(e.itemCodeSub, e.label);

        if (!tr) {
            tr = doc.createElement('tr');
            tr.innerHTML = `
                <td style="padding:3px 6px;border:1px solid #B8C8D4;font-size:10px;"></td>
                <td style="padding:3px 6px;border:1px solid #B8C8D4;font-size:10px;text-align:center;"></td>
                <td style="padding:3px 6px;border:1px solid #B8C8D4;font-size:10px;"></td>
            `;
            tbody.appendChild(tr);
        }

        tr.setAttribute('data-key-param', e.itemCodeSub);

        let cells = tr.querySelectorAll('td');
        cells[0].textContent = e.label + (e.uom ? ` (${e.uom})` : '');
        cells[1].textContent = e.value;
        cells[2].textContent = e.range || '';
    });

    applyRestrictedEditingExceptions(root);

    return root.innerHTML;
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

    // main heading of the report: the saved heading, else the test description(s)
    cardEl.querySelector('.pathology-report-title').value = finding.report_title || itemDesc;

    let keyParamsHtml = buildKeyParametersHtml(finding);
    let keyParamsWrap = cardEl.querySelector('.pathology-key-parameters');

    if (keyParamsHtml) {
        keyParamsWrap.innerHTML = keyParamsHtml;
        keyParamsWrap.style.display = 'block';
    }

    // A test date for each test on this report (starts as the date entered at
    // invoice creation; editable until the report is confirmed).
    let datesWrap = cardEl.querySelector('.pathology-item-dates');

    (finding.items || []).flatMap(item => item.date_lines && item.date_lines.length ? item.date_lines : [item]).forEach(item => {

        let label = document.createElement('label');
        label.className = 'd-flex align-items-center gap-2 small mb-0';
        label.innerHTML = `<span class="text-muted">${escapeHtml(item.item_description)} - test date</span>`
            + `<input type="date" class="form-control form-control-sm pathology-item-date" style="width:auto"`
            + ` data-detail-id="${item.invoice_detail_id}" value="${escapeHtml(item.test_date || '')}">`;

        datesWrap.appendChild(label);
    });
    cardEl.querySelector('.pathology-template-title').innerText =
        finding.template_title ? `(Template: ${finding.template_title})` : '';

    container.appendChild(frag);

    let textarea = cardEl.querySelector('.pathology-content');

    // RestrictedEditingMode (see LOCKED_EDITOR_CONFIG's doc comment) locks
    // the WHOLE document except explicit exception regions -- only usable
    // when the template has an identifiable table to carve those islands
    // out of; a free-text template or a from-scratch Blank Report has
    // nothing to except, so forcing it on would leave staff unable to
    // type anywhere at all.
    let editorConfig = /<table[\s>]/i.test(finding.content || '') ? LOCKED_EDITOR_CONFIG : RICH_EDITOR_CONFIG;

    // Returned so loadPathologyReports() can wait for every card's editor
    // (and, with it, lockPathologyCard()'s confirmed-badge display) before
    // computing group/invoice completion -- pathologyGroupProgress() reads
    // that badge's visibility directly, and CKEditor's own creation is
    // async, so refreshing right after the render loop (before any editor
    // had actually resolved) always saw every card as still-unconfirmed.
    return ClassicEditor.create(textarea, editorConfig).then(function (editor) {

        bindTabIndent(editor);
        pathologyEditors.set(cardEl, editor);
        editor.setData(applyTemplateContentLock(toEditorHtml(finding.content || '')));

        if (finding.confirmed_at) {
            lockPathologyCard(cardEl, finding.id);
        }
    });
}

function lockPathologyCard(cardEl, findingId) {

    let editor = pathologyEditors.get(cardEl);

    if (editor) editor.enableReadOnlyMode('pathology-locked');

    cardEl.querySelector('.pathology-confirmed-badge').style.display = 'inline-block';
    cardEl.querySelectorAll('.pathology-item-date').forEach(input => { input.disabled = true; });
    cardEl.querySelectorAll('.pathology-key-param-input').forEach(input => { input.disabled = true; });
    cardEl.querySelector('.pathology-report-title').disabled = true;
    cardEl.querySelector('.pathology-save-btn').style.display = 'none';
    cardEl.querySelector('.pathology-confirm-btn').style.display = 'none';

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

    let [kind, idValue, itemCodeSub] = picker.value.split(':');

    if (kind === 'template' || kind === 'template-item') {

        let tpl = g.available_templates.find(t => String(t.id) === idValue);

        if (!tpl) {
            picker.value = '';
            return;
        }

        let remainingItemCodes = tpl.item_code_subs.filter(code =>
            g.unclaimed_items.some(i => i.item_code_sub === code)
                && (kind !== 'template-item' || code === itemCodeSub)
        );

        let items = remainingItemCodes
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

        g.unclaimed_items = g.unclaimed_items.filter(i => !remainingItemCodes.includes(i.item_code_sub));

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
    } else if (kind === 'splitall') {

        // ALL items of the package in ONE report, each item's own template one after the other
        let pkg = g.unclaimed_items.find(i => String(i.invoice_detail_id) === idValue);

        if (!pkg || !pkg.components) {
            picker.value = '';
            return;
        }

        let parts = pkg.components.map(c => {
            let own = (g.split_templates || []).filter(t => t.item_code_subs.length === 1 && t.item_code_subs[0] === c.item_code_sub);
            return own.length === 1 ? own[0].content : null;
        });

        if (parts.some(p => p === null)) {
            picker.value = '';
            return;
        }

        cardReady = renderPathologyFindingCard(findingsWrap, {
            id: null,
            content: parts.join('<p>&nbsp;</p>'),
            template_id: null,
            template_title: null,
            confirmed_at: null,
            items: [Object.assign({}, pkg, { date_lines: pkg.components })]
        }, invoiceNo);

        g.unclaimed_items = g.unclaimed_items.filter(i => i !== pkg);

    } else if (kind === 'splittpl' || kind === 'splitblank') {

        // report PART of a package: only the chosen components are claimed
        let pkg = g.unclaimed_items.find(i => String(i.invoice_detail_id) === itemCodeSub);
        let tpl = kind === 'splittpl' ? (g.split_templates || []).find(t => String(t.id) === idValue) : null;

        if (!pkg || !pkg.components || (kind === 'splittpl' && !tpl)) {
            picker.value = '';
            return;
        }

        let chosen = kind === 'splittpl'
            ? pkg.components.filter(c => tpl.item_code_subs.includes(c.item_code_sub))
            : pkg.components.filter(c => String(c.invoice_detail_id) === idValue);

        if (!chosen.length) {
            picker.value = '';
            return;
        }

        cardReady = renderPathologyFindingCard(findingsWrap, {
            id: null,
            content: tpl ? tpl.content : '',
            template_id: tpl ? tpl.id : null,
            template_title: tpl ? tpl.title : null,
            confirmed_at: null,
            items: chosen
        }, invoiceNo);

        // the package line is no longer open as one; its other components become
        // ordinary open lines (what the server works out after the first save)
        let chosenIds = chosen.map(c => c.invoice_detail_id);

        g.unclaimed_items = g.unclaimed_items.filter(i => i !== pkg);

        pkg.components
            .filter(c => !chosenIds.includes(c.invoice_detail_id))
            .forEach(c => g.unclaimed_items.push({
                invoice_detail_id: c.invoice_detail_id,
                item_code_sub: c.item_code_sub,
                item_description: c.item_description,
                test_date: c.test_date,
                components: null
            }));

        // templates for the components that are left
        (g.split_templates || []).forEach(t => {
            if (!g.available_templates.some(a => String(a.id) === String(t.id))) {
                g.available_templates.push(t);
            }
        });
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

    // Key Parameters -> auto-inserted/kept-in-sync block in the report
    // itself, so a value only needs to be typed once. Reflected back into
    // the visible editor too, so staff see it land in the report right
    // after saving.
    content = mergeKeyParametersIntoContent(card, content);
    if (editor) editor.setData(content);

    let payload = { content: content, report_title: card.querySelector('.pathology-report-title').value };

    // per-test dates: { invoice_detail_id: 'YYYY-MM-DD' } ('' clears a test's own
    // date so it follows the invoice's again)
    payload.item_dates = {};

    card.querySelectorAll('.pathology-item-date').forEach(input => {
        payload.item_dates[input.dataset.detailId] = input.value || '';
    });

    // { item_code_sub: 'value' } -- the Key Parameters box, draft-saved
    // alongside content and only promoted into the patient's permanent
    // history once this report is actually confirmed (see
    // KeyParameterService::recordConfirmedValues()).
    payload.key_parameter_values = {};

    card.querySelectorAll('.pathology-key-param-input').forEach(input => {
        payload.key_parameter_values[input.dataset.itemCodeSub] = input.value || '';
    });

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
        refreshPathologyDeleteButtons();
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
            pending_other_reports: 'Confirmed. WhatsApp will be sent once every Pathology test on this invoice is confirmed.',
            held_due: 'Confirmed. WhatsApp is on hold until the pending payment on this invoice is cleared; it will then be sent automatically.'
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

// Confirms/locks just ONE report, independent of the rest of its test
// group -- for items that come back sooner than the rest of the group
// (pathology-report.confirm-finding). Mirrors confirmPathologyGroup()
// above (auto-save first, then lock + refresh the shared group/invoice
// buttons and WhatsApp banner), just scoped to a single card.
async function confirmPathologyFinding(confirmBtn) {

    let card = confirmBtn.closest('.pathology-finding-card');

    let confirmResult = await Swal.fire({
        icon: 'warning',
        title: 'Confirm this report?',
        text: 'This locks this report -- it can no longer be edited.',
        showCancelButton: true,
        confirmButtonText: 'Yes, Confirm & Lock'
    });

    if (!confirmResult.isConfirmed) {
        return;
    }

    confirmBtn.disabled = true;

    const saveResult = await savePathologyCard(card);

    if (!saveResult.status) {

        confirmBtn.disabled = false;

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: saveResult.message ?? (saveResult.errors ? Object.values(saveResult.errors).flat().join(', ') : 'Unable to save this report.')
        });

        return;
    }

    const { result } = await fetchJson(`/pathology-report/confirm-finding/${card.dataset.findingId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken()
        }
    });

    confirmBtn.disabled = false;

    if (result.status) {

        lockPathologyCard(card, card.dataset.findingId);
        refreshPathologyGroupButtons();

        const waStatus = result.data ? result.data.whatsapp_status : null;

        if (waStatus === 'sent') {
            pathologyState.pathology_whatsapp = { sent: true, sent_at: result.data.whatsapp_sent_at };
        }

        renderPathologyWhatsappBanner();

        const waMessages = {
            sent: 'Confirmed -- all reports on this invoice are now confirmed, so the combined report has been sent to the patient via WhatsApp.',
            already_sent: 'Confirmed. (WhatsApp for this invoice was already sent earlier.)',
            skipped: 'Confirmed. Automatic WhatsApp sending is currently switched off.',
            failed: 'Confirmed, but sending the WhatsApp message failed -- use Send Now once ready.',
            pending_other_reports: 'Confirmed. WhatsApp will be sent once every Pathology test on this invoice is confirmed.',
            held_due: 'Confirmed. WhatsApp is on hold until the pending payment on this invoice is cleared; it will then be sent automatically.'
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
        return;
    }

    let confirmFindingBtn = e.target.closest('.pathology-confirm-btn');

    if (confirmFindingBtn) {
        await confirmPathologyFinding(confirmFindingBtn);
        return;
    }

    let deleteFindingBtn = e.target.closest('.pathology-delete-btn');

    if (deleteFindingBtn) {
        await deletePathologyFinding(deleteFindingBtn);
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

