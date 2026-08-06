let currentPage = 1;
let lastPage = 1;
let currentItemCode = '';
let searchTerm = '';
let perPage = 25;
let currentAnalyteParentId = null;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

/*
|--------------------------------------------------------------------------
| INSERT ± AT CURSOR (range fields) -- works for both static fields and
| dynamically-generated analyte-row fields via event delegation.
|--------------------------------------------------------------------------
*/

document.addEventListener('click', function (e) {

    let btn = e.target.closest('.insert-plusminus');

    if (!btn) return;

    let input = btn.closest('.input-group')?.querySelector('input');

    if (!input) return;

    let start = input.selectionStart ?? input.value.length;
    let end = input.selectionEnd ?? input.value.length;

    input.value = input.value.slice(0, start) + '±' + input.value.slice(end);

    input.focus();
    input.setSelectionRange(start + 1, start + 1);
});

/*
|--------------------------------------------------------------------------
| DETAIL RANGE QUICK-PICK (range fields) -- lets staff pick a saved,
| highly-descriptive range instead of typing it out each time. Selecting
| an option replaces the sibling input's full value.
|--------------------------------------------------------------------------
*/

let detailRangeOptions = [];

async function loadDetailRangeOptions() {

    const response = await fetch('/detail-range-master/options');
    const result = await response.json();

    detailRangeOptions = result.status ? result.data : [];

    populateRangeQuickPicks(document);
}

function rangeQuickPickOptionsHtml() {

    let html = '<option value="">Pick...</option>';

    detailRangeOptions.forEach(o => {

        let label = o.name.length > 60 ? o.name.slice(0, 60) + '…' : o.name;

        html += `<option value="${escapeHtml(o.name)}">${escapeHtml(label)}</option>`;
    });

    return html;
}

function populateRangeQuickPicks(container) {

    container.querySelectorAll('.range-quick-pick').forEach(select => {
        select.innerHTML = rangeQuickPickOptionsHtml();
    });
}

document.addEventListener('change', function (e) {

    let select = e.target.closest('.range-quick-pick');

    if (!select || !select.value) return;

    let input = select.closest('.input-group')?.querySelector('input');

    if (!input) return;

    input.value = select.value;

    select.value = '';
});

async function loadParameters(page = 1) {

    if (!currentItemCode) return;

    currentPage = page;

    const response = await fetch(
        `/test-parameter/list/${currentItemCode}?page=${page}&per_page=${perPage}&search=${encodeURIComponent(searchTerm)}`
    );

    const result = await response.json();

    let tbody = document.querySelector('#parameter-table-body');

    tbody.innerHTML = '';

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText =
        `Page ${result.pagination.current_page}`;

    document.querySelector('#pagination-info').innerText =
        result.pagination.total > 0
            ? `Showing ${result.pagination.from} to ${result.pagination.to} of ${result.pagination.total} records`
            : 'No records found';

    result.data.forEach(raw => {

        tbody.innerHTML += `
        <tr>

            <td>${raw.item_code}</td>

            <td>${raw.item_code_sub ?? ''}</td>

            <td>${raw.item_description_sub ?? ''}</td>

            <td>${raw.test_group_name ?? ''}</td>

            <td>${raw.sample_name ?? ''}</td>

            <td>${raw.uom ?? ''}</td>

            <td>${raw.range_male ?? ''}</td>

            <td>${raw.range_female ?? ''}</td>

            <td>${raw.range_common ?? ''}</td>

            <td>${raw.method ?? ''}</td>

            <td>${raw.report_days ?? 0}</td>

            <td>

                <a href="#showModal"
                   data-bs-toggle="modal"
                   class="btn btn-sm btn-soft-success edit-item-btn me-1"
                   data-id="${raw.id}"
                   title="Edit">
                    <i class="ri-pencil-fill"></i>
                </a>

                <a href="#analyteModal"
                   data-bs-toggle="modal"
                   class="btn btn-sm btn-soft-primary manage-analytes-btn"
                   data-id="${raw.id}"
                   data-name="${escapeHtml(raw.item_description_sub ?? '')}"
                   title="Manage Analytes">
                    <i class="ri-list-check-2"></i>
                </a>

            </td>

        </tr>
        `;
    });
}

