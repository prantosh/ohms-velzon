let currentPage = 1;
let lastPage = 1;
let perPage = 10;
let searchTerm = '';
let extraFieldTypes = [];

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
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/*
|--------------------------------------------------------------------------
| PARAMETER CARDS -- built into a template at creation time. A parameter's
| starting value is picked from its master list (for SELECT-type fields)
| or typed free text, and stays editable either way.
|--------------------------------------------------------------------------
*/

function renderTemplateParamCard(fieldTypeId, fieldName, inputType, value) {

    let inputHtml;

    if (inputType === 'SELECT') {

        let fieldType = extraFieldTypes.find(f => String(f.id) === String(fieldTypeId));
        let options = (fieldType && fieldType.options) ? fieldType.options.slice() : [];

        if (value && !options.includes(value)) {
            options = [value, ...options];
        }

        inputHtml = `
        <select class="form-select form-select-sm template-param-input">
            <option value="">-- Select --</option>
            ${options.map(o => `<option value="${escapeHtml(o)}" ${o === value ? 'selected' : ''}>${escapeHtml(o)}</option>`).join('')}
        </select>
        `;

    } else if (inputType === 'TEXTAREA') {

        inputHtml = `<textarea class="form-control form-control-sm template-param-input" rows="2">${escapeHtml(value)}</textarea>`;

    } else {

        inputHtml = `<input type="text" class="form-control form-control-sm template-param-input" value="${escapeHtml(value)}">`;
    }

    return `
    <div class="template-param-item border rounded p-2" data-field-type-id="${fieldTypeId}">

        <label class="form-label mb-1 fw-semibold small">${escapeHtml(fieldName)}</label>

        ${inputHtml}

        <div class="mt-1 text-end">
            <button type="button" class="btn btn-sm btn-danger remove-template-param-btn" title="Remove">
                <i class="ri-close-line"></i>
            </button>
        </div>

    </div>
    `;
}

function clearTemplateParamCards() {

    document.querySelectorAll('#templateParamsContainer .template-param-item').forEach(el => el.remove());
}

function collectTemplateParams() {

    let params = [];

    document.querySelectorAll('#templateParamsContainer .template-param-item').forEach(card => {

        params.push({
            field_type_id: card.dataset.fieldTypeId,
            value: card.querySelector('.template-param-input').value
        });
    });

    return params;
}

let openMenuAnchorBtn = null;
let openMenuEl = null;

function closeAllTemplateParamMenus() {

    document.querySelectorAll('.template-param-menu').forEach(m => {
        m.style.display = 'none';
    });

    openMenuAnchorBtn = null;
    openMenuEl = null;
}

function positionTemplateParamMenu(menu, addBtn) {

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

/*
|--------------------------------------------------------------------------
| TEMPLATE LIST
|--------------------------------------------------------------------------
*/

async function loadTemplates(page = 1) {
    currentPage = page;

    const response = await fetch(
        `/test-report-template/list?page=${page}&per_page=${perPage}&search=${encodeURIComponent(searchTerm)}`
    );

    const result = await response.json();

    let tbody = document.querySelector('#templateTable tbody');

    tbody.innerHTML = '';

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText =
        `Page ${result.pagination.current_page}`;

    document.querySelector('#pagination-info').innerText =
        `Total Records : ${result.pagination.total}`;

    result.data.forEach(raw => {

        let statusBadge = raw.status === 'ACTIVE'
            ? '<span class="badge bg-success">ACTIVE</span>'
            : '<span class="badge bg-danger">INACTIVE</span>';

        tbody.innerHTML += `
        <tr>

            <td>
                <input type="checkbox">
            </td>

            <td>${escapeHtml(raw.title)}</td>

            <td>${escapeHtml(raw.test_type_name ?? raw.item_code_sub)}</td>

            <td>${statusBadge}</td>

            <td>

                <a href="#showModal"
                   data-bs-toggle="modal"
                   class="btn btn-sm btn-soft-success edit-item-btn me-1"
                   data-id="${raw.id}"
                   title="Edit">
                    <i class="ri-pencil-fill"></i>
                </a>

                <a href="javascript:void(0)"
                   class="btn btn-sm btn-soft-danger delete-item-btn"
                   data-id="${raw.id}"
                   title="Delete">
                    <i class="ri-delete-bin-5-fill"></i>
                </a>

            </td>

        </tr>
        `;
    });
}

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

    let editId = document.querySelector('#edit-id').value;

    let payload = {
        title: document.querySelector('#title-field').value,
        item_code_sub: document.querySelector('#item_code_sub-field').value,
        remarks: document.querySelector('#remarks-field').value,
        status: document.querySelector('#status-field').value,
        parameters: collectTemplateParams()
    };

    let url = '/test-report-template/store';

    if (editId) {
        url = `/test-report-template/update/${editId}`;
    }

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!response.ok || !result.status) {

        let errorText = result.errors
            ? Object.values(result.errors).flat().join(', ')
            : (result.message ?? 'Unable to save template.');

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

    this.reset();
    clearTemplateParamCards();

    loadTemplates(currentPage);
});

