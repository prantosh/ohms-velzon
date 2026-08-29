let currentPage = 1;
let lastPage = 1;
let perPage = 10;
let searchTerm = '';
let currentTab = '';
let testGroups = [];

try {
    let dataEl = document.getElementById('testGroupsData');
    testGroups = dataEl ? JSON.parse(dataEl.textContent || '[]') : [];
} catch (e) {
    testGroups = [];
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

function testGroupName(testGroupCode) {
    if (!testGroupCode) return 'Ungrouped / General';
    let group = testGroups.find(g => String(g.id) === String(testGroupCode));
    return group ? group.test_group_name : 'Ungrouped / General';
}

/*
|--------------------------------------------------------------------------
| RICH-TEXT EDITOR
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

let contentEditor = null;
let contentEditorReady = ClassicEditor.create(document.querySelector('#content-field'), RICH_EDITOR_CONFIG)
    .then(editor => { contentEditor = editor; return bindTabIndent(editor); });

function toEditorHtml(text) {
    if (!text) return '';
    if (text.includes('<')) return text;
    return text.split('\n').map(line => `<p>${escapeHtml(line)}</p>`).join('');
}

/*
|--------------------------------------------------------------------------
| ITEMS CHECKBOX LIST
|--------------------------------------------------------------------------
*/

async function loadItemsCheckboxList(testGroupCode, checkedItemCodeSubs = [], packageId = '') {

    let wrap = document.getElementById('itemsCheckboxList');
    wrap.innerHTML = '<span class="text-muted small">Loading...</span>';

    let params = new URLSearchParams({ test_group_code: testGroupCode || '' });
    if (packageId) params.set('package_id', packageId);

    const response = await fetch(`/pathology-report-template/items?${params.toString()}`);
    const result = await response.json();

    if (!result.status || !result.data.length) {
        wrap.innerHTML = packageId
            ? '<span class="text-muted small">This package has no components in this test group.</span>'
            : '<span class="text-muted small">No items found for this test group.</span>';
        return;
    }

    wrap.innerHTML = result.data.map(item => `
    <div class="form-check">
        <input class="form-check-input item-checkbox" type="checkbox"
               value="${escapeHtml(item.item_code_sub)}"
               id="item-cb-${escapeHtml(item.item_code_sub)}"
               ${checkedItemCodeSubs.includes(item.item_code_sub) ? 'checked' : ''}>
        <label class="form-check-label" for="item-cb-${escapeHtml(item.item_code_sub)}">
            ${escapeHtml(item.item_description_sub)}
        </label>
    </div>
    `).join('');
}

function reloadItemsCheckboxList() {
    loadItemsCheckboxList(
        document.getElementById('test_group_code-field').value,
        [],
        document.getElementById('package_id-field').value
    );
}

document.getElementById('test_group_code-field').addEventListener('change', reloadItemsCheckboxList);
document.getElementById('package_id-field').addEventListener('change', reloadItemsCheckboxList);

/*
|--------------------------------------------------------------------------
| PACKAGES DROPDOWN -- populated once; a package's components can span
| several test groups (e.g. Lipid Profile has both Biochemistry and
| Hematology components), so picking one here narrows the item list above
| to just its components in whichever group is currently selected.
|--------------------------------------------------------------------------
*/

async function loadPackagesDropdown() {

    const response = await fetch('/pathology-report-template/packages');
    const result = await response.json();

    let select = document.getElementById('package_id-field');

    if (!result.status) return;

    result.data.forEach(pkg => {
        let option = document.createElement('option');
        option.value = pkg.id;
        option.textContent = pkg.item_description_sub;
        select.appendChild(option);
    });
}

loadPackagesDropdown();

/*
|--------------------------------------------------------------------------
| TEMPLATE LIST
|--------------------------------------------------------------------------
*/

document.querySelectorAll('#groupTabs .nav-link').forEach(function (tab) {

    tab.addEventListener('click', function () {

        document.querySelectorAll('#groupTabs .nav-link').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');

        currentTab = tab.dataset.testGroupCode;
        loadTemplates(1);
    });
});

async function loadTemplates(page = 1) {
    currentPage = page;

    let params = new URLSearchParams({
        page: page,
        per_page: perPage,
        search: searchTerm
    });

    if (currentTab === 'ungrouped') {
        params.set('ungrouped', '1');
    } else if (currentTab) {
        params.set('test_group_code', currentTab);
    }

    const response = await fetch(`/pathology-report-template/list?${params.toString()}`);

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

            <td>${escapeHtml(raw.title)}</td>

            <td>${escapeHtml(testGroupName(raw.test_group_code))}</td>

            <td>${escapeHtml(raw.item_names)}</td>

            <td>${escapeHtml(raw.created_by_name)}</td>

            <td>${escapeHtml(raw.created_dt)}</td>

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

    await contentEditorReady;

    let editId = document.querySelector('#edit-id').value;

    let itemCodeSubs = Array.from(document.querySelectorAll('#itemsCheckboxList .item-checkbox:checked'))
        .map(cb => cb.value);

    let payload = {
        title: document.querySelector('#title-field').value,
        test_group_code: document.querySelector('#test_group_code-field').value || null,
        item_code_subs: itemCodeSubs,
        content: contentEditor.getData(),
        status: document.querySelector('#status-field').value
    };

    let url = '/pathology-report-template/store';

    if (editId) {
        url = `/pathology-report-template/update/${editId}`;
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

    loadTemplates(currentPage);
});

document.getElementById('showModal').addEventListener('hidden.bs.modal', function () {

    document.querySelector('.tablelist-form').reset();

    if (contentEditor) contentEditor.setData('');

    document.getElementById('package_id-field').value = '';

    document.getElementById('itemsCheckboxList').innerHTML =
        '<span class="text-muted small">Select a Test Group above to list its items.</span>';

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

        const response = await fetch(`/pathology-report-template/edit/${id}`);
        const result = await response.json();

        await contentEditorReady;

        if (result.status) {

            document.querySelector('#title-field').value = result.data.title;
            document.querySelector('#test_group_code-field').value = result.data.test_group_code ?? '';
            document.querySelector('#package_id-field').value = '';
            contentEditor.setData(toEditorHtml(result.data.content ?? ''));
            document.querySelector('#status-field').value = result.data.status;

            await loadItemsCheckboxList(result.data.test_group_code, result.data.item_code_subs || []);
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

        let response = await fetch(`/pathology-report-template/delete/${id}`, {
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
