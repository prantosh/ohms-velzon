"use strict";

let isConfirmed = false;
// Complete-but-not-yet-confirmed reports open read-only ("Show Result")
// -- editing is locked the same as a confirmed report, but the Confirm
// button still shows (unlike a truly confirmed report) since that's the
// remaining step before Print/WhatsApp unlock.
let isReadOnlyView = false;
let currentRows = [];
let extraFieldTypes = [];

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

    return `
    <tr class="extra-params-row">
        <td colspan="11">
            <div class="d-flex flex-wrap gap-2 align-items-start extra-params-container" data-invoice-detail-id="${row.invoice_detail_id}">

                ${cards}

                <div class="extra-param-add-wrap ${inputsLocked() ? 'd-none' : ''}">
                    <button class="btn btn-sm btn-soft-primary add-extra-param-btn" type="button">
                        <i class="ri-add-line"></i> Add Parameter
                    </button>
                    <ul class="dropdown-menu extra-param-menu" style="max-height: 420px; overflow-y: auto; z-index: 1065;"></ul>
                </div>

            </div>
        </td>
    </tr>
    `;
}

function renderRows(rows) {

    let tbody = document.querySelector('#resultTableBody');

    tbody.innerHTML = '';

    rows.forEach(row => {

        // The main billed test always gets its own editable result row,
        // whether or not it also has an analyte breakdown below it —
        // some tests need both (e.g. an overall result plus components).
        tbody.innerHTML += renderResultRow({
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
                    tbody.innerHTML += renderAnalyteSubGroupHeaderRow(subGroup);
                    previousGroup = undefined;
                }

                if (group && group !== previousGroup) {
                    tbody.innerHTML += renderAnalyteGroupHeaderRow(group);
                }

                previousGroup = group;
                previousSubGroup = subGroup;

                tbody.innerHTML += renderResultRow({
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

        tbody.innerHTML += renderExtraParamsRow(row);
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

        document.querySelector('#noQualifyingMsg').style.display = 'block';
        return;
    }

    renderRows(result.data);
    applyLockState();

    document.querySelector('#resultTableWrap').style.display = 'block';
}

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
| EXTRA PARAMETERS (once-per-test metadata fields)
|--------------------------------------------------------------------------
*/

let openMenuAnchorBtn = null;
let openMenuEl = null;

function closeAllExtraParamMenus() {

    document.querySelectorAll('.extra-param-menu').forEach(m => {
        m.style.display = 'none';
    });

    openMenuAnchorBtn = null;
    openMenuEl = null;
}

// Positions (or re-positions, e.g. on scroll) a fixed menu against its
// trigger button, flipping above / clamping within the viewport as
// needed — position:fixed only escapes ancestor overflow clipping, it
// does not automatically stay inside the viewport or track the anchor.
function positionExtraParamMenu(menu, addBtn) {

    let rect = addBtn.getBoundingClientRect();

    let wasHidden = menu.style.display !== 'block';

    if (wasHidden) {
        menu.style.visibility = 'hidden';
        menu.style.display = 'block';
    }

    let menuHeight = menu.offsetHeight;
    let menuWidth = menu.offsetWidth;
    let viewportHeight = window.innerHeight;
    let viewportWidth = window.innerWidth;

    let top;

    if (rect.bottom + 4 + menuHeight <= viewportHeight) {
        top = rect.bottom + 4;
    } else if (rect.top - 4 - menuHeight >= 0) {
        top = rect.top - 4 - menuHeight;
    } else {
        top = Math.max(4, viewportHeight - menuHeight - 4);
    }

    let left = rect.left;

    if (left + menuWidth > viewportWidth) {
        left = Math.max(4, viewportWidth - menuWidth - 4);
    }

    menu.style.position = 'fixed';
    menu.style.minWidth = Math.max(rect.width, 180) + 'px';
    menu.style.top = top + 'px';
    menu.style.left = left + 'px';

    if (wasHidden) {
        menu.style.visibility = 'visible';
    }
}

document.addEventListener('click', async function (e) {

    let addBtn = e.target.closest('.add-extra-param-btn');

    if (addBtn) {

        let container = addBtn.closest('.extra-params-container');
        let presentIds = Array.from(container.querySelectorAll('.extra-param-item'))
            .map(el => String(el.dataset.fieldTypeId));

        let menu = addBtn.closest('.extra-param-add-wrap').querySelector('.extra-param-menu');

        let alreadyOpen = menu.style.display === 'block';

        closeAllExtraParamMenus();

        if (alreadyOpen) {
            return;
        }

        menu.innerHTML = '';

        let available = extraFieldTypes.filter(ft => !presentIds.includes(String(ft.id)));

        if (!available.length) {

            menu.innerHTML = '<li><span class="dropdown-item-text text-muted">No more parameters available</span></li>';

        } else {

            available.forEach(ft => {

                menu.innerHTML += `
                <li>
                    <a class="dropdown-item add-extra-param-option" href="javascript:void(0)"
                       data-field-type-id="${ft.id}"
                       data-field-name="${escapeHtml(ft.field_name)}"
                       data-input-type="${ft.input_type}">
                       ${escapeHtml(ft.field_name)}
                    </a>
                </li>
                `;
            });
        }

        positionExtraParamMenu(menu, addBtn);

        openMenuAnchorBtn = addBtn;
        openMenuEl = menu;

        return;
    }

    let addOption = e.target.closest('.add-extra-param-option');

    if (addOption) {

        closeAllExtraParamMenus();

        let container = addOption.closest('.extra-params-container');
        let invoiceDetailId = container.dataset.invoiceDetailId;
        let dropdownWrap = container.querySelector('.extra-param-add-wrap');

        let wrapper = document.createElement('div');

        wrapper.innerHTML = renderExtraParamCard(
            invoiceDetailId,
            addOption.dataset.fieldTypeId,
            addOption.dataset.fieldName,
            addOption.dataset.inputType,
            '',
            ''
        );

        container.insertBefore(wrapper.firstElementChild, dropdownWrap);

        return;
    }

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

document.addEventListener('click', function (e) {

    if (e.target.closest('.add-extra-param-btn')) return;
    if (e.target.closest('.extra-param-menu')) return;

    closeAllExtraParamMenus();
});

document.addEventListener('scroll', function (e) {

    if (!openMenuAnchorBtn || !openMenuEl) return;

    if (e.target && e.target.closest && e.target.closest('.extra-param-menu')) return;

    // Keep the fixed-positioned menu tracking its anchor button when an
    // ancestor (or the page) scrolls, rather than closing it outright —
    // simpler and avoids misjudging harmless layout-settling reflows as
    // "the user scrolled away".
    positionExtraParamMenu(openMenuEl, openMenuAnchorBtn);
}, true);

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
