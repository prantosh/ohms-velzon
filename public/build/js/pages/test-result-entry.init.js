"use strict";

let isConfirmed = false;
// Complete-but-not-yet-confirmed reports open read-only ("Show Result")
// -- editing is locked the same as a confirmed report, but the Confirm
// button still shows (unlike a truly confirmed report) since that's the
// remaining step before Print/WhatsApp unlock.
let isReadOnlyView = false;
let currentRows = [];
let extraFieldTypes = [];
let remarksTemplates = [];

fetch('/remarks-master/active-list')
    .then(r => r.json())
    .then(result => {
        if (result.status) remarksTemplates = result.data;
    })
    .catch(() => {});

function inputsLocked() {
    return isConfirmed || isReadOnlyView;
}

try {
    let dataEl = document.getElementById('extraFieldTypesData');
    extraFieldTypes = dataEl ? JSON.parse(dataEl.textContent || '[]') : [];
} catch (e) {
    extraFieldTypes = [];
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

/*
|--------------------------------------------------------------------------
| RESULT ROWS (atomic tests, and analyte sub-rows within a panel test)
|--------------------------------------------------------------------------
*/

function renderResultRow(options) {

    let {
        invoiceDetailId, analyteId, resultId,
        itemCode, itemCodeSub, description, descriptionSuffix,
        uom, rangeMale, rangeFemale, rangeCommon, method,
        resultValue, remarks, extraClass
    } = options;

    return `
    <tr class="${extraClass || ''}"
        data-invoice-detail-id="${invoiceDetailId}"
        data-analyte-id="${analyteId}"
        data-result-id="${resultId ?? ''}"
        data-description="${escapeHtml(description)}"
        data-uom="${escapeHtml(uom)}">

        <td>${escapeHtml(itemCode)}</td>

        <td>${escapeHtml(itemCodeSub)}</td>

        <td>${escapeHtml(description)}${descriptionSuffix || ''}</td>

        <td>${escapeHtml(uom)}</td>

        <td>${escapeHtml(rangeMale)}</td>

        <td>${escapeHtml(rangeFemale)}</td>

        <td>${escapeHtml(rangeCommon)}</td>

        <td>${escapeHtml(method)}</td>

        <td>
            <input type="text" class="form-control form-control-sm result-value-input"
                   value="${escapeHtml(resultValue)}" ${inputsLocked() ? 'disabled' : ''}>
        </td>

        <td>
            <select class="form-select form-select-sm mb-1 remarks-template-picker"
                    style="max-width:130px" ${inputsLocked() ? 'disabled' : ''}>
                <option value="">-- Quick pick --</option>
                ${remarksTemplates.map(r => `<option value="${escapeHtml(r)}">${escapeHtml(r.length > 40 ? r.slice(0, 40) + '…' : r)}</option>`).join('')}
            </select>
            <input type="text" class="form-control form-control-sm remarks-input"
                   value="${escapeHtml(remarks)}" ${inputsLocked() ? 'disabled' : ''}>
        </td>

        <td class="text-nowrap">

            <button class="btn btn-sm btn-success save-result-btn ${inputsLocked() ? 'd-none' : ''}" title="Save">
                <i class="ri-save-line"></i>
            </button>

            <button class="btn btn-sm btn-danger clear-result-btn ${(resultId && !inputsLocked()) ? '' : 'd-none'}" title="Clear">
                <i class="ri-close-line"></i>
            </button>

        </td>

    </tr>
    `;
}

function renderAnalyteGroupHeaderRow(groupName) {

    return `
    <tr class="analyte-group-row">
        <td colspan="11" style="font-weight:bold; background-color:#f8f9fa; padding-left:16px;">
            ${escapeHtml(groupName)}
        </td>
    </tr>
    `;
}

function renderAnalyteSubGroupHeaderRow(subGroupName) {

    return `
    <tr class="analyte-subgroup-row">
        <td colspan="11" style="font-weight:bold; background-color:#e9ecef; padding-left:8px;">
            ${escapeHtml(subGroupName)}
        </td>
    </tr>
    `;
}

function getExtraParamInputType(inputEl) {

    if (inputEl.tagName === 'SELECT') return 'SELECT';
    if (inputEl.tagName === 'TEXTAREA') return 'TEXTAREA';

    return 'TEXT';
}

function renderExtraParamCard(invoiceDetailId, fieldTypeId, fieldName, inputType, value, valueId) {

    let inputHtml;

    if (inputType === 'SELECT') {

        let fieldType = extraFieldTypes.find(f => String(f.id) === String(fieldTypeId));
        let options = (fieldType && fieldType.options) ? fieldType.options.slice() : [];

        // Keep a previously-saved value selectable even if the master
        // record behind it was later deactivated or renamed.
        if (value && !options.includes(value)) {
            options = [value, ...options];
        }

        inputHtml = `
        <select class="form-select form-select-sm extra-param-input" ${inputsLocked() ? 'disabled' : ''}>
            <option value="">-- Select --</option>
            ${options.map(o => `<option value="${escapeHtml(o)}" ${o === value ? 'selected' : ''}>${escapeHtml(o)}</option>`).join('')}
        </select>
        `;

    } else if (inputType === 'TEXTAREA') {

        inputHtml = `<textarea class="form-control form-control-sm extra-param-input" rows="2" ${inputsLocked() ? 'disabled' : ''}>${escapeHtml(value)}</textarea>`;

    } else {

        inputHtml = `<input type="text" class="form-control form-control-sm extra-param-input" value="${escapeHtml(value)}" ${inputsLocked() ? 'disabled' : ''}>`;
    }

    return `
    <div class="extra-param-item border rounded p-2"
         data-value-id="${valueId ?? ''}"
         data-field-type-id="${fieldTypeId}"
         data-invoice-detail-id="${invoiceDetailId}">

        <label class="form-label mb-1 fw-semibold small">${escapeHtml(fieldName)}</label>

        ${inputHtml}

        <div class="mt-1 text-end">

            <button class="btn btn-sm btn-success save-extra-param-btn ${inputsLocked() ? 'd-none' : ''}" title="Save">
                <i class="ri-save-line"></i>
            </button>

            <button class="btn btn-sm btn-danger remove-extra-param-btn ${inputsLocked() ? 'd-none' : ''}" title="Remove">
                <i class="ri-close-line"></i>
            </button>

        </div>

    </div>
    `;
}

function renderExtraParamsRow(row) {

    let cards = (row.extra_values || []).map(v =>
        renderExtraParamCard(row.invoice_detail_id, v.field_type_id, v.field_name, v.input_type, v.value, v.id)
    ).join('');

    // Bundled templates (Remarks + Microscopy + Impression together) only
    // make sense once per billed test line, so this picker lives here
    // alongside the other test-line-level "extra parameter" controls,
    // not on every per-analyte result row.
    let templatePickerHtml = row.item_code_sub ? `
    <select class="form-select form-select-sm test-template-picker ${inputsLocked() ? 'd-none' : ''}"
            style="max-width:220px" data-invoice-detail-id="${row.invoice_detail_id}">
        <option value="">-- Load Template --</option>
    </select>
    ` : '';

    return `
    <tr class="extra-params-row">
        <td colspan="11">
            <div class="d-flex flex-wrap gap-2 align-items-start extra-params-container" data-invoice-detail-id="${row.invoice_detail_id}">

                ${templatePickerHtml}

                ${cards}

            </div>
        </td>
    </tr>
    `;
}

function renderRows(rows) {

    let tbody = document.querySelector('#resultTableBody');

    let html = '';
    let pickersToLoad = [];

    rows.forEach(row => {

        // The main billed test always gets its own editable result row,
        // whether or not it also has an analyte breakdown below it —
        // some tests need both (e.g. an overall result plus components).
        html += renderResultRow({
            invoiceDetailId: row.invoice_detail_id,
            analyteId: 0,
            resultId: row.result_id,
            itemCode: row.item_code,
            itemCodeSub: row.item_code_sub,
            description: row.item_description,
            descriptionSuffix: row.has_analytes
                ? ` <span class="badge bg-secondary ms-1">${row.analytes.length} sub-parameter${row.analytes.length > 1 ? 's' : ''}</span>`
                : '',
            uom: row.uom,
            rangeMale: row.range_male,
            rangeFemale: row.range_female,
            rangeCommon: row.range_common,
            method: row.method,
            resultValue: row.result_value,
            remarks: row.remarks
        });

        if (row.has_analytes) {

            let previousGroup = undefined;
            let previousSubGroup = undefined;

            row.analytes.forEach(a => {

                let group = a.group_name || '';
                let subGroup = a.sub_group_name || '';

                if (subGroup && subGroup !== previousSubGroup) {
                    html += renderAnalyteSubGroupHeaderRow(subGroup);
                    previousGroup = undefined;
                }

                if (group && group !== previousGroup) {
                    html += renderAnalyteGroupHeaderRow(group);
                }

                previousGroup = group;
                previousSubGroup = subGroup;

                html += renderResultRow({
                    invoiceDetailId: row.invoice_detail_id,
                    analyteId: a.analyte_id,
                    resultId: a.result_id,
                    itemCode: '',
                    itemCodeSub: '',
                    description: a.analyte_name,
                    uom: a.uom,
                    rangeMale: a.range_male,
                    rangeFemale: a.range_female,
                    rangeCommon: a.range_common,
                    method: a.method,
                    resultValue: a.result_value,
                    remarks: a.remarks,
                    extraClass: 'analyte-row'
                });
            });
        }

        html += renderExtraParamsRow(row);

        if (row.item_code_sub && !inputsLocked()) {
            pickersToLoad.push({ invoiceDetailId: row.invoice_detail_id, itemCodeSub: row.item_code_sub });
        }
    });

    // Build the whole tbody in one shot before wiring up any async picker
    // loads -- assigning innerHTML incrementally inside the loop above would
    // tear down and recreate every earlier row's nodes on each iteration,
    // detaching any picker element a later-resolving fetch tries to populate.
    tbody.innerHTML = html;

    pickersToLoad.forEach(({ invoiceDetailId, itemCodeSub }) => {

        let picker = tbody.querySelector(
            `.test-template-picker[data-invoice-detail-id="${invoiceDetailId}"]`
        );

        if (picker) loadTestTemplatesForPicker(picker, itemCodeSub);
    });
}

function loadTestTemplatesForPicker(picker, itemCodeSub) {

    fetch(`/test-report-template/for-test/${itemCodeSub}`)
        .then(r => r.json())
        .then(result => {

            if (!result.status || !result.data.length) return;

            picker.testTemplates = {};

            result.data.forEach(tpl => {

                picker.testTemplates[tpl.id] = tpl;

                let option = document.createElement('option');
                option.value = tpl.id;
                option.textContent = tpl.title;
                picker.appendChild(option);
            });
        })
        .catch(() => {});
}

function applyTestTemplateToLine(extraParamsRow, template) {

    let container = extraParamsRow.querySelector('.extra-params-container');
    let invoiceDetailId = container.dataset.invoiceDetailId;

    (template.parameters || []).forEach(function (param) {

        if (!param.value) return;

        let existingCard = container.querySelector(`.extra-param-item[data-field-type-id="${param.field_type_id}"]`);
        let valueId = existingCard ? existingCard.dataset.valueId : '';

        if (existingCard) existingCard.remove();

        let wrapper = document.createElement('div');

        wrapper.innerHTML = renderExtraParamCard(
            invoiceDetailId, param.field_type_id, param.field_name, param.input_type, param.value, valueId
        );

        container.appendChild(wrapper.firstElementChild);
    });
}

function updateCurrentRowsCache(invoiceDetailId, analyteId, resultId, resultValue, remarks) {

    let row = currentRows.find(r => String(r.invoice_detail_id) === String(invoiceDetailId));

    if (!row) return;

    if (String(analyteId) !== '0' && row.has_analytes) {

        let analyte = row.analytes.find(a => String(a.analyte_id) === String(analyteId));

        if (analyte) {
            analyte.result_id = resultId;
            analyte.result_value = resultValue;
            analyte.remarks = remarks;
        }

    } else {

        row.result_id = resultId;
        row.result_value = resultValue;
        row.remarks = remarks;
    }
}

function updateCurrentRowsExtraValue(invoiceDetailId, fieldTypeId, fieldName, inputType, valueId, value) {

    let row = currentRows.find(r => String(r.invoice_detail_id) === String(invoiceDetailId));

    if (!row) return;

    if (!row.extra_values) {
        row.extra_values = [];
    }

    let existing = row.extra_values.find(v => String(v.field_type_id) === String(fieldTypeId));

    if (existing) {

        existing.id = valueId;
        existing.value = value;

    } else {

        row.extra_values.push({
            id: valueId,
            field_type_id: fieldTypeId,
            field_name: fieldName,
            input_type: inputType,
            value: value
        });
    }
}

function collectRowsFromDom() {

    let rows = [];

    document.querySelectorAll('#resultTableBody tr').forEach(tr => {

        let resultInput = tr.querySelector('.result-value-input');

        if (!resultInput) return;

        rows.push({
            invoice_detail_id: tr.dataset.invoiceDetailId,
            analyte_id: tr.dataset.analyteId ?? '0',
            item_description: tr.dataset.description || '',
            uom: tr.dataset.uom || '',
            result_value: resultInput.value,
            remarks: tr.querySelector('.remarks-input').value,
        });
    });

    return rows;
}

async function saveRow(invoiceDetailId, analyteId, resultValue, remarks) {

    const response = await fetch('/test-result-entry/save', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            invoice_detail_id: invoiceDetailId,
            analyte_id: analyteId,
            result_value: resultValue,
            remarks: remarks
        })
    });

    return response.json();
}

