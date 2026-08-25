let currentPage = 1;
let lastPage = 1;
let perPage = 10;
let searchTerm = '';

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
| RICH-TEXT EDITORS -- Clinical History / Findings / Impression
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

let editors = {};

async function initTemplateEditors() {

    const fields = ['clinical_history', 'findings', 'impression'];

    for (const field of fields) {
        editors[field] = bindTabIndent(await ClassicEditor.create(
            document.querySelector(`#${field}-field`),
            RICH_EDITOR_CONFIG
        ));
    }
}

// Records saved before rich-text editing existed are plain text with literal
// newlines -- each line becomes its own paragraph so it displays the same
// way it would have as plain text. Already-HTML content (post-feature)
// passes through untouched.
function toEditorHtml(text) {
    if (!text) return '';
    if (text.includes('<')) return text;
    return text.split('\n').map(line => `<p>${escapeHtml(line)}</p>`).join('');
}

let editorsReady = initTemplateEditors();

async function loadTemplates(page = 1) {
    currentPage = page;

    const { result } = await fetchJson(
        `/non-pathology-report-template/list?page=${page}&per_page=${perPage}&search=${encodeURIComponent(searchTerm)}`
    );

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

            <td>${escapeHtml(raw.item_name ?? raw.item_code_sub)}</td>

            <td>${escapeHtml(raw.created_by_name ?? '-')}</td>

            <td>${escapeHtml(raw.created_dt ?? '-')}</td>

            <td>${escapeHtml(raw.updated_by_name ?? '-')}</td>

            <td>${escapeHtml(raw.updated_dt ?? '-')}</td>

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

    let form = this;

    try {

        await editorsReady;

        let editId = document.querySelector('#edit-id').value;

        let payload = {
            title: document.querySelector('#title-field').value,
            item_code_sub: document.querySelector('#item_code_sub-field').value,
            clinical_history: editors.clinical_history.getData(),
            findings: editors.findings.getData(),
            impression: editors.impression.getData(),
            status: document.querySelector('#status-field').value
        };

        let url = '/non-pathology-report-template/store';

        if (editId) {
            url = `/non-pathology-report-template/update/${editId}`;
        }

        const { response, result } = await fetchJson(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken()
            },
            body: JSON.stringify(payload)
        });

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

        form.reset();

        loadTemplates(currentPage);

    } catch (err) {

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: err.message || 'Unable to save template.'
        });
    }
});

document.getElementById('showModal').addEventListener('hidden.bs.modal', async function () {

    document.querySelector('.tablelist-form').reset();

    await editorsReady;
    editors.clinical_history.setData('');
    editors.findings.setData('');
    editors.impression.setData('');

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

        const { result } = await fetchJson(`/non-pathology-report-template/edit/${id}`);

        await editorsReady;

        if (result.status) {

            document.querySelector('#title-field').value = result.data.title;
            document.querySelector('#item_code_sub-field').value = result.data.item_code_sub;
            editors.clinical_history.setData(toEditorHtml(result.data.clinical_history ?? ''));
            editors.findings.setData(toEditorHtml(result.data.findings ?? ''));
            editors.impression.setData(toEditorHtml(result.data.impression ?? ''));
            document.querySelector('#status-field').value = result.data.status;
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

        const { result } = await fetchJson(`/non-pathology-report-template/delete/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken()
            }
        });

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