document.getElementById('showModal').addEventListener('hidden.bs.modal', function () {

    document.querySelector('.tablelist-form').reset();
    clearTemplateParamCards();
    closeAllTemplateParamMenus();

    document.querySelector('#edit-id').value = '';

    document.querySelector('#modal-title').innerText = 'Add Template';
    document.querySelector('#add-btn').innerText = 'Save Template';
});

document.addEventListener('click', async function (e) {

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadTemplates(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadTemplates(currentPage + 1);
        return;
    }

    let editBtn = e.target.closest('.edit-item-btn');

    if (editBtn) {

        document.querySelector('#modal-title').innerText = 'Update Template';
        document.querySelector('#add-btn').innerText = 'Update Template';

        let id = editBtn.dataset.id;

        document.querySelector('#edit-id').value = id;

        const response = await fetch(`/test-report-template/edit/${id}`);
        const result = await response.json();

        if (result.status) {

            document.querySelector('#title-field').value = result.data.title;
            document.querySelector('#item_code_sub-field').value = result.data.item_code_sub;
            document.querySelector('#remarks-field').value = result.data.remarks ?? '';
            document.querySelector('#status-field').value = result.data.status;

            clearTemplateParamCards();

            let addWrap = document.querySelector('#templateParamsContainer .template-param-add-wrap');

            (result.data.parameters || []).forEach(function (p) {

                let fieldType = extraFieldTypes.find(f => String(f.id) === String(p.field_type_id));

                if (!fieldType) return;

                let wrapper = document.createElement('div');

                wrapper.innerHTML = renderTemplateParamCard(
                    fieldType.id, fieldType.field_name, fieldType.input_type, p.value
                );

                addWrap.parentElement.insertBefore(wrapper.firstElementChild, addWrap);
            });
        }

        return;
    }

    let deleteBtn = e.target.closest('.delete-item-btn');

    if (deleteBtn) {

        let id = deleteBtn.dataset.id;

        let confirm = await Swal.fire({
            title: 'Delete this Template?',
            text: 'This record will be permanently deleted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0ab39c',
            cancelButtonColor: '#f06548',
            confirmButtonText: 'Yes, Delete'
        });

        if (!confirm.isConfirmed) return;

        let response = await fetch(`/test-report-template/delete/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken()
            }
        });

        let result = await response.json();

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Deleted' : 'Error',
            text: result.message
        });

        if (result.status) {
            loadTemplates(currentPage);
        }
    }
});

document.addEventListener('click', function (e) {

    let addBtn = e.target.closest('.add-template-param-btn');

    if (addBtn) {

        let container = document.getElementById('templateParamsContainer');

        let presentIds = Array.from(container.querySelectorAll('.template-param-item'))
            .map(el => String(el.dataset.fieldTypeId));

        let menu = addBtn.closest('.template-param-add-wrap').querySelector('.template-param-menu');

        let alreadyOpen = menu.style.display === 'block';

        closeAllTemplateParamMenus();

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
                    <a class="dropdown-item add-template-param-option" href="javascript:void(0)"
                       data-field-type-id="${ft.id}"
                       data-field-name="${escapeHtml(ft.field_name)}"
                       data-input-type="${ft.input_type}">
                       ${escapeHtml(ft.field_name)}
                    </a>
                </li>
                `;
            });
        }

        positionTemplateParamMenu(menu, addBtn);

        openMenuAnchorBtn = addBtn;
        openMenuEl = menu;

        return;
    }

    let addOption = e.target.closest('.add-template-param-option');

    if (addOption) {

        closeAllTemplateParamMenus();

        let container = document.getElementById('templateParamsContainer');
        let addWrap = container.querySelector('.template-param-add-wrap');

        let wrapper = document.createElement('div');

        wrapper.innerHTML = renderTemplateParamCard(
            addOption.dataset.fieldTypeId,
            addOption.dataset.fieldName,
            addOption.dataset.inputType,
            ''
        );

        container.insertBefore(wrapper.firstElementChild, addWrap);

        return;
    }

    let removeBtn = e.target.closest('.remove-template-param-btn');

    if (removeBtn) {
        removeBtn.closest('.template-param-item').remove();
        return;
    }

    if (e.target.closest('.add-template-param-btn')) return;
    if (e.target.closest('.template-param-menu')) return;

    closeAllTemplateParamMenus();
});

document.addEventListener('scroll', function (e) {

    if (!openMenuAnchorBtn || !openMenuEl) return;

    if (e.target && e.target.closest && e.target.closest('.template-param-menu')) return;

    positionTemplateParamMenu(openMenuEl, openMenuAnchorBtn);
}, true);

document.addEventListener('change', function (e) {

    if (e.target.id === 'perPage') {
        perPage = parseInt(e.target.value);
        loadTemplates(1);
    }
});

document.getElementById('searchInput').addEventListener('input', function () {

    searchTerm = this.value;
    loadTemplates(1);
});

loadTemplates();