async function saveExtraParam(invoiceDetailId, fieldTypeId, value) {

    const response = await fetch('/test-result-entry/save-extra', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            invoice_detail_id: invoiceDetailId,
            test_extra_field_type_id: fieldTypeId,
            value: value
        })
    });

    return response.json();
}

function applyLockState() {

    document.querySelector('#btnPrintReport').disabled = !isConfirmed;
    document.querySelector('#btnWhatsappReport').disabled = !isConfirmed;

    document.querySelector('#btnConfirmReport').style.display = isConfirmed ? 'none' : 'inline-block';
    document.querySelector('#confirmedBadge').style.display = isConfirmed ? 'inline-block' : 'none';
}

async function searchInvoice() {

    let invoiceNo = document.querySelector('#invoiceNoInput').value.trim();

    document.querySelector('#invoiceInfoWrap').style.display = 'none';
    document.querySelector('#invoiceNotFoundMsg').style.display = 'none';
    document.querySelector('#resultTableWrap').style.display = 'none';
    document.querySelector('#noQualifyingMsg').style.display = 'none';
    document.querySelector('#nonPathologyReportsWrap').innerHTML = '';

    if (!invoiceNo) {
        return;
    }

    const response = await fetch('/test-result-entry/search', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({ invoice_no: invoiceNo })
    });

    const result = await response.json();

    if (!result.status) {

        document.querySelector('#invoiceNotFoundMsg').style.display = 'block';
        return;
    }

    document.querySelector('#info-invoice_no').innerText = result.invoice.invoice_no ?? '';
    document.querySelector('#info-invoice_date').innerText = result.invoice.invoice_date ?? '';
    document.querySelector('#info-patient_name').innerText = result.invoice.patient_name ?? '';
    document.querySelector('#info-patient_age_gender').innerText =
        `${result.invoice.patient_age ?? ''} / ${result.invoice.patient_gender ?? ''}`;
    document.querySelector('#info-referred_doctor').innerText = result.invoice.referred_doctor ?? '';
    document.querySelector('#info-status').innerText = result.invoice.status ?? '';

    document.querySelector('#invoiceInfoWrap').dataset.invoiceId = result.invoice.id;
    document.querySelector('#invoiceInfoWrap').dataset.invoiceNo = result.invoice.invoice_no;

    document.querySelector('#invoiceInfoWrap').style.display = 'block';

    isConfirmed = !!result.invoice.confirmed;
    currentRows = result.data;

    if (!result.data.length) {

        // Non-Pathology categories (X-Ray, Cardiology, EMG-NCV, Endoscopy,
        // Dental, EYE, Miscellaneous, etc. -- everything except Pathology
        // and USG, which has its own separate module) have no parameter
        // grid at all, so buildRows() always returns empty for them. Offer
        // the narrative-report cards instead of the dead-end message.
        if (result.invoice.invoice_category === 'NON_PATHOLOGY') {
            await loadNonPathologyReports(result.invoice.invoice_no);
            return;
        }

        document.querySelector('#noQualifyingMsg').style.display = 'block';
        return;
    }

    renderRows(result.data);
    applyLockState();

    document.querySelector('#resultTableWrap').style.display = 'block';
}