function escapeHtml(value) {

    return (value ?? '').toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function renderAnalyteViewRow(a) {

    let statusBadge = a.status === 'ACTIVE'
        ? '<span class="badge bg-success">ACTIVE</span>'
        : '<span class="badge bg-danger">INACTIVE</span>';

    return `
    <tr data-analyte-id="${a.id}"
        data-name="${escapeHtml(a.analyte_name)}"
        data-group="${escapeHtml(a.group_name)}"
        data-uom="${escapeHtml(a.uom)}"
        data-range_male="${escapeHtml(a.range_male)}"
        data-range_female="${escapeHtml(a.range_female)}"
        data-range_common="${escapeHtml(a.range_common)}"
        data-method="${escapeHtml(a.method)}"
        data-status="${a.status}">

        <td>${escapeHtml(a.analyte_name)}</td>
        <td>${escapeHtml(a.group_name)}</td>
        <td>${escapeHtml(a.uom)}</td>
        <td>${escapeHtml(a.range_male)}</td>
        <td>${escapeHtml(a.range_female)}</td>
        <td>${escapeHtml(a.range_common)}</td>
        <td>${escapeHtml(a.method)}</td>
        <td>${statusBadge}</td>
        <td class="text-nowrap">
            <a href="javascript:void(0)" class="btn btn-sm btn-soft-success edit-analyte-btn me-1" title="Edit">
                <i class="ri-pencil-fill"></i>
            </a>
            <a href="javascript:void(0)" class="btn btn-sm btn-soft-danger delete-analyte-btn" title="Delete">
                <i class="ri-delete-bin-5-fill"></i>
            </a>
        </td>
    </tr>
    `;
}

function uomOptionsHtml(selectedValue) {

    let html = '<option value="">Select</option>';

    (window.uomMasterOptions || []).forEach(name => {
        html += `<option value="${escapeHtml(name)}" ${name === selectedValue ? 'selected' : ''}>${escapeHtml(name)}</option>`;
    });

    return html;
}

function renderAnalyteEditRow(a) {

    return `
    <tr data-analyte-id="${a.id}">

        <td><input type="text" class="form-control form-control-sm edit-analyte_name" value="${escapeHtml(a.analyte_name)}"></td>
        <td><input type="text" class="form-control form-control-sm edit-analyte_group" value="${escapeHtml(a.group_name)}" placeholder="e.g. A"></td>
        <td>
            <select class="form-select form-select-sm edit-analyte_uom">
                ${uomOptionsHtml(a.uom)}
            </select>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <input type="text" class="form-control form-control-sm edit-analyte_range_male" value="${escapeHtml(a.range_male)}">
                <select class="form-select form-select-sm range-quick-pick" style="max-width:60px;" title="Pick a saved detail range"></select>
                <button type="button" class="btn btn-outline-secondary insert-plusminus" title="Insert &plusmn;">&plusmn;</button>
            </div>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <input type="text" class="form-control form-control-sm edit-analyte_range_female" value="${escapeHtml(a.range_female)}">
                <select class="form-select form-select-sm range-quick-pick" style="max-width:60px;" title="Pick a saved detail range"></select>
                <button type="button" class="btn btn-outline-secondary insert-plusminus" title="Insert &plusmn;">&plusmn;</button>
            </div>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <input type="text" class="form-control form-control-sm edit-analyte_range_common" value="${escapeHtml(a.range_common)}">
                <select class="form-select form-select-sm range-quick-pick" style="max-width:60px;" title="Pick a saved detail range"></select>
                <button type="button" class="btn btn-outline-secondary insert-plusminus" title="Insert &plusmn;">&plusmn;</button>
            </div>
        </td>
        <td><input type="text" class="form-control form-control-sm edit-analyte_method" value="${escapeHtml(a.method)}"></td>
        <td>
            <select class="form-select form-select-sm edit-analyte_status">
                <option value="ACTIVE" ${a.status === 'ACTIVE' ? 'selected' : ''}>ACTIVE</option>
                <option value="INACTIVE" ${a.status === 'INACTIVE' ? 'selected' : ''}>INACTIVE</option>
            </select>
        </td>
        <td class="text-nowrap">
            <a href="javascript:void(0)" class="btn btn-sm btn-success save-analyte-btn me-1" title="Save">
                <i class="ri-save-line"></i>
            </a>
            <a href="javascript:void(0)" class="btn btn-sm btn-light cancel-analyte-btn" title="Cancel">
                <i class="ri-close-line"></i>
            </a>
        </td>
    </tr>
    `;
}

async function loadAnalytes(invoiceItemDetailId) {

    const response = await fetch(`/test-parameter/analytes/${invoiceItemDetailId}`);
    const result = await response.json();

    let tbody = document.querySelector('#analyteTableBody');

    tbody.innerHTML = '';

    if (result.status) {
        result.data.forEach(a => {
            tbody.innerHTML += renderAnalyteViewRow(a);
        });
    }
}

/*
|--------------------------------------------------------------------------
| MANAGE SUB GROUPS (club several analyte groups together, e.g.
| Sub Group "Liver Panel" = analyte groups A + B)
|--------------------------------------------------------------------------
*/

let currentAvailableGroups = [];

async function loadSubGroups(invoiceItemDetailId) {

    const response = await fetch(`/test-parameter/sub-groups/${invoiceItemDetailId}`);
    const result = await response.json();

    if (!result.status) return;

    currentAvailableGroups = result.available_groups || [];

    let tbody = document.querySelector('#subGroupTableBody');
    tbody.innerHTML = '';

    result.data.forEach(sg => {
        tbody.innerHTML += renderSubGroupViewRow(sg);
    });

    renderGroupCheckboxes(document.getElementById('new-subgroup_groups'), currentAvailableGroups, []);
}

function renderGroupCheckboxes(container, availableGroups, selectedGroups) {

    if (!container) return;

    if (availableGroups.length === 0) {
        container.innerHTML = '<span class="text-muted small">No analyte groups defined yet.</span>';
        return;
    }

    container.innerHTML = availableGroups.map(g => `
        <div class="form-check form-check-inline m-0">
            <input type="checkbox" class="form-check-input subgroup-group-checkbox" value="${escapeHtml(g)}" id="chk-${container.id}-${escapeHtml(g)}" ${selectedGroups.includes(g) ? 'checked' : ''}>
            <label class="form-check-label small" for="chk-${container.id}-${escapeHtml(g)}">${escapeHtml(g)}</label>
        </div>
    `).join('');
}

function checkedGroupValues(container) {

    return Array.from(container.querySelectorAll('.subgroup-group-checkbox:checked')).map(el => el.value);
}

function renderSubGroupViewRow(sg) {

    let groupNames = (sg.members || []).map(m => m.group_name);

    return `
    <tr data-subgroup-id="${sg.id}" data-name="${escapeHtml(sg.name)}" data-groups="${escapeHtml(groupNames.join(','))}">
        <td>${escapeHtml(sg.name)}</td>
        <td>${escapeHtml(groupNames.join(', '))}</td>
        <td class="text-nowrap">
            <a href="javascript:void(0)" class="btn btn-sm btn-soft-success edit-subgroup-btn me-1" title="Edit">
                <i class="ri-pencil-fill"></i>
            </a>
            <a href="javascript:void(0)" class="btn btn-sm btn-soft-danger delete-subgroup-btn" title="Delete">
                <i class="ri-delete-bin-5-fill"></i>
            </a>
        </td>
    </tr>
    `;
}

function renderSubGroupEditRow(id, name) {

    return `
    <tr data-subgroup-id="${id}">
        <td><input type="text" class="form-control form-control-sm edit-subgroup_name" value="${escapeHtml(name)}"></td>
        <td><div class="d-flex flex-wrap gap-2 edit-subgroup_groups" id="edit-subgroup-groups-${id}"></div></td>
        <td class="text-nowrap">
            <a href="javascript:void(0)" class="btn btn-sm btn-success save-subgroup-btn me-1" title="Save">
                <i class="ri-save-line"></i>
            </a>
            <a href="javascript:void(0)" class="btn btn-sm btn-light cancel-subgroup-btn" title="Cancel">
                <i class="ri-close-line"></i>
            </a>
        </td>
    </tr>
    `;
}

document.getElementById('btnAddSubGroupRow').addEventListener('click', async function () {

    let name = document.querySelector('#new-subgroup_name').value.trim();

    if (!name) {
        Swal.fire('Error', 'Sub group name is required.', 'error');
        return;
    }

    let groupNames = checkedGroupValues(document.getElementById('new-subgroup_groups'));

    if (groupNames.length === 0) {
        Swal.fire('Error', 'Select at least one analyte group to club under this sub group.', 'error');
        return;
    }

    let formData = new FormData();
    formData.append('name', name);
    groupNames.forEach(g => formData.append('group_names[]', g));

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

    const response = await fetch(`/test-parameter/sub-groups/${currentAnalyteParentId}/store`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
        body: formData
    });

    const result = await response.json();

    if (!result.status) {
        Swal.fire('Error', result.message ?? 'Unable to add sub group.', 'error');
        return;
    }

    document.querySelector('#new-subgroup_name').value = '';

    Swal.close();

    loadSubGroups(currentAnalyteParentId);
});