/*
|--------------------------------------------------------------------------
| NON-PATHOLOGY NARRATIVE REPORTS -- one independently completable/
| confirmable/printable Clinical History/Findings/Impression card per
| billed line, for every Non-Pathology category with no parameter grid.
| Mirrors usg-report.init.js's card handling exactly (USG solved this same
| problem for itself already and keeps its own separate module).
|--------------------------------------------------------------------------
*/

async function loadNonPathologyReports(invoiceNo) {

    const response = await fetch('/non-pathology-report/search', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({ invoice_no: invoiceNo })
    });

    const result = await response.json();

    let wrap = document.querySelector('#nonPathologyReportsWrap');
    wrap.innerHTML = '';

    if (!result.status || !result.lines.length) {

        document.querySelector('#noQualifyingMsg').style.display = 'block';
        return;
    }

    let template = document.getElementById('nonPathReportCardTemplate');

    result.lines.forEach(function (line) {

        let card = template.content.cloneNode(true);
        let root = card.querySelector('.nonpath-report-card');

        root.dataset.invoiceDetailId = line.invoice_detail_id;
        root.dataset.findingId = line.finding_id ?? '';

        root.querySelector('.nonpath-item-description').innerText = line.item_description ?? '';
        root.querySelector('.nonpath-item-code-sub').innerText = line.item_code_sub ? `(${line.item_code_sub})` : '';
        root.querySelector('.nonpath-doctor-name').innerText = line.doctor_name ?? '-';

        root.querySelector('.nonpath-clinical-history').value = line.clinical_history ?? '';
        root.querySelector('.nonpath-findings').value = line.findings ?? '';
        root.querySelector('.nonpath-impression').value = line.impression ?? '';

        if (line.confirmed_at) {
            lockNonPathCard(root, line.finding_id);
        }

        wrap.appendChild(card);
    });

    document.querySelector('#resultTableWrap').style.display = 'none';
}

function lockNonPathCard(root, findingId) {

    root.querySelectorAll('textarea').forEach(t => t.disabled = true);
    root.querySelector('.nonpath-confirmed-badge').style.display = 'inline-block';
    root.querySelector('.nonpath-save-btn').style.display = 'none';
    root.querySelector('.nonpath-confirm-btn').style.display = 'none';

    let printBtn = root.querySelector('.nonpath-print-btn');
    printBtn.href = `/non-pathology-report/print/${findingId}`;
    printBtn.style.display = 'inline-block';

    root.querySelector('.nonpath-whatsapp-btn').style.display = 'inline-block';
}

document.addEventListener('click', async function (e) {

    let saveBtn = e.target.closest('.nonpath-save-btn');
    if (saveBtn) {

        let root = saveBtn.closest('.nonpath-report-card');

        saveBtn.disabled = true;

        const response = await fetch('/non-pathology-report/save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken()
            },
            body: JSON.stringify({
                invoice_detail_id: root.dataset.invoiceDetailId,
                clinical_history: root.querySelector('.nonpath-clinical-history').value,
                findings: root.querySelector('.nonpath-findings').value,
                impression: root.querySelector('.nonpath-impression').value
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

        const response = await fetch('/non-pathology-report/confirm', {
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

        const response = await fetch(`/non-pathology-report/send-whatsapp/${findingId}`, {
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

document.addEventListener('change', async function (e) {

    let picker = e.target.closest('.remarks-template-picker');

    if (!picker || !picker.value) return;

    let selectedText = picker.value;
    let remarksInput = picker.closest('td').querySelector('.remarks-input');

    if (remarksInput.value.trim()) {

        let confirmResult = await Swal.fire({
            icon: 'warning',
            title: 'Replace current remarks?',
            text: 'This will replace your current remarks with the selected quick pick.',
            showCancelButton: true,
            confirmButtonText: 'Yes, Replace'
        });

        if (!confirmResult.isConfirmed) {
            picker.value = '';
            return;
        }
    }

    remarksInput.value = selectedText;
    picker.value = '';
});

document.addEventListener('change', async function (e) {

    let picker = e.target.closest('.test-template-picker');

    if (!picker || !picker.value) return;

    let template = (picker.testTemplates || {})[picker.value];

    if (!template) {
        picker.value = '';
        return;
    }

    let invoiceDetailId = picker.dataset.invoiceDetailId;

    let mainRow = document.querySelector(
        `#resultTableBody tr[data-invoice-detail-id="${invoiceDetailId}"][data-analyte-id="0"]`
    );

    let remarksInput = mainRow ? mainRow.querySelector('.remarks-input') : null;

    let extraParamsRow = picker.closest('tr.extra-params-row');
    let container = extraParamsRow.querySelector('.extra-params-container');

    let hasExistingParam = (template.parameters || []).some(function (param) {
        let existingInput = container.querySelector(
            `.extra-param-item[data-field-type-id="${param.field_type_id}"] .extra-param-input`
        );
        return existingInput && existingInput.value.trim();
    });

    let hasExisting = (remarksInput && remarksInput.value.trim()) || hasExistingParam;

    if (hasExisting) {

        let confirmResult = await Swal.fire({
            icon: 'warning',
            title: 'Replace current values?',
            text: 'This will replace the current Remarks and any overlapping parameters for this test with the selected template.',
            showCancelButton: true,
            confirmButtonText: 'Yes, Replace'
        });

        if (!confirmResult.isConfirmed) {
            picker.value = '';
            return;
        }
    }

    if (remarksInput && template.remarks) remarksInput.value = template.remarks;

    applyTestTemplateToLine(extraParamsRow, template);

    picker.value = '';
});

document.addEventListener('click', async function (e) {

    let saveBtn = e.target.closest('.save-result-btn');

    if (saveBtn) {

        let row = saveBtn.closest('tr');

        let invoiceDetailId = row.dataset.invoiceDetailId;
        let analyteId = row.dataset.analyteId ?? '0';

        let resultValue = row.querySelector('.result-value-input').value;
        let remarks = row.querySelector('.remarks-input').value;

        Swal.fire({
            title: 'Saving....',
            text: 'Please wait',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        const result = await saveRow(invoiceDetailId, analyteId, resultValue, remarks);

        if (!result.status) {

            let errorText = result.errors
                ? Object.values(result.errors).flat().join(', ')
                : (result.message ?? 'Unable to save result.');

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorText
            });

            return;
        }

        row.dataset.resultId = result.data.result_id;
        row.querySelector('.clear-result-btn').classList.remove('d-none');

        updateCurrentRowsCache(invoiceDetailId, analyteId, result.data.result_id, result.data.result_value, result.data.remarks);

        Swal.fire({
            icon: 'success',
            title: 'Saved',
            text: result.message,
            timer: 1200,
            showConfirmButton: false
        });

        return;
    }

    let clearBtn = e.target.closest('.clear-result-btn');

    if (clearBtn) {

        let row = clearBtn.closest('tr');

        let resultId = row.dataset.resultId;
        let invoiceDetailId = row.dataset.invoiceDetailId;
        let analyteId = row.dataset.analyteId ?? '0';

        if (!resultId) {
            return;
        }

        Swal.fire({

            title: 'Clear Result?',
            text: 'This will remove the entered result for this test.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Clear'

        }).then(async function (confirmResult) {

            if (!confirmResult.isConfirmed) {
                return;
            }

            const response = await fetch(`/test-result-entry/delete/${resultId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'Accept': 'application/json'
                }
            });

            const result = await response.json();

            if (!result.status) {

                Swal.fire('Error', result.message ?? 'Unable to clear result.', 'error');
                return;
            }

            row.dataset.resultId = '';
            row.querySelector('.result-value-input').value = '';
            row.querySelector('.remarks-input').value = '';
            clearBtn.classList.add('d-none');

            updateCurrentRowsCache(invoiceDetailId, analyteId, null, null, null);

            Swal.fire({
                icon: 'success',
                title: 'Cleared',
                timer: 1000,
                showConfirmButton: false
            });
        });

        return;
    }
});

/*
|--------------------------------------------------------------------------
| EXTRA PARAMETERS (once-per-test metadata fields) -- cards can only
| arrive here via a Load Template pick now; there is no ad hoc "Add
| Parameter" control at entry time any more (that now lives on the Test
| Report Template admin screen, where parameters are configured once per
| template instead of chosen per patient). Save/Remove on an existing
| card still work normally.
|--------------------------------------------------------------------------
*/

document.addEventListener('click', async function (e) {

    let saveExtraBtn = e.target.closest('.save-extra-param-btn');

    if (saveExtraBtn) {

        let card = saveExtraBtn.closest('.extra-param-item');
        let value = card.querySelector('.extra-param-input').value;

        Swal.fire({
            title: 'Saving....',
            text: 'Please wait',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        const result = await saveExtraParam(
            card.dataset.invoiceDetailId,
            card.dataset.fieldTypeId,
            value
        );

        if (!result.status) {

            let errorText = result.errors
                ? Object.values(result.errors).flat().join(', ')
                : (result.message ?? 'Unable to save parameter.');

            Swal.fire({ icon: 'error', title: 'Error', text: errorText });
            return;
        }

        card.dataset.valueId = result.data.id;

        updateCurrentRowsExtraValue(
            card.dataset.invoiceDetailId,
            card.dataset.fieldTypeId,
            card.querySelector('.form-label').innerText,
            getExtraParamInputType(card.querySelector('.extra-param-input')),
            result.data.id,
            result.data.value
        );

        Swal.fire({
            icon: 'success',
            title: 'Saved',
            timer: 900,
            showConfirmButton: false
        });

        return;
    }

    let removeExtraBtn = e.target.closest('.remove-extra-param-btn');

    if (removeExtraBtn) {

        let card = removeExtraBtn.closest('.extra-param-item');
        let valueId = card.dataset.valueId;

        if (!valueId) {
            card.remove();
            return;
        }

        let confirmResult = await Swal.fire({
            title: 'Remove this parameter?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Remove'
        });

        if (!confirmResult.isConfirmed) return;

        const response = await fetch(`/test-result-entry/delete-extra/${valueId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json'
            }
        });

        const result = await response.json();

        if (!result.status) {

            Swal.fire('Error', result.message ?? 'Unable to remove parameter.', 'error');
            return;
        }

        card.remove();
    }
});

document.getElementById('btnPrintReport').addEventListener('click', function () {

    let invoiceId = document.querySelector('#invoiceInfoWrap').dataset.invoiceId;

    if (!invoiceId) {
        return;
    }

    window.open(`/test-result-entry/print/${invoiceId}`, '_blank');
});

document.getElementById('btnWhatsappReport').addEventListener('click', async function () {

    let invoiceId = document.querySelector('#invoiceInfoWrap').dataset.invoiceId;

    if (!invoiceId) {
        return;
    }

    Swal.fire({
        title: 'Please wait',
        text: 'Whatsapp message is under process.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: function () {
            Swal.showLoading();
        }
    });

    const response = await fetch(`/test-result-entry/send-whatsapp/${invoiceId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        }
    });

    const result = await response.json();

    if (!result.status) {

        Swal.fire('Error', result.message ?? 'Unable to send report via WhatsApp.', 'error');
        return;
    }

    Swal.fire({
        icon: 'success',
        title: 'Sent',
        text: result.message,
        timer: 1500,
        showConfirmButton: false
    });
});

document.getElementById('btnConfirmReport').addEventListener('click', function () {

    document.querySelector('#review-invoice_no').innerText =
        document.querySelector('#info-invoice_no').innerText;

    document.querySelector('#review-patient_name').innerText =
        document.querySelector('#info-patient_name').innerText;

    let rows = collectRowsFromDom();

    let tbody = document.querySelector('#reviewTableBody');

    tbody.innerHTML = '';

    rows.forEach(row => {

        tbody.innerHTML += `
        <tr>
            <td>${escapeHtml(row.item_description)}</td>
            <td>${escapeHtml(row.result_value)}</td>
            <td>${escapeHtml(row.uom)}</td>
            <td>${escapeHtml(row.remarks)}</td>
        </tr>
        `;
    });

    let extraTbody = document.querySelector('#reviewExtraParamsBody');

    extraTbody.innerHTML = '';

    let extraRowsFound = false;

    document.querySelectorAll('.extra-param-item').forEach(card => {

        let value = card.querySelector('.extra-param-input').value;

        if (!value) return;

        extraRowsFound = true;

        let fieldName = card.querySelector('.form-label').innerText;

        extraTbody.innerHTML += `
        <tr>
            <td>${escapeHtml(fieldName)}</td>
            <td>${escapeHtml(value)}</td>
        </tr>
        `;
    });

    document.querySelector('#reviewExtraParamsWrap').style.display = extraRowsFound ? 'block' : 'none';

    new bootstrap.Offcanvas(
        document.getElementById('confirmOffcanvas')
    ).show();
});

document.getElementById('btnConfirmLock').addEventListener('click', async function () {

    let invoiceNo = document.querySelector('#invoiceInfoWrap').dataset.invoiceNo;

    if (!invoiceNo) {
        return;
    }

    let rows = collectRowsFromDom();

    for (const row of rows) {

        const saveResult = await saveRow(
            row.invoice_detail_id,
            row.analyte_id,
            row.result_value,
            row.remarks
        );

        if (!saveResult.status) {

            let errorText = saveResult.errors
                ? Object.values(saveResult.errors).flat().join(', ')
                : (saveResult.message ?? 'Unable to save one or more test results.');

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorText
            });

            return;
        }

        updateCurrentRowsCache(row.invoice_detail_id, row.analyte_id, saveResult.data.result_id, saveResult.data.result_value, saveResult.data.remarks);
    }

    let extraCards = document.querySelectorAll('.extra-param-item');

    for (const card of extraCards) {

        let value = card.querySelector('.extra-param-input').value;

        if (!value) continue;

        const saveResult = await saveExtraParam(
            card.dataset.invoiceDetailId,
            card.dataset.fieldTypeId,
            value
        );

        if (!saveResult.status) {

            let errorText = saveResult.errors
                ? Object.values(saveResult.errors).flat().join(', ')
                : (saveResult.message ?? 'Unable to save one or more parameters.');

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorText
            });

            return;
        }

        updateCurrentRowsExtraValue(
            card.dataset.invoiceDetailId,
            card.dataset.fieldTypeId,
            card.querySelector('.form-label').innerText,
            getExtraParamInputType(card.querySelector('.extra-param-input')),
            saveResult.data.id,
            saveResult.data.value
        );
    }

    const response = await fetch('/test-result-entry/confirm', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({ invoice_no: invoiceNo })
    });

    const result = await response.json();

    if (!result.status) {

        Swal.fire('Error', result.message ?? 'Unable to confirm test report.', 'error');
        return;
    }

    isConfirmed = true;

    renderRows(currentRows);
    applyLockState();

    bootstrap.Offcanvas.getInstance(
        document.getElementById('confirmOffcanvas')
    ).hide();

    Swal.fire({
        icon: 'success',
        title: 'Confirmed',
        text: result.message,
        timer: 1500,
        showConfirmButton: false
    });
});