document.addEventListener('click', async function (e) {

    let editBtn = e.target.closest('.edit-subgroup-btn');

    if (editBtn) {

        let row = editBtn.closest('tr');
        let id = row.dataset.subgroupId;
        let name = row.dataset.name;
        let groups = row.dataset.groups ? row.dataset.groups.split(',') : [];

        row.outerHTML = renderSubGroupEditRow(id, name);

        renderGroupCheckboxes(document.getElementById(`edit-subgroup-groups-${id}`), currentAvailableGroups, groups);

        return;
    }

    if (e.target.closest('.cancel-subgroup-btn')) {

        loadSubGroups(currentAnalyteParentId);
        return;
    }

    let saveBtn = e.target.closest('.save-subgroup-btn');

    if (saveBtn) {

        let row = saveBtn.closest('tr');
        let id = row.dataset.subgroupId;
        let name = row.querySelector('.edit-subgroup_name').value.trim();
        let groupNames = checkedGroupValues(row.querySelector('.edit-subgroup_groups'));

        if (!name) {
            Swal.fire('Error', 'Sub group name is required.', 'error');
            return;
        }

        if (groupNames.length === 0) {
            Swal.fire('Error', 'Select at least one analyte group to club under this sub group.', 'error');
            return;
        }

        let formData = new FormData();
        formData.append('name', name);
        groupNames.forEach(g => formData.append('group_names[]', g));

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

        const response = await fetch(`/test-parameter/sub-groups/update/${id}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            body: formData
        });

        const result = await response.json();

        if (!result.status) {
            Swal.fire('Error', result.message ?? 'Unable to update sub group.', 'error');
            return;
        }

        Swal.close();

        loadSubGroups(currentAnalyteParentId);

        return;
    }

    let deleteBtn = e.target.closest('.delete-subgroup-btn');

    if (deleteBtn) {

        let row = deleteBtn.closest('tr');
        let id = row.dataset.subgroupId;

        let confirmResult = await Swal.fire({
            title: 'Delete this sub group?',
            text: 'The analytes and their groups are not affected, only this sub-group heading is removed.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Delete'
        });

        if (!confirmResult.isConfirmed) return;

        const response = await fetch(`/test-parameter/sub-groups/delete/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() }
        });

        const result = await response.json();

        if (!result.status) {
            Swal.fire('Error', result.message ?? 'Unable to delete sub group.', 'error');
            return;
        }

        row.remove();
    }
});

document.getElementById('searchInput').addEventListener('input', function () {

    searchTerm = this.value;
    loadParameters(1);
});

document.getElementById('perPageSelect').addEventListener('change', function () {

    perPage = this.value;
    loadParameters(1);
});

document.getElementById('item-select').addEventListener('change', function () {

    currentItemCode = this.value;

    if (!currentItemCode) {

        document.querySelector('#parameter-table-wrap').style.display = 'none';
        document.querySelector('#no-selection-msg').style.display = 'block';
        return;
    }

    document.querySelector('#parameter-table-wrap').style.display = 'block';
    document.querySelector('#no-selection-msg').style.display = 'none';

    loadParameters(1);
});

document.querySelector('.tablelist-form').addEventListener('submit', async function (e) {

    e.preventDefault();

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

    let id = document.querySelector('#edit-id').value;

    let formData = new FormData();

    formData.append('test_group_code', document.querySelector('#test_group_code-field').value);
    formData.append('sample_master_id', document.querySelector('#sample_master_id-field').value);
    formData.append('uom', document.querySelector('#uom-field').value);
    formData.append('report_days', document.querySelector('#report_days-field').value || 0);
    formData.append('range_male', document.querySelector('#range_male-field').value);
    formData.append('range_female', document.querySelector('#range_female-field').value);
    formData.append('range_common', document.querySelector('#range_common-field').value);
    formData.append('method', document.querySelector('#method-field').value);

    const response = await fetch(`/test-parameter/update/${id}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken()
        },
        body: formData
    });

    const result = await response.json();

    if (!response.ok || !result.status) {

        let errorText = result.errors
            ? Object.values(result.errors).flat().join(', ')
            : (result.message ?? 'Unable to update test parameter.');

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: errorText
        });

        return;
    }

    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: result.message
    });

    bootstrap.Modal.getInstance(document.getElementById('showModal')).hide();

    loadParameters(currentPage);
});

document.addEventListener('click', async function (e) {

    if (e.target.closest('#firstPage')) {
        if (currentPage > 1) loadParameters(1);
        return;
    }

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadParameters(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadParameters(currentPage + 1);
        return;
    }

    if (e.target.closest('#lastPage')) {
        if (currentPage < lastPage) loadParameters(lastPage);
        return;
    }

    let editBtn = e.target.closest('.edit-item-btn');

    if (editBtn) {

        let id = editBtn.dataset.id;

        const response = await fetch(`/test-parameter/edit/${id}`);
        const result = await response.json();

        if (!result.status) return;

        let item = result.data;

        document.querySelector('#edit-id').value = item.id;
        document.querySelector('#display-item_code').value = item.item_code ?? '';
        document.querySelector('#display-item_code_sub').value = item.item_code_sub ?? '';
        document.querySelector('#display-item_description_sub').value = item.item_description_sub ?? '';
        document.querySelector('#test_group_code-field').value = item.test_group_code ?? '';
        document.querySelector('#sample_master_id-field').value = item.sample_master_id ?? '';
        document.querySelector('#uom-field').value = item.uom ?? '';
        document.querySelector('#report_days-field').value = item.report_days ?? 0;
        document.querySelector('#range_male-field').value = item.range_male ?? '';
        document.querySelector('#range_female-field').value = item.range_female ?? '';
        document.querySelector('#range_common-field').value = item.range_common ?? '';
        document.querySelector('#method-field').value = item.method ?? '';

        let hasAnalytes = (item.analyte_count ?? 0) > 0;

        document.querySelector('#analyte-notice').style.display = hasAnalytes ? 'block' : 'none';

        return;
    }

    let manageBtn = e.target.closest('.manage-analytes-btn');

    if (manageBtn) {

        currentAnalyteParentId = manageBtn.dataset.id;

        document.querySelector('#analyte-parent-id').value = currentAnalyteParentId;
        document.querySelector('#analyte-parent-name').innerText = manageBtn.dataset.name;

        loadAnalytes(currentAnalyteParentId);
        loadSubGroups(currentAnalyteParentId);
        resetCopyPicker();
        return;
    }

    if (e.target.closest('#btnAddAnalyteRow')) {

        let name = document.querySelector('#new-analyte_name').value.trim();

        if (!name) {
            Swal.fire('Error', 'Analyte name is required.', 'error');
            return;
        }

        let formData = new FormData();

        formData.append('analyte_name', name);
        formData.append('group_name', document.querySelector('#new-analyte_group').value);
        formData.append('uom', document.querySelector('#new-analyte_uom').value);
        formData.append('range_male', document.querySelector('#new-analyte_range_male').value);
        formData.append('range_female', document.querySelector('#new-analyte_range_female').value);
        formData.append('range_common', document.querySelector('#new-analyte_range_common').value);
        formData.append('method', document.querySelector('#new-analyte_method').value);

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

        const response = await fetch(`/test-parameter/analytes/${currentAnalyteParentId}/store`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            body: formData
        });

        const result = await response.json();

        if (!result.status) {
            Swal.fire('Error', result.message ?? 'Unable to add analyte.', 'error');
            return;
        }

        Swal.close();

        ['#new-analyte_name', '#new-analyte_group', '#new-analyte_uom', '#new-analyte_range_male', '#new-analyte_range_female', '#new-analyte_range_common', '#new-analyte_method']
            .forEach(sel => document.querySelector(sel).value = '');

        document.querySelector('#analyteTableBody').insertAdjacentHTML('beforeend', renderAnalyteViewRow(result.data));

        loadSubGroups(currentAnalyteParentId);

        return;
    }

    let editAnalyteBtn = e.target.closest('.edit-analyte-btn');

    if (editAnalyteBtn) {

        let row = editAnalyteBtn.closest('tr');

        let a = {
            id: row.dataset.analyteId,
            analyte_name: row.dataset.name,
            group_name: row.dataset.group,
            uom: row.dataset.uom,
            range_male: row.dataset.range_male,
            range_female: row.dataset.range_female,
            range_common: row.dataset.range_common,
            method: row.dataset.method,
            status: row.dataset.status
        };

        row.outerHTML = renderAnalyteEditRow(a);

        populateRangeQuickPicks(document.getElementById('analyteTableBody'));

        return;
    }

    if (e.target.closest('.cancel-analyte-btn')) {

        loadAnalytes(currentAnalyteParentId);
        return;
    }

    let saveAnalyteBtn = e.target.closest('.save-analyte-btn');

    if (saveAnalyteBtn) {

        let row = saveAnalyteBtn.closest('tr');
        let id = row.dataset.analyteId;

        let formData = new FormData();

        formData.append('analyte_name', row.querySelector('.edit-analyte_name').value);
        formData.append('group_name', row.querySelector('.edit-analyte_group').value);
        formData.append('uom', row.querySelector('.edit-analyte_uom').value);
        formData.append('range_male', row.querySelector('.edit-analyte_range_male').value);
        formData.append('range_female', row.querySelector('.edit-analyte_range_female').value);
        formData.append('range_common', row.querySelector('.edit-analyte_range_common').value);
        formData.append('method', row.querySelector('.edit-analyte_method').value);
        formData.append('status', row.querySelector('.edit-analyte_status').value);

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

        const response = await fetch(`/test-parameter/analytes/update/${id}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            body: formData
        });

        const result = await response.json();

        if (!result.status) {
            Swal.fire('Error', result.message ?? 'Unable to update analyte.', 'error');
            return;
        }

        Swal.close();

        loadAnalytes(currentAnalyteParentId);
        loadSubGroups(currentAnalyteParentId);

        return;
    }

    let deleteAnalyteBtn = e.target.closest('.delete-analyte-btn');

    if (deleteAnalyteBtn) {

        let row = deleteAnalyteBtn.closest('tr');
        let id = row.dataset.analyteId;

        let confirmResult = await Swal.fire({
            title: 'Delete this analyte?',
            text: 'This will remove it from the panel test.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Delete'
        });

        if (!confirmResult.isConfirmed) return;

        const response = await fetch(`/test-parameter/analytes/delete/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() }
        });

        const result = await response.json();

        if (!result.status) {
            Swal.fire('Error', result.message ?? 'Unable to delete analyte.', 'error');
            return;
        }

        row.remove();
        loadSubGroups(currentAnalyteParentId);
    }
});

/*
|--------------------------------------------------------------------------
| COPY ANALYTES & SUB GROUPS FROM ANOTHER TEST
|--------------------------------------------------------------------------
*/

function resetCopyPicker() {

    document.querySelector('#copy-source-category').value = '';

    let testSelect = document.querySelector('#copy-source-test');
    testSelect.innerHTML = '<option value="">Select Test</option>';
    testSelect.disabled = true;

    document.querySelector('#btnCopyAnalytes').disabled = true;
}

document.getElementById('copy-source-category').addEventListener('change', async function () {

    let category = this.value;

    let testSelect = document.querySelector('#copy-source-test');
    testSelect.innerHTML = '<option value="">Select Test</option>';
    testSelect.disabled = true;
    document.querySelector('#btnCopyAnalytes').disabled = true;

    if (!category) return;

    const response = await fetch(`/test-parameter/list/${category}?per_page=1000`);
    const result = await response.json();

    if (!result.status) return;

    result.data
        .filter(item => String(item.id) !== String(currentAnalyteParentId))
        .forEach(item => {
            testSelect.innerHTML += `<option value="${item.id}">${escapeHtml(item.item_code_sub)} - ${escapeHtml(item.item_description_sub)}</option>`;
        });

    testSelect.disabled = false;
});

document.getElementById('copy-source-test').addEventListener('change', function () {

    document.querySelector('#btnCopyAnalytes').disabled = !this.value;
});

document.getElementById('btnCopyAnalytes').addEventListener('click', async function () {

    let sourceId = document.querySelector('#copy-source-test').value;

    if (!sourceId) return;

    let formData = new FormData();
    formData.append('source_invoice_item_detail_id', sourceId);

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

    const response = await fetch(`/test-parameter/analytes/${currentAnalyteParentId}/copy`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
        body: formData
    });

    const result = await response.json();

    if (!result.status) {
        Swal.fire('Error', result.message ?? 'Unable to copy analytes.', 'error');
        return;
    }

    Swal.fire({ icon: 'success', title: 'Copied', text: result.message });

    loadAnalytes(currentAnalyteParentId);
    loadSubGroups(currentAnalyteParentId);
    resetCopyPicker();
});

loadDetailRangeOptions();
